/**
 * Build script: packages Laravel application files into a .tar for Cloudflare Static Assets.
 *
 * Usage: bun run .cloudflare/build-app.mjs
 *
 * Output: .cloudflare/dist/assets/app.tar
 */

import { readdirSync, statSync, readFileSync, mkdirSync, writeFileSync, copyFileSync, existsSync, rmSync, lstatSync, realpathSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join, relative, resolve } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = resolve(import.meta.dirname, '..');
const DIST_DIR = resolve(import.meta.dirname, 'dist', 'assets');
const OUTPUT = join(DIST_DIR, 'app.tar.gz');

// Directories to include in the tar
const INCLUDE_DIRS = [
  'app',
  'bootstrap',
  'config',
  'database',
  'routes',
  'resources/views',
  'vendor',
];

// Individual files to include
const INCLUDE_FILES = [
  'public/index.php',
  'artisan',
  'composer.json',
];

// Patterns to exclude from the tar
const EXCLUDE_PATTERNS = [
  /\/\.git\//,
  /\/\.github\//,
  /\/node_modules\//,
  /\/\.DS_Store$/,

  // Test directories (case-insensitive check via the regex)
  /\/tests\//i,
  /\/test\//,

  // Documentation and metadata files in vendor
  /\/vendor\/.*\.md$/i,
  /\/vendor\/.*CHANGELOG/i,
  /\/vendor\/.*UPGRADING/i,
  /\/vendor\/.*CONTRIBUTING/i,
  /\/vendor\/.*CODE_OF_CONDUCT/i,
  /\/vendor\/.*SECURITY/i,
  /\/vendor\/.*\.txt$/i,

  // Config/tooling files
  /\/\.editorconfig$/,
  /\/\.gitattributes$/,
  /\/\.gitignore$/,
  /\/\.php-cs-fixer/,
  /\/\.styleci\.yml$/,
  /\/phpunit\.xml/,
  /\/phpstan\.neon/,
  /\/psalm\.xml/,
  /\/pint\.json$/,
  /\/rector\.php$/,

  // Documentation directories in vendor
  /\/vendor\/.*\/docs?\//i,
  /\/vendor\/.*\/examples?\//i,

  // Bin scripts (not needed at runtime)
  /\/vendor\/bin\//,

  // Carbon locale files — 3.3 MB for 824 locales, keep only English
  /\/vendor\/nesbot\/carbon\/src\/Carbon\/Lang\/(?!en[._]|en\.php)/,

  // laravel/tinker, psy/psysh, nikic/php-parser are in require-dev and
  // excluded by `composer install --no-dev` during the build.
];

/**
 * Collect all files from a directory recursively.
 */
function collectFiles(dir, basePath = '') {
  const results = [];
  const entries = readdirSync(dir, { withFileTypes: true });

  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    const relPath = basePath ? `${basePath}/${entry.name}` : entry.name;

    if (EXCLUDE_PATTERNS.some(p => p.test('/' + relPath))) {
      continue;
    }

    // Follow symlinks by checking the real path
    const isSymlink = entry.isSymbolicLink();
    const realPath = isSymlink ? realpathSync(fullPath) : fullPath;
    const realStat = isSymlink ? statSync(realPath) : null;

    if (entry.isDirectory() || (isSymlink && realStat?.isDirectory())) {
      // Add directory entry
      results.push({ path: relPath + '/', isDir: true });
      results.push(...collectFiles(realPath, relPath));
    } else if (entry.isFile() || (isSymlink && realStat?.isFile())) {
      results.push({ path: relPath, isDir: false, fullPath: realPath });
    }
  }

  return results;
}

/**
 * Create a 512-byte tar header for a file or directory.
 */
