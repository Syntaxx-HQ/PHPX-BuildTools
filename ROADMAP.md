# PHPX Build Process Roadmap

This document outlines planned improvements to the PHPX build process (`composer wasm`).

## Priority Levels

- 🔴 **High Priority** - Critical for developer experience
- 🟡 **Medium Priority** - Performance and productivity improvements
- 🟢 **Nice to Have** - Quality of life improvements

---

## High Priority Improvements

### 🔴 1. Watch Mode with Auto-Reload (HMR Lite)
**Problem:** Need to manually refresh browser after code changes

**Current State:**
- `composer wasm:watch` rebuilds on file changes
- Manual browser refresh required
- No state preservation

**Solution:**
- WebSocket connection between build tool and browser
- Inject WebSocket client into built application
- Send reload signal on successful build
- Auto-refresh browser page

**Future Enhancement (Full HMR):**
- Hot-swap components without full page reload
- Preserve application state during updates
- Smart patching of changed components only

**Example Output:**
```
👀 Watching for changes...
📝 File changed: src/components/Hero.php
🔨 Building... ✓ (1.2s)
🔄 Browser reloaded

👀 Watching for changes...
```

**Benefits:**
- Faster development workflow
- No manual refresh needed
- Foundation for full HMR later

---

### 🔴 2. Better Error Handling and Reporting
**Problem:** Compilation errors don't provide clear feedback

**Current State:**
```php
if ($returnCode !== 0) {
    echo "Compilation failed with return code: $returnCode\n";
}
```

**Solution:**
- Capture and parse compiler output
- Show which file failed with line numbers
- Syntax highlighting for error messages
- Exit with proper error codes
- Rollback on failure (keep previous working build)

**Example Output:**
```
❌ Compilation failed in src/components/Hero.php:42
   Expected closing tag for <section>

42 | return (
43 |     <section className="hero">
44 |         <div>Hello</div>
   |     ^-- Missing closing </section>
```

**Benefits:**
- Faster debugging
- Clear error messages
- Safer builds (rollback capability)

---

### 🔴 3. Better Logging and Output
**Problem:** Minimal feedback during build

**Current Output:**
```
Packing custom code...
Compiling PHPX files in app directory...
Export completed successfully!
```

**Improved Output:**
```
Building PHPX application...
├─ Cleaning app directory... ✓ (0.1s)
├─ Copying source files... ✓ (0.3s)
│  ├─ src/ (45 files)
│  └─ vendor/ (123 files)
├─ Compiling PHPX files... ✓ (2.1s)
│  ├─ Hero.php → Hero.php ✓
│  ├─ CodeDemo.php → CodeDemo.php ✓
│  ├─ Features.php → Features.php ✓
│  └─ ... (5 more files)
├─ Packing WebAssembly data... ✓ (1.2s)
│  └─ php-web.data (231 KB)
└─ Exporting to public/build... ✓ (0.4s)

✨ Build completed successfully in 4.1s
```

**Benefits:**
- Understand what's happening
- Identify slow steps
- Professional appearance

---

## Medium Priority Improvements

### 🟡 4. Add Incremental Compilation
**Problem:** Every file is recompiled even if unchanged

**Solution:**
- Track file modification timestamps
- Only compile PHPX files that changed since last build
- Hash-based change detection for dependencies
- Dramatically speed up rebuilds

**Implementation:**
- Store file hashes in `.build-cache/manifest.json`
- Compare hashes before compilation
- Only recompile changed files

**Benefits:**
- 10-100x faster rebuilds for small changes
- Better developer experience
- Less CPU usage

---

### 🟡 5. Add Build Caching
**Problem:** No caching between builds

**Solution:**
- Cache compiled PHP files in a `.cache` directory
- Store file hashes to detect changes
- Reuse cached compilation results when source hasn't changed
- Clear cache with `composer wasm:clean`

