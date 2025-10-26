# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

PHPX-BuildTools is a CLI and library package that provides the build system for PHPX WebAssembly projects. It handles compilation, bundling, caching, and development workflow automation for transforming PHPX applications into WebAssembly-ready packages.

## Core Architecture

### Main Components

#### `bin/phpx-build` - CLI Entry Point
- Main executable for all build commands
- Command routing (build, pack, export, watch)
- Configuration loading
- Error handling and output formatting

#### `src/Builder.php` - Build Orchestration
- Coordinates the entire build process
- Manages temporary directories
- Handles file copying and cleanup
- Integrates compiler and packer

#### `src/VendorCache.php` - Vendor Optimization
- Hash-based caching of production dependencies
- Manages `/tmp/phpx-build/{project}-vendor-{hash}`
- Runs `composer install --no-dev` on cache miss
- Validates cache integrity

#### `src/Watcher.php` - File System Monitoring
- Uses `inotifywait` for file change detection
- Debouncing to prevent excessive rebuilds
- Colored terminal output
- Excludes temporary files

#### `scripts/wasm-pack.php` - Legacy Pack Script
- Will be refactored into Builder class
- Handles PHPX compilation
- Creates WebAssembly data files
- Uses vendor caching

#### `scripts/wasm-export.php` - Legacy Export Script
- Will be refactored into Builder class
- Copies build artifacts to public directory
- Exports runtime files from vendor

#### `scripts/wasm-watch.sh` - Legacy Watch Script
- Will be refactored into Watcher class
- Bash-based file monitoring
- Color-coded output
- Signal handling

## Common Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test
vendor/bin/phpunit

# Run phpstan
composer phpstan

# Format code
composer cs-fix

# Use locally (from a PHPX project)
../PHPX-BuildTools/bin/phpx-build build
```

## Build Process Flow

### 1. Initialization
```php
$builder = new Builder($config);
$builder->init();
```

### 2. Temporary Directory Setup
- Create `/tmp/phpx-build/{project-name}-{hash}`
- Hash = MD5(project name + timestamp)
- Ensures isolation between builds

### 3. File Collection
```php
$builder->copyFiles([
    'bootstrap.php',
    'src/' => 'src/',
    'vendor/' => 'vendor/' // from cache
]);
```

### 4. Vendor Caching
```php
$cache = new VendorCache($projectName);
$vendorDir = $cache->get($composerJson, $composerLock);
// Returns cached or creates new vendor without dev dependencies
```

### 5. PHPX Compilation
```php
$compiler = new Compiler();
$compiler->compileDirectory($tempDir . '/src');
```

### 6. WebAssembly Packing
```php
$packer = new Packer();
$packer->pack(
    source: $tempDir,
    destination: '/app',
    output: 'build/php-web.data'
);
```

### 7. Export
```php
$builder->export([
    'build/php-web.data' => 'public/build/php-web.data',
    'vendor/syntaxx/wasm-php-runtime-vrzno/dist/*' => 'public/build/'
]);
```

### 8. Cleanup
```php
$builder->cleanup();
// Removes temporary directory
```

## Vendor Caching Strategy

### Cache Key Generation
```php
$composerJson = file_get_contents('composer.json');
$composerLock = file_get_contents('composer.lock');
$hash = md5($composerJson . $composerLock);
$cacheDir = "/tmp/phpx-build/{$projectName}-vendor-{$hash}";
```

### Cache Hit
- Vendor directory exists → copy directly to build
- Build time: ~0.4 seconds

### Cache Miss
- Run `COMPOSER_VENDOR_DIR={cacheDir} composer install --no-dev --no-interaction --no-scripts`
- Cache for future builds
- Build time: ~10-20 seconds (first time only)

### Cache Invalidation
- Automatically invalidated when composer.json or composer.lock changes
- Old cache directories remain (can be cleaned manually)

## Watch Mode Implementation

### File Monitoring
```bash
inotifywait -m -r \
    -e modify,create,delete,move \
    --exclude '(\.git|node_modules|vendor|build)' \
    src/ composer.json composer.lock
```

### Event Handling
```php
class Watcher {
    private int $lastBuildTime = 0;
    private const DEBOUNCE_SECONDS = 2;

    public function onFileChange(string $file): void {
        $now = time();
        if ($now - $this->lastBuildTime < self::DEBOUNCE_SECONDS) {
            return; // Debounce
        }

        $this->lastBuildTime = $now;
        $this->triggerRebuild();
    }
}
```

### Colored Output
```php
class Output {
    const GREEN = "\033[0;32m";
    const BLUE = "\033[0;34m";
    const YELLOW = "\033[1;33m";
    const RED = "\033[0;31m";
    const NC = "\033[0m"; // No Color

