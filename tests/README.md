# Testing Fuerte-WP Plugin

## Test Suite Overview

This test suite uses **Pest v4** (modern PHP testing framework built on PHPUnit) to test the Fuerte-WP plugin functionality.

## Test Structure

```
tests/
├── Unit/
│   ├── DeferredUpdatesTest.php      # Tests for deferred updates feature
│   ├── HelpersTest.php               # Tests for helper functions
│   └── ConfigDeferredUpdatesTest.php # Tests for config loading
├── Integration/
│   └── DeferredUpdatesIntegrationTest.php # End-to-end integration tests
├── bootstrap.php                    # PHPUnit bootstrap file
└── wordpress-mocks.php              # WordPress function mocks
```

## Test Coverage

### Deferred Updates Feature Tests

**Unit Tests:**
1. `DeferredUpdatesTest.php` - Tests the auto-update filter logic
   - Excluding deferred plugins from auto-updates
   - Excluding deferred themes from auto-updates
   - Allowing non-deferred items to auto-update
   - Handling empty deferred lists
   - Handling missing config keys
   - Filter registration

2. `HelpersTest.php` - Tests helper functions
   - Getting installed plugins list
   - Getting installed themes list
   - Caching mechanism for both functions

3. `ConfigDeferredUpdatesTest.php` - Tests configuration management
   - Loading deferred plugins from database
   - Loading deferred themes from database
   - Returning empty arrays when no items exist
   - Including deferred updates in enforcer config
   - Handling malformed config gracefully
   - Caching and cache invalidation

**Integration Tests:**
4. `DeferredUpdatesIntegrationTest.php` - Tests complete workflow
   - Complete flow from config to filter execution
   - Handling empty deferred lists in integration
   - Preserving config through cache invalidation

## Installation

Pest is already included in `composer.json` dev dependencies. Simply run:

```bash
composer install
```

## Running Tests

### Run All Tests

```bash
./vendor/bin/pest
```

### Run Specific Test File

```bash
./vendor/bin/pest tests/Unit/DeferredUpdatesTest.php
```

### Run with Coverage

```bash
./vendor/bin/pest --coverage
```

### Run Specific Test

```bash
./vendor/bin/pest --filter it_excludes_deferred_plugins
```

### Run in Verbose Mode

```bash
./vendor/bin/pest --verbose
```

## Test Isolation

Each test is isolated with proper setup and teardown:

- **Setup:** Clears WordPress global state and config cache before each test
- **Teardown:** Restores original state after each test
- **Static Cache:** Helper function caches are cleared between tests

## WordPress Mocks

The test suite includes comprehensive WordPress mocks in `wordpress-mocks.php`:

- **Options API:** `get_option()`, `update_option()`, `delete_option()`
- **Transients API:** `get_transient()`, `set_transient()`, `delete_transient()`
- **Hooks API:** `add_filter()`, `apply_filters()`, `do_action()`
- **User Functions:** `wp_get_current_user()`, `current_user_can()`
- **Plugin/Theme Functions:** `get_plugins()`, `wp_get_themes()`
- **URL Functions:** `admin_url()`, `home_url()`, `get_bloginfo()`
- **i18n Functions:** `__()`, `_e()`, `esc_html()`, `esc_attr()`, `esc_url()`
- **Database:** Mock `wpdb` class

## Writing New Tests

### Test Example (Pest Style)

```php
<?php

use PHPUnit\Framework\TestCase;

class MyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup code here
    }

    protected function tearDown(): void
    {
        // Cleanup code here
        parent::tearDown();
    }

    /** @test */
    public function it_does_something_expected()
    {
        // Arrange
        $input = 'test';

        // Act
        $result = some_function($input);

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Best Practices

1. **Isolation:** Each test should be independent and not rely on other tests
2. **Clear Names:** Use descriptive test names that explain what is being tested
3. **Arrange-Act-Assert:** Follow the AAA pattern for clear test structure
4. **Mock External Dependencies:** Use mocks for WordPress functions and external services
5. **Clean Up:** Always clean up global state in teardown

## CI/CD Integration

Add to your GitHub Actions workflow:

```yaml
- name: Run Tests
  run: |
    cd src/app/plugins/fuerte-wp
    composer install
    ./vendor/bin/pest
```

## Troubleshooting

### Tests Fail with "Class Not Found"

Ensure you've run `composer install` and the autoloader is generated.

### WordPress Functions Undefined

Check that `tests/wordpress-mocks.php` is being loaded in `bootstrap.php`.

### Cache Issues Between Tests

Try running tests with `--exclude-group=integration` to isolate unit tests, or use `--parallel` to run tests in separate processes.

## Contributing Tests

When adding new features:

1. Write tests first (TDD approach) or alongside feature development
2. Ensure all tests pass before committing
3. Aim for high test coverage (aim for 80%+)
4. Document complex test scenarios in comments
5. Update this README if adding new test categories

## Resources

- [Pest Documentation](https://pestphp.com/docs)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Guide](https://make.wordpress.org/core/handbook/testing/automated-testing/)