function createTarHeader(path, size, isDir) {
  const header = new Uint8Array(512);
  const encoder = new TextEncoder();

  // Handle long paths with ustar prefix
  let name = path;
  let ustarPrefix = '';

  if (name.length > 100) {
    const splitAt = name.lastIndexOf('/', 155);
    if (splitAt > 0) {
      ustarPrefix = name.substring(0, splitAt);
      name = name.substring(splitAt + 1);
    }
  }

  // Name (0-99)
  header.set(encoder.encode(name.substring(0, 100)), 0);

  // Mode (100-107) - 0777 for dirs, 0666 for files (MEMFS — no real permissions)
  const mode = isDir ? '0000777' : '0000666';
  header.set(encoder.encode(mode + '\0'), 100);

  // UID (108-115) - use 0 (root) to match Emscripten MEMFS default uid
  header.set(encoder.encode('0000000\0'), 108);

  // GID (116-123)
  header.set(encoder.encode('0000000\0'), 116);

  // Size (124-135) - octal, 11 digits
  const sizeStr = size.toString(8).padStart(11, '0');
  header.set(encoder.encode(sizeStr + '\0'), 124);

  // Mtime (136-147)
  const mtime = Math.floor(Date.now() / 1000).toString(8).padStart(11, '0');
  header.set(encoder.encode(mtime + '\0'), 136);

  // Checksum placeholder (148-155) - spaces
  header.set(encoder.encode('        '), 148);

  // Type flag (156): '0' for file, '5' for directory
  header[156] = isDir ? 53 : 48;

  // USTAR magic (257-262)
  header.set(encoder.encode('ustar\0'), 257);

  // USTAR version (263-264)
  header.set(encoder.encode('00'), 263);

  // USTAR prefix (345-499)
  if (ustarPrefix) {
    header.set(encoder.encode(ustarPrefix.substring(0, 155)), 345);
  }

  // Calculate and write checksum
  let checksum = 0;
  for (let i = 0; i < 512; i++) {
    checksum += header[i];
  }
  const checksumStr = checksum.toString(8).padStart(6, '0') + '\0 ';
  header.set(encoder.encode(checksumStr), 148);

  return header;
}

/**
 * Create a tar archive from collected files.
 */
function createTar(files) {
  const chunks = [];

  for (const file of files) {
    if (file.isDir) {
      chunks.push(createTarHeader(file.path, 0, true));
    } else {
      const content = readFileSync(file.fullPath);
      chunks.push(createTarHeader(file.path, content.length, false));
      chunks.push(new Uint8Array(content));

      // Pad to 512-byte boundary
      const remainder = content.length % 512;
      if (remainder > 0) {
        chunks.push(new Uint8Array(512 - remainder));
      }
    }
  }

  // End-of-archive marker (two 512-byte zero blocks)
  chunks.push(new Uint8Array(1024));

  // Concatenate all chunks
  const totalSize = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
  const result = new Uint8Array(totalSize);
  let offset = 0;
  for (const chunk of chunks) {
    result.set(chunk, offset);
    offset += chunk.length;
  }

  return result;
}

// --- Main ---

// Clean previous build
if (existsSync(DIST_DIR)) {
  rmSync(DIST_DIR, { recursive: true });
}

console.log('Building Laravel app tar...');

// --- Production preparation ---
// 1. Install production-only dependencies (strips dev packages)
// 2. Cache config/routes/views with production .env
// 3. Clean up after tar creation

const envFile = join(ROOT, '.env');
const envBackup = join(ROOT, '.env.build-backup');
const envProd = join(import.meta.dirname, '.env.production');
let needsRestore = false;

