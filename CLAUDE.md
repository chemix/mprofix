# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MProfiX is a PHP-based MySQL log profiler and analyzer that outputs statistics of the most-used queries by reading query log files. It normalizes queries (replacing variable data with `{}`), groups them by pattern, and generates usage statistics.

## Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run the tool
php bin/myprofi [OPTIONS] INPUTFILE
```

## Architecture

The codebase uses the Strategy pattern with pluggable readers, templates, and writers:

- **Entry point**: `bin/myprofi` - CLI argument parsing and orchestration
- **Core engine**: `src/MyProfi/MyProfi.php` - Query processing, normalization, and statistics calculation
- **Reader subsystem** (`src/MyProfi/Reader/`):
  - `IQueryFetcher` interface defines the contract
  - `Extractor` - MySQL general query log
  - `SlowExtractor` - MySQL slow query log (extracts Query Time, Lock Time, Rows Sent, Rows Examined, plus context: thread_id, user, host, timestamp, schema)
  - `CsvReader` / `SlowCsvReader` - CSV format variants
- **Template subsystem** (`src/MyProfi/Template/`):
  - `ITemplate` interface defines the contract
  - `PlainTemplate` - Text output
  - `HtmlTemplate` - HTML table output
  - `DynamicTemplate` - Interactive HTML with JavaScript filtering (requires `-slow` mode)
- **Writer subsystem** (`src/MyProfi/Writer/`):
  - `SqliteWriter` - Exports all query executions to SQLite database (requires `-slow` mode)

## Code Style

- PSR code style guidelines
- PSR-4 autoloading for `MyProfi` namespace
- Requires PHP 8.2+
- Unit tests required for new functionality (PHPUnit 11)
