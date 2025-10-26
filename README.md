# PHPX Build Tools

Build tools and CLI utilities for PHPX WebAssembly projects.

## Overview

This package provides the build system for PHPX applications, handling compilation, bundling, and deployment of PHP applications to WebAssembly.

## Features

- **Build System**: Compile and pack PHPX applications for WebAssembly
- **Watch Mode**: Auto-rebuild on file changes during development
- **Vendor Optimization**: Smart caching of production dependencies
- **Fast Builds**: Cached vendor directory for quick rebuilds
- **Clean Architecture**: Build artifacts in temporary directories

## Installation

```bash
composer require syntaxx/phpx-build-tools --dev
```

## Usage

### Build Your Application

```bash
# Full build (pack + export)
vendor/bin/phpx-build build

# Pack only
vendor/bin/phpx-build pack

# Export only
vendor/bin/phpx-build export
```

### Watch Mode (Auto-rebuild)

```bash
vendor/bin/phpx-build watch
```

### Composer Scripts Integration

Add to your `composer.json`:

```json
{
    "scripts": {
        "wasm": "phpx-build build",
        "wasm:watch": [
            "Composer\\Config::disableProcessTimeout",
            "phpx-build watch"
        ]
    }
}
```

## How It Works

### Build Process

1. **Temporary Build Directory**: Creates `/tmp/phpx-build/{project}-{hash}`
2. **File Copy**: Copies `bootstrap.php`, `src/`, and vendor files
3. **Vendor Optimization**: Uses cached production vendor (no dev dependencies)
4. **PHPX Compilation**: Transforms PHPX files to standard PHP
5. **WebAssembly Packing**: Bundles everything into `.wasm` data file
6. **Export**: Copies build artifacts to `public/build/`
7. **Cleanup**: Removes temporary directory

### Vendor Caching

The build system caches the vendor directory based on `composer.json` and `composer.lock` hashes:
- **Cache Hit**: Reuses existing vendor (~0.4s build time)
- **Cache Miss**: Runs `composer install --no-dev` and caches result

Cache location: `/tmp/phpx-build/{project}-vendor-{hash}`

### Watch Mode

Monitors for changes in:
- `src/` directory (PHP/PHPX files)
- `composer.json` (dependencies)
- `composer.lock` (dependency lock)

Features:
- Initial build on startup
- 2-second debouncing to prevent excessive rebuilds
- Colored output with clear status messages
- Excludes temporary files and editor backups

## Configuration

Create a `phpx-build.json` in your project root:

```json
{
    "build": {
        "src": "src",
        "output": "public/build",
        "bootstrap": "bootstrap.php"
    },
    "watch": {
        "paths": ["src", "composer.json", "composer.lock"],
        "exclude": ["*.swp", "*.tmp", "~"]
    }
}
```

## Requirements

- PHP 8.3 or higher
- `inotify-tools` (for watch mode on Linux)

### Installing inotify-tools

```bash
# Ubuntu/Debian
sudo apt-get install inotify-tools

# Fedora/RHEL
sudo yum install inotify-tools

# Arch Linux
sudo pacman -S inotify-tools
```

## Integration with PHPX Ecosystem

This package is designed to work seamlessly with:
- **PHPX-Compiler**: Transforms PHPX syntax to PHP
- **WebAssemblyPacker**: Bundles files for WebAssembly
- **PHPX-Framework**: React-like component framework
- **PHPX-WasmRuntime**: Runtime for executing PHP in browser

## Development

```bash
# Clone the repository
git clone https://github.com/syntaxx/phpx-build-tools
cd PHPX-BuildTools

# Install dependencies
composer install

# Run tests
composer test
```

## License

MIT License - see LICENSE file for details.

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.

## Credits

Part of the [PHPX ecosystem](https://github.com/syntaxx) - bringing React-like development to PHP with WebAssembly.