try {

// Back up .env and use production env for caching
if (existsSync(envFile)) {
  copyFileSync(envFile, envBackup);
}
if (existsSync(envProd)) {
  copyFileSync(envProd, envFile);
}
needsRestore = true;

// Strip dev dependencies — saves ~30MB from vendor/
console.log('  Installing production dependencies...');
execSync('composer install --no-dev --optimize-autoloader --no-interaction --quiet', {
  cwd: ROOT,
  stdio: 'pipe',
  timeout: 120_000,
});

// Pre-cache Laravel config, routes, and views for faster boot
console.log('  Caching config, routes, and views...');
const cacheCommands = [
  'php artisan config:cache --no-interaction',
  'php artisan route:cache --no-interaction',
  'php artisan view:cache --no-interaction',
];
for (const cmd of cacheCommands) {
  try {
    execSync(cmd, { cwd: ROOT, stdio: 'pipe', timeout: 30_000 });
    console.log(`    ${cmd.replace('php artisan ', '').replace(' --no-interaction', '')} ✓`);
  } catch (err) {
    console.warn(`    Warning: ${cmd} failed: ${err.stderr?.toString().trim() || err.message}`);
  }
}

// Fix absolute paths in cached files — config:cache and route:cache bake in
// the local machine's absolute paths, but in WASM MEMFS the app lives at /app.
const cacheDir = join(ROOT, 'bootstrap', 'cache');
const cacheFiles = ['config.php', 'routes-v7.php'];
for (const file of cacheFiles) {
  const cachePath = join(cacheDir, file);
  if (existsSync(cachePath)) {
    let content = readFileSync(cachePath, 'utf8');
    const replaced = content.replaceAll(ROOT, '/app');
    if (replaced !== content) {
      writeFileSync(cachePath, replaced);
      console.log(`    Fixed paths in ${file}`);
    }
  }
}

// Fix compiled view paths — view:cache compiles Blade templates with absolute
// paths embedded in comments. These are used for error reporting.
const compiledViewsDir = join(ROOT, 'storage', 'framework', 'views');
if (existsSync(compiledViewsDir)) {
  const viewFiles = readdirSync(compiledViewsDir).filter(f => f.endsWith('.php'));
  let fixedCount = 0;
  for (const file of viewFiles) {
    const viewPath = join(compiledViewsDir, file);
    let content = readFileSync(viewPath, 'utf8');
    const replaced = content.replaceAll(ROOT, '/app');
    if (replaced !== content) {
      writeFileSync(viewPath, replaced);
      fixedCount++;
    }
  }
  if (fixedCount > 0) {
    console.log(`    Fixed paths in ${fixedCount} compiled views`);
  }
}

const allFiles = [];

// Collect directories
for (const dir of INCLUDE_DIRS) {
  const fullDir = join(ROOT, dir);
  if (existsSync(fullDir)) {
    allFiles.push({ path: dir + '/', isDir: true });
    allFiles.push(...collectFiles(fullDir, dir));
  } else {
    console.warn(`  Warning: ${dir} not found, skipping`);
  }
}

// Collect individual files
for (const file of INCLUDE_FILES) {
  const fullPath = join(ROOT, file);
  if (existsSync(fullPath)) {
    // Ensure parent directory entries exist
    const parts = file.split('/');
    if (parts.length > 1) {
      let dirPath = '';
      for (let i = 0; i < parts.length - 1; i++) {
        dirPath += (dirPath ? '/' : '') + parts[i];
        if (!allFiles.some(f => f.path === dirPath + '/')) {
          allFiles.push({ path: dirPath + '/', isDir: true });
        }
      }
    }
    allFiles.push({ path: file, isDir: false, fullPath });
  } else {
    console.warn(`  Warning: ${file} not found, skipping`);
  }
}

// Copy .env.production as .env
const envSource = join(import.meta.dirname, '.env.production');
if (existsSync(envSource)) {
  allFiles.push({ path: '.env', isDir: false, fullPath: envSource });
} else {
  console.warn('  Warning: .cloudflare/.env.production not found, no .env will be included');
}

// Add empty storage directory structure
const storageDirs = [
  'storage/',
  'storage/app/',
  'storage/framework/',
  'storage/framework/cache/',
  'storage/framework/cache/data/',
  'storage/framework/sessions/',
  'storage/framework/testing/',
  'storage/framework/views/',
  'storage/logs/',
];

for (const dir of storageDirs) {
  if (!allFiles.some(f => f.path === dir)) {
    allFiles.push({ path: dir, isDir: true });
  }
}

// Override Composer platform check — php-cgi-wasm provides PHP 8.3.11 but
// Laravel 12 requires >= 8.4.0. The WASM PHP works fine, just skip the check.
const platformCheckPath = 'vendor/composer/platform_check.php';
const idx = allFiles.findIndex(f => f.path === platformCheckPath);
if (idx >= 0) {
  // Replace with a no-op PHP file by creating a temp file
  const tmpFile = join(DIST_DIR, '__platform_check_noop.php');
  mkdirSync(DIST_DIR, { recursive: true });
  writeFileSync(tmpFile, '<?php\n// Platform check disabled for WASM runtime\n');
  allFiles[idx] = { path: platformCheckPath, isDir: false, fullPath: tmpFile };
  console.log('  Disabled Composer platform check');
}

console.log(`  Collected ${allFiles.length} entries`);

const tar = createTar(allFiles);
const tarSizeMB = (tar.length / 1024 / 1024).toFixed(2);
console.log(`  Tar size: ${tarSizeMB} MB (uncompressed)`);

// Gzip the tar
const gzipped = gzipSync(tar, { level: 9 });

// Write output
mkdirSync(DIST_DIR, { recursive: true });
writeFileSync(OUTPUT, gzipped);

const sizeMB = (gzipped.length / 1024 / 1024).toFixed(2);
console.log(`  Created ${OUTPUT} (${sizeMB} MB compressed)`);

} finally {
  // Restore dev dependencies and original .env
  if (needsRestore) {
    console.log('  Restoring dev dependencies...');
    if (existsSync(envBackup)) {
      copyFileSync(envBackup, envFile);
      rmSync(envBackup);
    }
    // Clear production caches so dev environment isn't affected
    try {
      execSync('php artisan config:clear --no-interaction', { cwd: ROOT, stdio: 'pipe' });
      execSync('php artisan route:clear --no-interaction', { cwd: ROOT, stdio: 'pipe' });
      execSync('php artisan view:clear --no-interaction', { cwd: ROOT, stdio: 'pipe' });
    } catch { /* ignore cleanup errors */ }
    execSync('composer install --no-interaction --quiet', {
      cwd: ROOT,
      stdio: 'pipe',
      timeout: 120_000,
    });
  }
}

