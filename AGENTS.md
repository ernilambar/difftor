# AGENTS.md

## Overview

Difftor is a PHP CLI tool that compares two sources (URLs, directories, or zip files) and generates an HTML diff file. Built with PHP 8.3+, Symfony Console, and jfcherng/php-diff.

## Setup

```bash
composer install
```

## Commands

```bash
composer install      # Install dependencies
composer phpunit      # Run PHPUnit tests
composer lint         # Run PHP lint + PHPCS
composer format       # Auto-fix PHPCS violations with PHPCBF
composer test         # Run lint + phpunit
```

Run a single test file:

```bash
vendor/bin/phpunit tests/Unit/FileUtilsTest.php
```

## Architecture

**Entry point:** `bin/difftor` → bootstraps Symfony Console app → runs `DifftorCommand`.

**Request flow:**

```
DifftorCommand (Console)
  └── DifftorService::generateDiff()
        ├── PathUtils::normalizePath / isUrl / isLocalDirectory / isLocalZip
        ├── ZipUtils::downloadAndExtractZip / extractLocalZip  (temp dirs)
        ├── FileUtils::getDirectoryFiles / shouldIgnoreFile / readFileAsUtf8
        ├── jfcherng/php-diff DiffHelper::calculate()  (inline HTML diff)
        └── HtmlUtils::buildHtmlDocument / generateFileId  (final HTML output)
```

**Key behaviors:**
- Binary/font/media extensions are skipped (hardcoded list in `DifftorService::$ignored_extensions`).
- Folder renames are auto-detected: if only the first path component differs between a removed and added file, they are treated as a rename and diffed together.
- Temp directories created for URL downloads and zip extractions are cleaned up after diff generation.
- Output is a single self-contained HTML file saved to system temp dir (or `--output-dir`).

## Conventions

- **Tab indentation** for PHP files (not spaces). Configure your editor accordingly.
- **PHP 8.3+ compatibility** — code must run on PHP 8.3 and later.
- **PSR-12** coding standard with custom modifications (see `phpcs.xml.dist`).
- **Short array syntax** only (`[]` not `array()`).
- **Alphabetically sorted use statements** — no grouped use declarations.
- **PSR-4 autoloading** — namespace `Nilambar\Difftor\` maps to `src/`.

## Quality Gate

All gates MUST pass (exit code 0) before declaring a task complete:

1. `composer format` — auto-fix code style violations
2. `composer lint` — verify no lint or PHPCS errors remain
3. `composer phpunit` — verify all tests pass

If any step fails, fix the issue and re-run from that step.
