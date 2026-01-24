# AGENTS.md - Meting-API

## Project Overview
Meting-API is a PHP-based music API wrapper that provides APlayer-compatible endpoints for multiple music sources (Netease, Tencent). It uses the `metowolf/meting` library as its core music framework.

## Build/Lint/Test Commands

### Installation
```bash
# Install dependencies
composer install

# Use Chinese mirror if needed
composer config -g repo.packagist composer https://packagist.phpcomposer.com
composer install
```

### Code Quality
```bash
# Check PHP syntax (requires PHP installed)
php -l index.php
php -l src/Meting.php
php -l src/QrcDecode.php

# Validate all PHP files
find . -name "*.php" -exec php -l {} \;
```

### Testing
**No test framework is currently configured.** This project does not use PHPUnit, Pest, or other testing frameworks. To run a single test:
1. Create a test file (e.g., `tests/YourTest.php`)
2. Run: `php tests/YourTest.php`

### Development Server
```bash
# Start PHP built-in server (requires PHP)
php -S localhost:8000 index.php
```

## Code Style Guidelines

### PHP Version
- Minimum PHP 5.4+ (as per README)
- Uses PHP 5.4+ features (scalar type hints, short array syntax)

### Formatting
- **Indentation**: 4 spaces (per `.editorconfig`)
- **Line endings**: LF
- **File encoding**: UTF-8
- **Final newline**: Required for PHP files
- **Trailing whitespace**: Allowed (per `.editorconfig`)

### Imports & Namespaces
- Use namespaces for classes: `namespace Metowolf;`, `namespace QrcDecode;`
- Include dependencies with `require`/`include` at file top
- Use `use` statements for class imports
- Example from `src/Meting.php:13-17`:
  ```php
  namespace Metowolf;
  include __DIR__ . '/QrcDecode.php';
  use QrcDecode\Decoder;
  ```

### Naming Conventions
- **Classes**: PascalCase (e.g., `Meting`, `Decoder`)
- **Methods**: camelCase (e.g., `dwrc()`, `bakdwrc()`, `curlset()`)
- **Variables**: snake_case (e.g., `$tencent_cookie`, `$lrc_url`)
- **Constants**: UPPER_CASE (e.g., `API_URI`, `TLYRIC`, `CACHE`)
- **Private properties**: snake_case (e.g., `$temp`, `$en_mode`)

### Type Declarations
- **No type hints** in function parameters (PHP 5.4 compatible)
- **No return types** (PHP 5.4 compatible)
- Use `@param` and `@return` docblocks for documentation

### Error Handling
- Use `exit` for fatal errors (e.g., `index.php:115`)
- Check `json_last_error()` after `json_decode()` (e.g., `index.php:158`)
- Validate input with `filter_input()` (e.g., `index.php:20-27`)
- Check file existence with `file_exists()` (e.g., `index.php:64`)
- Return HTTP status codes: `http_response_code(403)` (e.g., `index.php:34`)

### Security Practices
- **Input sanitization**: Use `FILTER_SANITIZE_SPECIAL_CHARS` (e.g., `index.php:20-27`)
- **Authentication**: HMAC-SHA1 with secret key (e.g., `index.php:239-242`)
- **CORS**: Explicitly set `Access-Control-Allow-Origin: *` (e.g., `index.php:48`)
- **Access control**: Check `defined('METING_API')` in included files (e.g., `src/QMCookie.php:1`)
- **Secrets**: Never hardcode secrets; use environment variables or config files

### String & URL Handling
- **URL construction**: Use `API_URI` constant (e.g., `index.php:121`)
- **HTTPS enforcement**: Convert `http://` to `https://` (e.g., `index.php:261-267`)
- **Query building**: Use `http_build_query()` (e.g., `src/Meting.php:91`)
- **String checks**: Use `strpos()` for URL validation (e.g., `index.php:263`)

### JSON Handling
- **Decode**: `json_decode($data)` or `json_decode($data, true)` for arrays
- **Encode**: `json_encode($array, JSON_UNESCAPED_UNICODE)` for UTF-8 (e.g., `index.php:181`)
- **Error checking**: Always check `json_last_error()` (e.g., `index.php:158`)

### Array Operations
- **Short syntax**: Use `[]` not `array()` (e.g., `index.php:119`)
- **Implode**: Use `implode('/', $array)` for joining (e.g., `index.php:128`)
- **Array access**: Use object notation for JSON objects (e.g., `$song->name`)

### Configuration & Constants
- **Define constants** at file top (e.g., `index.php:1-13`)
- **Boolean flags**: Use `true`/`false` (e.g., `TLYRIC`, `CACHE`, `AUTH`)
- **Default values**: Use ternary operators (e.g., `$server = $_GET['server'] ?: 'netease'`)

### Caching
- **File cache**: Check file existence and age (e.g., `index.php:103-110`)
- **APCu cache**: Use `apcu_exists()`, `apcu_fetch()`, `apcu_store()` (e.g., `index.php:192-223`)
- **Cache keys**: Concatenate server, type, and ID (e.g., `$server . $type . $id`)

### Common Patterns
1. **Input validation chain**:
   ```php
   $server = filter_input(INPUT_GET, 'server', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'netease';
   $type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
   $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
   ```

2. **Conditional execution**:
   ```php
   if ($type == 'playlist') {
       // playlist logic
   } else if ($type == 'search') {
       // search logic
   } else {
       // other logic
   }
   ```

3. **Early exit pattern**:
   ```php
   if (!isset($_GET['type']) || !isset($_GET['id'])) {
       include __DIR__ . '/public/index.php';
       exit;
   }
   ```

4. **Function-based organization**:
   - Helper functions at file bottom (e.g., `api_uri()`, `auth()`, `song2data()`, `return_data()`)
   - Use `exit` in functions to stop execution

### File Structure
```
meting-api/
├── index.php           # Main entry point (342 lines)
├── src/
│   ├── Meting.php      # Core music API class (namespace Metowolf)
│   ├── QrcDecode.php   # QRC decoder (namespace QrcDecode)
│   └── QMCookie.php    # Tencent cookie config
├── public/
│   └── index.php       # Demo page
├── docs/
│   └── index.html      # APlayer demo
├── cache/              # Playlist cache directory
├── composer.json       # Dependencies (metowolf/meting)
└── .editorconfig       # Editor configuration
```

### Key Files & Line References
- **Main entry**: `index.php:1-342` - API routing and logic
- **Meting class**: `src/Meting.php:19-100` - Core API methods
- **Configuration**: `index.php:1-13` - Constants setup
- **Auth function**: `index.php:239-242` - HMAC-SHA1 authentication
- **Data processing**: `index.php:244-330` - `song2data()` function

### Dependencies
- **metowolf/meting** (^1.5) - Core music API library
- **PHP extensions**: BCMath, Curl, OpenSSL (per README)

### Notes for Agents
- This is a **legacy PHP project** (PHP 5.4+ compatible)
- **No type safety** - use runtime checks
- **No framework** - pure PHP with composer for dependency management
- **No tests** - add tests if needed
- **No CI/CD** - manual deployment
- **Cache directory** (`cache/`) must be writable if caching is enabled
- **QMCookie.php** is optional - provides Tencent cookie support
- **APCu cache** requires APCu extension installed
- **Security**: Always validate and sanitize user input
- **Error handling**: Use `exit` for fatal errors, set HTTP status codes
- **CORS**: API is designed for browser consumption, CORS headers are set
- **Redirects**: URL and pic types return 302 redirects (e.g., `index.php:335`)