    public function success(string $message): void {
        echo self::GREEN . "✓ " . $message . self::NC . "\n";
    }
}
```

## Configuration

### Default Configuration (phpx-build.json)
```json
{
    "build": {
        "src": "src",
        "output": "public/build",
        "bootstrap": "bootstrap.php",
        "temp_dir": "/tmp/phpx-build"
    },
    "watch": {
        "paths": ["src", "composer.json", "composer.lock"],
        "exclude": [
            "*.swp", "*.swx", "*.tmp", "*~",
            ".git", "node_modules", "vendor", "build"
        ],
        "debounce": 2
    },
    "cache": {
        "vendor": true,
        "cleanup_old": true,
        "max_age_days": 30
    }
}
```

## CLI Commands

### build
```bash
phpx-build build [options]
```
Full build: pack + export

**Options:**
- `--no-cache` - Skip vendor caching
- `--verbose` - Detailed output
- `--config=<file>` - Custom config file

### pack
```bash
phpx-build pack [options]
```
Pack only (no export)

### export
```bash
phpx-build export [options]
```
Export only (assumes pack already done)

### watch
```bash
phpx-build watch [options]
```
Watch mode with auto-rebuild

**Options:**
- `--debounce=<seconds>` - Debounce delay (default: 2)
- `--no-initial-build` - Skip initial build

## Integration with PHPX Ecosystem

### Dependency Graph
```
PHPX-BuildTools
├── syntaxx/phpx-compiler (compilation)
├── syntaxx/webassembly-packer (bundling)
└── syntaxx/wasm-php-runtime-vrzno (runtime export)
```

### Used By
- PHPX-wasmstarter
- PHPX-wasmstarter-phpx
- Any PHPX application

## Testing Strategy

### Unit Tests
- VendorCache: Hash generation, cache hit/miss
- Builder: File copying, cleanup
- Watcher: Debouncing, event handling
- Configuration: Loading, validation

### Integration Tests
- Full build process
- Cache functionality
- Watch mode
- Multi-project isolation

### End-to-End Tests
- Build real PHPX project
- Verify output files
- Test cache reuse
- Watch mode reliability

## Performance Considerations

### Build Performance
- **First Build**: ~10-20 seconds (vendor install)
- **Cached Build**: ~0.4-2 seconds (cache hit)
- **Watch Rebuild**: ~0.4-2 seconds (cached vendor)

### Optimization Strategies
1. **Vendor Caching**: Eliminates repeated composer installs
2. **Temp Directory Outside Project**: Faster file operations
3. **Exclusion Patterns**: Skip unnecessary files
4. **Debouncing**: Prevent multiple rebuilds from rapid saves

### Memory Usage
- **Peak**: ~50-100MB during build
- **Vendor Cache**: ~20-50MB per project
- **Watch Mode**: ~10-20MB idle

## Migration Guide

### From Legacy Scripts to BuildTools

**Before:**
```json
{
    "scripts": {
        "wasm": "php scripts/wasm-pack.php && php scripts/wasm-export.php",
        "wasm:watch": ["Composer\\Config::disableProcessTimeout", "bash scripts/wasm-watch.sh"]
    }
}
```

**After:**
```json
{
    "require-dev": {
        "syntaxx/phpx-build-tools": "^1.0"
    },
    "scripts": {
        "wasm": "phpx-build build",
        "wasm:watch": ["Composer\\Config::disableProcessTimeout", "phpx-build watch"]
    }
}
```

**Then remove:**
- `scripts/wasm-pack.php`
- `scripts/wasm-export.php`
- `scripts/wasm-watch.sh`

## Development Guidelines

### Adding New Commands
1. Create command class in `src/Command/`
2. Register in `bin/phpx-build`
3. Add tests
4. Update README

### Modifying Build Process
1. Update `src/Builder.php`
2. Maintain backward compatibility
3. Add configuration option if needed
4. Document in CLAUDE.md

### Code Style
- Follow PSR-12
- Use strict types
- Type hint everything
- Document public APIs

## Known Considerations

- Requires `inotify-tools` for watch mode (Linux only)
- macOS/Windows watch mode needs alternative implementation
- Vendor cache grows over time (cleanup recommended)
- Temporary directories persist on failure (manual cleanup needed)

## Future Enhancements

- [ ] Project scaffolding (`phpx-build init`)
- [ ] Built-in dev server (`phpx-build serve`)
- [ ] Production optimizations (`--production` flag)
- [ ] Source maps for debugging
- [ ] Cache cleanup command
- [ ] macOS/Windows watch mode support
- [ ] Progress indicators for long builds
- [ ] Build hooks (pre-build, post-build)
