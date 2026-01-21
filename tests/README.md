# Test Files

This directory contains development and testing utilities for the email tracking system.

## Test Files

### Core Testing
- **test-enrichment.php** - Test contact enrichment using database thread data
- **test-enrichment-custom.php** - CLI test with custom email/subject arguments
- **test-enrichment-web.php** - Full-featured web UI for testing enrichment (552 lines, best dev tool)
- **test-monday-api.php** - Tests Monday.com connection and thread sync
- **test-monday-sync.php** - Tests manual sync workflow
- **test-webhook-with-monday.php** - End-to-end test (webhook → enrichment → Monday)

### Debugging Tools
- **debug-thread-data.php** - Simple data inspector for threads and emails

## Running Tests

All test files should be run from the project root via `php tests/filename.php`:

```bash
# Test enrichment with custom data
php tests/test-enrichment-custom.php john@example.com "Meeting Request"

# Test Monday.com integration
php tests/test-monday-api.php

# View in browser (best UI experience)
php -S localhost:8080 -t .
# Then visit: http://localhost:8080/tests/test-enrichment-web.php
```

## Environment Setup

Ensure `.env` is configured with:
- `PERPLEXITY_API_KEY` - For contact enrichment
- `MONDAY_API_TOKEN` and `MONDAY_BOARD_ID` - For Monday.com sync
- `INTERNAL_DOMAIN` and `INTERNAL_EMAIL` - For domain detection

## Notes

- Test files use `../vendor/autoload.php` for proper autoloading
- Web-based tests should be accessed through a PHP server
- CLI tests output to stdout/stderr for easy debugging