**File Structure:**
```
.cache/
├─ compiled/
│  ├─ Hero.php.cache
│  └─ CodeDemo.php.cache
└─ manifest.json
```

**Benefits:**
- Faster builds
- Efficient use of system resources
- Easy cache invalidation

---

### 🟡 6. Parallel Compilation
**Problem:** Files compiled sequentially

**Solution:**
- Compile multiple PHPX files in parallel
- Use PHP's process forking or parallel extension
- Significantly faster builds for large projects
- Respect CPU core count

**Example:**
```
Compiling 20 files using 8 workers...
[████████████████████] 100% (2.1s)
```

**Benefits:**
- 2-8x faster builds on multi-core systems
- Better resource utilization

---

### 🟡 7. Build Performance Metrics
**Problem:** No visibility into build time

**Solution:**
- Time each build phase
- Report timing for each step
- Identify bottlenecks
- Track build time trends

**Example Output:**
```
Build Performance:
├─ Cleaning: 0.1s
├─ Copying: 0.3s
├─ Compiling: 2.1s (slowest)
├─ Packing: 1.2s
└─ Exporting: 0.4s
Total: 4.1s

Tip: Compiling took 51% of build time. Consider incremental compilation.
```

**Benefits:**
- Identify optimization opportunities
- Track performance regressions
- Data-driven decisions

---

### 🟡 8. Dependency Graph Analysis
**Problem:** No understanding of component dependencies

**Solution:**
- Analyze `require_once` statements
- Build dependency tree
- Only recompile dependent files when shared component changes
- Detect circular dependencies

**Example:**
```
Dependency Graph:
main.php
├─ Router.php
├─ Hero.php
│  └─ HeroButton.php (ui)
├─ CodeDemo.php
│  └─ button.php (ui)
└─ Features.php

Circular dependencies: None ✓
```

**Benefits:**
- Smarter incremental builds
- Better understanding of codebase
- Catch dependency issues early

---

## Nice to Have Improvements

### 🟢 9. Add Build Modes (Development vs Production)
**Problem:** Same build for dev and production

**Solution:**
```bash
composer wasm:dev   # Fast builds, keep comments, no minification
composer wasm:prod  # Optimized builds, minification, remove debug code
```

**Features:**
- Development: Fast builds, verbose output, debug info
- Production: Optimized, minified, stripped debug code
- Environment-specific configurations

---

### 🟢 10. Add Source Maps
**Problem:** Debugging compiled code is difficult

**Solution:**
- Generate source maps during compilation
- Map compiled PHP back to original PHPX files
- Better error traces showing original file/line
- Easier debugging in browser console

**Example Error:**
```
Error in Hero.php:42 (original PHPX)
Instead of: Hero.php:14 (compiled)
```

---

### 🟢 11. Better Exclusion Configuration
**Problem:** Hardcoded exclusions in `wasm-pack.php`

**Current:**
```php
$excludedDirs = ['phpx-parser', 'phpx-compiler', 'webassembly-packer', 'lz4'];
```

**Solution:**
Move to `composer.json`:
```json
{
  "extra": {
    "php-wasm": {
      "target-dir": "build",
      "exclude": [
        "vendor/syntaxx/phpx-parser",
        "vendor/syntaxx/phpx-compiler",
        "vendor/syntaxx/webassembly-packer",
        "vendor/syntaxx/lz4"
      ]
    }
  }
}
```

**Benefits:**
- Project-specific configuration
- Better documentation
- Easier maintenance

---

### 🟢 12. Add Build Verification
**Problem:** No validation that build succeeded

**Solution:**
- Verify output files exist and are valid
- Check file sizes (catch empty/broken builds)
- Validate WebAssembly module loads
- Run smoke tests after build

**Example:**
```
Verifying build...
├─ php-vrzno-web.wasm exists ✓ (7.2 MB)
├─ php-web.data exists ✓ (231 KB)
├─ php-vrzno-web.mjs exists ✓ (262 KB)
└─ WebAssembly module validates ✓

Build verification passed ✓
```

