# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install      # Install dependencies
composer phpunit      # Run unit tests
composer test         # lint + phpunit
composer format       # Auto-fix PHPCS violations
composer lint         # php-lint + phpcs
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

## Quality gate

**All gates MUST pass before any task is marked complete. No exceptions.**

- `composer format` — auto-fixes PHPCS violations (must run before lint)
- `composer lint` — must exit with zero errors; fix all errors and re-run until clean

If a step fails: fix the issue, then re-run from that step.