// Copy Vite build assets if they exist
const viteBuildDir = join(ROOT, 'public', 'build');
if (existsSync(viteBuildDir)) {
  console.log('  Copying Vite build assets...');
  copyDirRecursive(viteBuildDir, join(DIST_DIR, 'build'));
  console.log('  Done.');
}

// Patch the Emscripten PHP module for Cloudflare Workers compatibility.
// The module uses `new URL("...wasm", import.meta.url)` to locate the WASM binary,
// which fails in workerd because import.meta.url is not a valid URL.
// We patch it to use a try/catch fallback so the locateFile callback works properly.
console.log('  Patching PHP module for Workers compatibility...');
const phpModuleSrc = join(ROOT, 'node_modules', 'php-cgi-wasm', 'php-cgi-web.mjs');
const phpModuleDest = join(import.meta.dirname, 'php-cgi.mjs');
let phpModule = readFileSync(phpModuleSrc, 'utf8');

// Patch 1: Replace `new URL("...wasm", import.meta.url).href` with a try/catch
// fallback. In Workers, import.meta.url is not a valid URL base.
phpModule = phpModule.replace(
  /new URL\("([^"]+\.wasm)",\s*import\.meta\.url\)\.href/g,
  '(() => { try { return new URL("$1", import.meta.url).href; } catch { return "$1"; } })()'
);

// Patch 2: Intercept dynamic library loading to use pre-provided WebAssembly.Module
// objects instead of fetch(). In Workers, WebAssembly.compile(bytes) is blocked
// outside request handlers, but pre-compiled Modules (from .wasm imports) work.
// loadWebAssemblyModule already checks `binary instanceof WebAssembly.Module`.
phpModule = phpModule.replace(
  /var readAsync,readBinary;/,
  'var readAsync,readBinary;var __preloadedLibs=Module["_preloadedLibs"]||{};'
);
// In loadLibData, return the pre-compiled Module directly if available
phpModule = phpModule.replace(
  'var libFile=locateFile(libName);if(flags.loadAsync)',
  'if(__preloadedLibs[libName]){var _m=__preloadedLibs[libName];return flags.loadAsync?Promise.resolve(_m):_m}var libFile=locateFile(libName);if(flags.loadAsync)'
);

// Patch 3: Add error recovery to loadDylibs so unhandled rejections
// in reportUndefinedSymbols don't leave the run dependency dangling.
phpModule = phpModule.replace(
  'dynamicLibraries.reduce((chain,lib)=>chain.then(()=>loadDynamicLibrary(lib,{loadAsync:true,global:true,nodelete:true,allowUndefined:true})),Promise.resolve()).then(()=>{reportUndefinedSymbols();removeRunDependency("loadDylibs")})',
  'dynamicLibraries.reduce((chain,lib)=>chain.then(()=>loadDynamicLibrary(lib,{loadAsync:true,global:true,nodelete:true,allowUndefined:true})),Promise.resolve()).then(()=>{reportUndefinedSymbols();removeRunDependency("loadDylibs")}).catch(e=>{console.error("loadDylibs error:",e);removeRunDependency("loadDylibs")})'
);