---

### 🟢 13. Differential Packing
**Problem:** Entire app is repacked even for small changes

**Solution:**
- Pack only changed files
- Create delta packages for updates
- Smaller build artifacts
- Faster upload/deploy times

**Benefits:**
- Faster builds
- Efficient network usage
- Better for CI/CD

---

### 🟢 14. Add Build Artifacts Management
**Problem:** Old builds accumulate, no history

**Solution:**
- Version build outputs: `build/v1.2.3/`
- Keep last N builds for rollback
- Clean old builds automatically
- Tag builds with git commit hash

**File Structure:**
```
build/
├─ latest/ -> v1.2.3/
├─ v1.2.3/ (abc123)
├─ v1.2.2/ (def456)
└─ v1.2.1/ (ghi789)
```

---

### 🟢 15. Compression Optimization
**Problem:** `php-web.data` could be smaller

**Solution:**
- Enable LZ4 compression (already available in WebAssembly-Packer)
- Compare compression algorithms (LZ4, Brotli, Gzip)
- Strip unnecessary whitespace
- Remove development-only code in production

**Example:**
```
Before: php-web.data (231 KB)
After:  php-web.data (142 KB) - 38% smaller
```

---

### 🟢 16. Add Pre-build Validation
**Problem:** Invalid code only discovered during compilation

**Solution:**
- Lint PHPX files before compilation
- Check for common syntax errors
- Validate component prop types
- Early failure = faster feedback

**Example:**
```
Validating PHPX files...
├─ Hero.php ✓
├─ CodeDemo.php ⚠️  Unused variable $foo
└─ Features.php ✓

2 warnings, 0 errors
```

---

### 🟢 17. Add `composer wasm:analyze` Command
**Purpose:** Understand the build output

**Features:**
- Show bundle size breakdown
- Identify largest dependencies
- Find unused code
- Optimization suggestions

**Example Output:**
```
Bundle Analysis:
├─ Total size: 231 KB
├─ Largest files:
│  ├─ vendor/syntaxx/phpx-framework (45 KB)
│  ├─ src/components/CodeDemo.php (12 KB)
│  └─ src/components/Hero.php (8 KB)
└─ Suggestions:
   └─ Consider code splitting for CodeDemo.php
```

---

### 🟢 18. Add Build Hooks
**Problem:** No extensibility

**Solution:**
Add hooks in `composer.json`:
```json
{
  "scripts": {
    "pre-wasm": "php scripts/generate-types.php",
    "post-wasm": [
      "php scripts/deploy.php",
      "php scripts/notify.php"
    ]
  }
}
```

**Use Cases:**
- Generate TypeScript definitions
- Deploy to CDN
- Send notifications
- Custom validation

---

### 🟢 19. Improve Error Recovery
**Problem:** Failed build leaves system in broken state

**Solution:**
- Keep previous working build
- Atomic builds (build to temp, then swap)
- Auto-rollback on failure
- Keep `/app` directory in working state

**Implementation:**
```
Build to: /app.tmp/
On success: mv /app.tmp /app
On failure: rm -rf /app.tmp (keep old /app)
```

---

### 🟢 20. Add TypeScript/JSDoc Generation
**Problem:** No IDE autocomplete for PHPX components

**Solution:**
- Generate `.d.ts` files from PHPX components
- Better IDE support
- Type checking for props
- Documentation hints

**Example Generated Type:**
```typescript
interface HeroProps {
  onClick?: () => void;
  variant?: 'primary' | 'secondary';
  children?: React.ReactNode;
}

declare function Hero(props: HeroProps): JSX.Element;
```

---

## Long Term Goals (v2.0+ - 2026)

### 🟣 Full Hot Module Replacement (HMR)
**Priority**: High | **Status**: Research

**Problem:** Full page reload loses application state

