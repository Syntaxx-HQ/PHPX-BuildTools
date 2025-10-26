<?php

echo "Packing custom code...\n";

// Find project root (where composer.json is)
$projectRoot = getcwd();
while (!file_exists($projectRoot . '/composer.json') && $projectRoot !== '/') {
    $projectRoot = dirname($projectRoot);
}

if ($projectRoot === '/' || !file_exists($projectRoot . '/composer.json')) {
    fwrite(STDERR, "Error: Could not find project root (no composer.json found).\n");
    exit(1);
}

// Create temporary build directory outside the project
$projectName = basename($projectRoot);
$hash = md5($projectName . time()); // MD5 of project name + timestamp
$tempDir = '/tmp/phpx-build/' . $projectName . '-' . $hash;

echo "Using temporary directory: $tempDir\n";

// Function to recursively remove directory
function removeDir($dir) {
    if (!file_exists($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    return rmdir($dir);
}

// Create temp directory
if (file_exists($tempDir)) {
    removeDir($tempDir);
}
mkdir($tempDir, 0777, true);
echo "Created temporary directory\n";

// Copy bootstrap.php
echo "Copying bootstrap.php...\n";
copy($projectRoot . '/bootstrap.php', $tempDir . '/bootstrap.php');

// Copy directory function (simple, no exclusions)
function copyDir($src, $dst) {
    if (!file_exists($dst)) {
        mkdir($dst, 0777, true);
    }
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            if (is_dir($srcFile)) {
                copyDir($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
    closedir($dir);
}

// Get or create cached vendor directory without dev dependencies
function getCachedVendor($projectName, $projectRoot) {
    $composerJson = file_get_contents($projectRoot . '/composer.json');
    $composerLock = file_get_contents($projectRoot . '/composer.lock');

    // Calculate hash of both files
    $vendorHash = md5($composerJson . $composerLock);
    $vendorCacheDir = '/tmp/phpx-build/' . $projectName . '-vendor-' . $vendorHash;

    // Check if cached vendor exists
    if (is_dir($vendorCacheDir)) {
        echo "Using cached vendor directory: $vendorCacheDir\n";
        echo "  (Cache hit - skipping composer install)\n";
        return $vendorCacheDir;
    }

    // Cache miss - need to create vendor
    echo "Creating cached vendor directory: $vendorCacheDir\n";
    echo "  (Cache miss - running composer install --no-dev)\n";

    if (!is_dir($vendorCacheDir)) {
        mkdir($vendorCacheDir, 0777, true);
    }

    // Run composer install from project root with custom vendor directory
    // Use COMPOSER_VENDOR_DIR environment variable to specify custom location
    $currentDir = getcwd();
    chdir($projectRoot);

    echo "  Running: COMPOSER_VENDOR_DIR=$vendorCacheDir composer install --no-dev --no-interaction...\n";
    $command = 'COMPOSER_VENDOR_DIR=' . escapeshellarg($vendorCacheDir) . ' composer install --no-dev --no-interaction --no-scripts 2>&1';
    exec($command, $composerOutput, $composerReturnCode);

    chdir($currentDir);

    if ($composerReturnCode !== 0) {
        echo "Composer install failed:\n";
        echo implode("\n", $composerOutput) . "\n";
        removeDir($vendorCacheDir);
        exit(1);
    }

    echo "  Vendor cache created successfully\n";
    return $vendorCacheDir;
}

// Copy src directory (complete structure)
echo "Copying src directory...\n";
copyDir($projectRoot . '/src', $tempDir . '/src');

// Function to collect and export AI source maps for development debugging
function collectAndExportAiMaps($tempDir, $projectRoot) {
    echo "\n";
    echo "Collecting AI source maps for development debugging...\n";

    $debugDir = $projectRoot . '/build/debug';

    // Create debug directory
    if (!file_exists($debugDir)) {
        mkdir($debugDir, 0777, true);
    }

    // Find all .ai.map files in temp directory
    $aiMapFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && substr($file->getFilename(), -7) === '.ai.map') {
            $aiMapFiles[] = $file->getPathname();
        }
    }

    if (empty($aiMapFiles)) {
        echo "  No AI source maps found (this is normal if no PHPX files were compiled)\n";
        return;
    }

    echo "  Found " . count($aiMapFiles) . " AI source map(s)\n";

    // Prepare index data
    $indexData = [
        'schema' => 'https://phpx.dev/schemas/ai-debug-index/v1.json',
        'generated_at' => date('c'),
        'build_mode' => 'development',
        'compiler_version' => '1.1.0', // TODO: Get from compiler
        'files' => [],
        'statistics' => [
            'total_files' => 0,
            'total_jsx_elements' => 0,
            'total_compilation_time_ms' => 0,
            'build_timestamp' => time(),
        ],
    ];

    // Copy each AI map and extract metadata
    foreach ($aiMapFiles as $aiMapPath) {
        // Get relative path from src directory (preserves subdirectories)
        $srcDir = $tempDir . '/src/';
        $relativePath = substr($aiMapPath, strlen($srcDir)); // e.g., "Components/Button.php.ai.map"
        $compiledFile = substr($relativePath, 0, -7); // Remove ".ai.map" → "Components/Button.php"

        // Preserve directory structure in debug output (replace / with -)
        $flattenedFilename = str_replace('/', '-', $relativePath); // "compiled-main.php.ai.map"
        $destPath = $debugDir . '/' . $flattenedFilename;

        // Copy AI map file
        copy($aiMapPath, $destPath);

        // Read and parse AI map to extract metadata
        $aiMapContent = json_decode(file_get_contents($aiMapPath), true);

        // Determine source file path
        $sourceFile = $aiMapContent['summary']['source_file'] ?? 'unknown';

        // Make source file path relative to project root
        if (strpos($sourceFile, $tempDir) === 0) {
            $sourceFile = 'src/' . substr($sourceFile, strlen($tempDir . '/src/'));
        }

        // Determine virtual path in WASM (/app/src/...) - preserve full path with extension
        $virtualPath = '/app/src/' . $compiledFile;

        // Add to index
        $indexData['files'][$virtualPath] = [
            'source' => $sourceFile,
            'ai_map' => 'build/debug/' . $flattenedFilename,
            'checksum' => $aiMapContent['summary']['compiled_checksum'] ?? null,
            'jsx_elements' => $aiMapContent['statistics']['jsx_elements_transformed'] ?? 0,
            'compilation_time_ms' => $aiMapContent['statistics']['compilation_time_ms'] ?? 0,
        ];

        // Update statistics
        $indexData['statistics']['total_files']++;
        $indexData['statistics']['total_jsx_elements'] += $aiMapContent['statistics']['jsx_elements_transformed'] ?? 0;
        $indexData['statistics']['total_compilation_time_ms'] += $aiMapContent['statistics']['compilation_time_ms'] ?? 0;

        echo "  ✓ Exported: $flattenedFilename\n";
    }

    // Write index.json
    $indexPath = $debugDir . '/index.json';
    file_put_contents(
        $indexPath,
        json_encode($indexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo "  ✓ Generated index.json with " . count($indexData['files']) . " file(s)\n";
    echo "\n";
    echo "  AI Debug Directory: $debugDir\n";
    echo "  Index File: $indexPath\n";
    echo "  Total JSX Elements: " . $indexData['statistics']['total_jsx_elements'] . "\n";
    echo "  Total Compilation Time: " . round($indexData['statistics']['total_compilation_time_ms'], 2) . "ms\n";
    echo "\n";
}

// Get cached vendor directory (or create it)
$cachedVendorDir = getCachedVendor($projectName, $projectRoot);

// Copy cached vendor to build directory
echo "Copying vendor directory from cache...\n";
copyDir($cachedVendorDir, $tempDir . '/vendor');

echo "Files copied successfully\n";

// Compile PHPX files in temp directory
$devMode = getenv('PHPX_DEV_MODE') === '1';
$htmlMaps = getenv('PHPX_HTML_MAPS') === '1';
$aiMapsFlag = $devMode ? ' --ai-source-maps' : '';
$htmlMapsFlag = $htmlMaps ? ' --create-html-maps' : '';

$compileModeMsg = "Compiling PHPX files";
if ($devMode || $htmlMaps) {
    $modes = [];
    if ($devMode) $modes[] = "AI source maps";
    if ($htmlMaps) $modes[] = "HTML source maps";
    $compileModeMsg .= " (generating " . implode(" and ", $modes) . ")";
}
echo $compileModeMsg . "...\n";

exec($projectRoot . '/vendor/syntaxx/phpx-compiler/bin/compile --compile-php-files' . $aiMapsFlag . $htmlMapsFlag . ' ' . $tempDir . '/src ' . $tempDir . '/src', $output, $returnCode);
echo implode("\n", $output) . "\n";
if ($returnCode !== 0) {
    echo "Compilation failed with return code: $returnCode\n";
    // Clean up on failure
    removeDir($tempDir);
    exit(1);
}

// Collect and export AI source maps if in dev mode
if ($devMode) {
    collectAndExportAiMaps($tempDir, $projectRoot);
}

// Pack the temporary directory into WebAssembly data file
// Use src@dst syntax: source (absolute path outside CWD) @ destination (virtual path in WASM)
// Destination is /app so files are accessible at /app/bootstrap.php, /app/src/, /app/vendor/
echo "Packing into WebAssembly data file...\n";
exec('php vendor/syntaxx/webassembly-packer/bin/file-packager build/php-web.data --use-preload-cache --preload "' . $tempDir . '@/app" --js-output=build/php-web.data.js --no-node --exclude composer.* .git/** build/** public/** scripts/** .gitignore LICENSE README.md --export-name=createPhpModule', $packOutput, $packReturnCode);

if ($packReturnCode !== 0) {
    echo "Packing failed with return code: $packReturnCode\n";
    echo implode("\n", $packOutput) . "\n";
    // Clean up on failure
    removeDir($tempDir);
    exit(1);
}

echo "Packing completed successfully\n";

// Clean up temporary directory
// KEEP temp directory for debugging purposes!!!!!!!!!!
//echo "Cleaning up temporary directory...\n";
//removeDir($tempDir);
//echo "Cleanup completed\n";

// Report final size
if (file_exists($projectRoot . '/build/php-web.data')) {
    $size = filesize($projectRoot . '/build/php-web.data');
    $sizeKB = round($size / 1024, 2);
    echo "\n✓ Build completed successfully!\n";
    echo "  Data file size: {$sizeKB} KB\n";
}