// Patch 4: Patch reportUndefinedSymbols to catch CompileError from addFunction.
// Workers blocks runtime WASM compilation needed by convertJsFunctionToWasm.
// The unresolved symbols are optional (PHP works without them).
phpModule = phpModule.replace(
  'entry.value=addFunction(value,value.sig)',
  'try{entry.value=addFunction(value,value.sig)}catch(_e){if(!(_e instanceof WebAssembly.CompileError))throw _e}'
);

writeFileSync(phpModuleDest, phpModule);
console.log(`  Patched ${phpModuleDest}`);

// Copy shared library .so files as .wasm so Cloudflare pre-compiles them.
// This avoids runtime WebAssembly.compile() which is blocked outside request handlers.
const soFiles = [
  { src: join(ROOT, 'node_modules', 'php-cgi-wasm', 'libxml2.so'), dest: 'libxml2.wasm' },
  { src: join(ROOT, 'node_modules', 'php-wasm-mbstring', 'libonig.so'), dest: 'libonig.wasm' },
  { src: join(ROOT, 'node_modules', 'php-wasm-mbstring', 'php8.3-mbstring.so'), dest: 'php8.3-mbstring.wasm' },
  { src: join(ROOT, 'node_modules', 'php-wasm-openssl', 'libcrypto.so'), dest: 'libcrypto.wasm' },
  { src: join(ROOT, 'node_modules', 'php-wasm-openssl', 'libssl.so'), dest: 'libssl.wasm' },
  { src: join(ROOT, 'node_modules', 'php-wasm-openssl', 'php8.3-openssl.so'), dest: 'php8.3-openssl.wasm' },
];

for (const { src, dest } of soFiles) {
  const destPath = join(import.meta.dirname, dest);
  if (existsSync(src)) {
    copyFileSync(src, destPath);
    console.log(`  Copied ${src.split('/').pop()} → ${dest}`);
  } else {
    console.warn(`  Warning: ${src} not found`);
  }
}

// Optimize WASM files with wasm-opt (binaryen) for size reduction.
// Uses -Oz (aggressive size optimization) and strips debug info.
const wasmOptBin = join(ROOT, 'node_modules', '.bin', 'wasm-opt');
if (existsSync(wasmOptBin)) {
  console.log('Optimizing WASM files with wasm-opt...');
  // Skip the custom PHP WASM binary — it's already optimized by Emscripten -Oz
  // and binaryen 125+ can introduce incompatible heap types (exact/custom-descriptors).
  const wasmFiles = readdirSync(import.meta.dirname).filter(
    f => f.endsWith('.wasm') && f !== 'php-cgi-worker.mjs.wasm'
  );

  for (const wasmFile of wasmFiles) {
    const wasmPath = join(import.meta.dirname, wasmFile);
    const sizeBefore = statSync(wasmPath).size;
    try {
      execSync(`${wasmOptBin} -Oz --strip-debug -o ${wasmPath} ${wasmPath}`, {
        stdio: 'pipe',
        timeout: 120_000,
      });
      const sizeAfter = statSync(wasmPath).size;
      const savedPct = ((1 - sizeAfter / sizeBefore) * 100).toFixed(1);
      console.log(`  ${wasmFile}: ${(sizeBefore / 1024).toFixed(0)} KiB → ${(sizeAfter / 1024).toFixed(0)} KiB (−${savedPct}%)`);
    } catch (err) {
      console.warn(`  Warning: wasm-opt failed for ${wasmFile}: ${err.message}`);
    }
  }
} else {
  console.log('  Skipping WASM optimization (binaryen not installed). Run: bun add -d binaryen');
}

console.log('Build complete.');

function copyDirRecursive(src, dest) {
  mkdirSync(dest, { recursive: true });
  const entries = readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = join(src, entry.name);
    const destPath = join(dest, entry.name);
    if (entry.isDirectory()) {
      copyDirRecursive(srcPath, destPath);
    } else {
      copyFileSync(srcPath, destPath);
    }
  }
}
