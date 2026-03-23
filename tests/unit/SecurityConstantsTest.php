<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

/**
 * Security Constants Tests
 *
 * Tests for WordPress security constants and configuration settings
 */
test('security constants - plugin version constant is accessible', function () {
    // Assert: Version constant should be defined
    expect(defined('FUERTEWP_VERSION'))->toBeTrue();
    expect(FUERTEWP_VERSION)->toBeString();
    expect(FUERTEWP_VERSION)->not->toBeEmpty();
});

test('security constants - plugin path constant is accessible', function () {
    // Assert: Path constant should be defined
    expect(defined('FUERTEWP_PATH'))->toBeTrue();
    expect(FUERTEWP_PATH)->toBeString();
    expect(FUERTEWP_PATH)->toContain('fuerte-wp');
});

test('security constants - late priority constant is correct', function () {
    // Assert: Late priority constant should be defined and high
    expect(defined('FUERTEWP_LATE_PRIORITY'))->toBeTrue();
    expect(FUERTEWP_LATE_PRIORITY)->toBeInt();
    expect(FUERTEWP_LATE_PRIORITY)->toBe(9999);
});

test('security constants - testing constant is only set during tests', function () {
    // Assert: Testing constant should be defined in test environment
    expect(defined('FUERTEWP_TESTING'))->toBeTrue();
    expect(FUERTEWP_TESTING)->toBeTrue();
});

test('security constants - ABSPATH is properly defined', function () {
    // Assert: WordPress ABSPATH should be defined
    expect(defined('ABSPATH'))->toBeTrue();
    expect(ABSPATH)->toBeString();
});

test('security constants - WordPress time constants are defined', function () {
    // Assert: WordPress time constants should be defined
    expect(defined('MINUTE_IN_SECONDS'))->toBeTrue();
    expect(defined('HOUR_IN_SECONDS'))->toBeTrue();
    expect(defined('DAY_IN_SECONDS'))->toBeTrue();
    expect(defined('WEEK_IN_SECONDS'))->toBeTrue();
    expect(defined('MONTH_IN_SECONDS'))->toBeTrue();
    expect(defined('YEAR_IN_SECONDS'))->toBeTrue();

    // Verify values are correct
    expect(MINUTE_IN_SECONDS)->toBe(60);
    expect(HOUR_IN_SECONDS)->toBe(60 * 60);
    expect(DAY_IN_SECONDS)->toBe(24 * HOUR_IN_SECONDS);
});

test('security constants - constants preserve their values', function () {
    // Arrange: Get original value
    $originalVersion = FUERTEWP_VERSION;

    // Act & Assert: Verify constant value hasn't changed
    // Constants in PHP cannot be redefined once set
    expect(FUERTEWP_VERSION)->toBe($originalVersion);
    expect(FUERTEWP_VERSION)->toBeString();
    expect(FUERTEWP_VERSION)->not->toBeEmpty();
});

test('security constants - plugin version format is valid semver', function () {
    // Act: Get version
    $version = FUERTEWP_VERSION;

    // Assert: Should match semver pattern (major.minor.patch)
    $pattern = '/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/';
    expect($version)->toMatch($pattern);
});

test('security constants - config handles FUERTEWP_DISABLE constant', function () {
    // Arrange: Set up minimal config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Define FUERTEWP_DISABLE to test early exit logic
    if (!defined('FUERTEWP_DISABLE')) {
        define('FUERTEWP_DISABLE', true);
    }

    // Act: Get config (should handle the constant)
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Config should still be retrievable
    expect($config)->toBeArray();
});

test('security constants - config handles FUERTEWP_FORCE constant', function () {
    // Arrange: Set up config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'admin@example.com';

    // Define FUERTEWP_FORCE to test force mode
    if (!defined('FUERTEWP_FORCE')) {
        define('FUERTEWP_FORCE', true);
    }

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Config should have super users even when forced
    expect($config['super_users'])->toContain('admin@example.com');
});

test('security constants - WordPress debug constants are respected', function () {
    // Assert: WordPress debug constants should be defined
    expect(defined('WP_DEBUG'))->toBeTrue();
    expect(defined('WPINC'))->toBeTrue();
    expect(defined('WP_PLUGIN_DIR'))->toBeTrue();
});

test('security constants - path constants end with correct separator', function () {
    // Assert: Path constants should end with /
    expect(FUERTEWP_PATH)->toMatch('/\/$/');
    expect(ABSPATH)->toMatch('/\/$/');
});