**Solution:**
- Component-level hot swapping
- Preserve component state during updates
- Smart diffing and patching
- WebSocket protocol for module updates
- Integration with PHPX component lifecycle

**Features:**
- Update changed components without page reload
- Keep application state (forms, scroll position, etc.)
- Show visual feedback when modules update
- Fallback to full reload on errors

**Example:**
```
🔥 HMR: Updated Hero.php
✓ Hot-swapped without losing state
```

**Dependencies:**
- Requires PHPX-Compiler source maps
- Requires component isolation
- Requires runtime support in PHPX-Framework

---

### 🟣 Static Site Generation (SSG)
**Priority**: Medium | **Status**: Research

**Problem:** PHPX apps require WebAssembly runtime for initial render

**Solution:**
- Pre-render PHPX components at build time
- Generate static HTML files
- Optional hydration for interactivity
- Route-based generation

**Features:**
- `phpx-build generate` command
- Route discovery and static rendering
- Incremental static regeneration (ISR)
- SEO optimization (meta tags, structured data)
- Fast initial page loads

**Example:**
```bash
phpx-build generate --routes=routes.json

Generating static site...
├─ / → public/index.html ✓
├─ /about → public/about/index.html ✓
├─ /blog/post-1 → public/blog/post-1/index.html ✓
└─ /blog/post-2 → public/blog/post-2/index.html ✓

Generated 4 pages in 2.3s
```

**Use Cases:**
- Marketing websites
- Blogs and documentation
- E-commerce product pages
- Landing pages

**Dependencies:**
- Requires PHPX-Compiler server-side rendering support
- Requires PHPX-Framework SSR capabilities
- Requires routing configuration

---

## Implementation Priority

### Phase 1: Developer Experience (Weeks 1-2)
- 🔴 #1: Watch mode with auto-reload (HMR Lite)
- 🔴 #2: Better error handling
- 🔴 #3: Better logging

### Phase 2: Performance (Weeks 3-4)
- 🟡 #4: Incremental compilation
- 🟡 #5: Build caching
- 🟡 #6: Parallel compilation

### Phase 3: Quality & Polish (Weeks 5-6)
- 🟡 #7: Build performance metrics
- 🟡 #8: Dependency graph analysis
- 🟢 #12: Build verification

### Phase 4: Advanced Features (Months 2-3)
- 🟢 #9: Build modes (dev/production)
- 🟢 #10: Source maps integration
- 🟢 #11: Better exclusion configuration
- 🟢 #13-20: Other nice-to-have features

### Phase 5: Long Term (2026+)
- 🟣 Full Hot Module Replacement (HMR)
- 🟣 Static Site Generation (SSG)

---

## Contributing

If you'd like to help implement any of these improvements:

1. Pick an item from the roadmap
2. Open an issue to discuss the approach
3. Submit a pull request with your implementation
4. Update this roadmap with implementation status

---

## Status Legend

### Implementation Status
- 📋 Planned
- 🚧 In Progress
- ✅ Completed
- ❌ Cancelled/Postponed

### Priority Icons
- 🔴 High Priority - Critical for developer experience
- 🟡 Medium Priority - Performance and productivity improvements
- 🟢 Nice to Have - Quality of life improvements
- 🟣 Long Term - Research and advanced features (2026+)

Currently all items are: 📋 Planned

---

## Cross-Project Dependencies

Many BuildTools features depend on capabilities from other PHPX modules:

**PHPX-Compiler Dependencies:**
- Source maps require compiler-level position tracking
- Incremental builds use compiler's AST caching API
- Parallel builds require thread-safe compiler internals

**PHPX-Framework Dependencies:**
- Full HMR requires component lifecycle hooks
- SSG requires server-side rendering support
- State preservation needs framework cooperation

**See Also:**
- [PHPX-Compiler Roadmap](https://github.com/Syntaxx/PHPX-Compiler/blob/main/ROADMAP.md) - Compilation features
- PHPX-Framework Roadmap - Component and runtime features
