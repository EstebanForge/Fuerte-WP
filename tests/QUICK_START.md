# Quick Start: Testing Fuerte-WP

## Prerequisites

- PHP 8.1 or higher
- Composer installed
- Git

## Setup (One-time)

1. Navigate to the plugin directory:
```bash
cd src/app/plugins/fuerte-wp
```

2. Install dependencies including testing tools:
```bash
composer install
```

## Running Tests

### Run all tests:
```bash
composer test
```

Or directly:
```bash
./vendor/bin/pest
```

### Run specific test file:
```bash
./vendor/bin/pest tests/Unit/DeferredUpdatesTest.php
```

### Run with coverage report:
```bash
composer test:coverage
```

### Run a specific test by name:
```bash
./vendor/bin/pest --filter it_excludes_deferred_plugins
```

## Test Commands Reference

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests |
| `composer test:coverage` | Run tests with coverage |
| `./vendor/bin/pest` | Run all tests (direct) |
| `./vendor/bin/pest --verbose` | Run with detailed output |
| `./vendor/bin/pest --parallel` | Run tests in parallel |
| `./vendor/bin/pest --fail-on-warning` | Treat warnings as failures |

## Writing New Tests

1. Create a new test file in `tests/Unit/` or `tests/Integration/`
2. Extend `PHPUnit\Framework\TestCase`
3. Use the `/** @test */` annotation for test methods
4. Follow Arrange-Act-Assert pattern

## Debugging Failed Tests

1. Run with verbose output:
```bash
./vendor/bin/pest --verbose
```

2. Run a single test file:
```bash
./vendor/bin/pest tests/Unit/YourTest.php
```

3. Stop on first failure:
```bash
./vendor/bin/pest --stop-on-failure
```

## Common Issues

**Issue:** "Class not found" error
**Solution:** Run `composer install` to regenerate autoloader

**Issue:** Tests pass locally but fail in CI
**Solution:** Ensure you're using the same PHP version as CI (8.2)

**Issue:** WordPress functions undefined
**Solution:** Ensure `tests/wordpress-mocks.php` is loaded in `tests/bootstrap.php`

## Test Structure

```
tests/
├── Unit/                    # Unit tests (isolated components)
│   ├── DeferredUpdatesTest.php
│   ├── HelpersTest.php
│   └── ConfigDeferredUpdatesTest.php
├── Integration/             # Integration tests (component interaction)
│   └── DeferredUpdatesIntegrationTest.php
├── bootstrap.php           # Test setup
├── wordpress-mocks.php     # WordPress function mocks
├── README.md               # Detailed testing documentation
└── QUICK_START.md          # This file
```

## Next Steps

- Read the full [README.md](tests/README.md) for detailed documentation
- Check existing tests for examples
- Write tests for new features as you develop them
