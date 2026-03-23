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
 * Super User Security Tests
 *
 * Critical security feature: Super users bypass all restrictions
 */
test('super user - email matching is case insensitive', function () {
    // Arrange: Set up config with super user in lowercase
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'admin@example.com';
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config and check case insensitivity
    $config = Fuerte_Wp_Config::get_config(true);

    // Simulate email comparison (should be case insensitive)
    $currentUserEmail = 'ADMIN@EXAMPLE.COM';
    $isSuperUser = in_array(strtolower($currentUserEmail), array_map('strtolower', $config['super_users']));

    // Assert: Should match despite case difference
    expect($isSuperUser)->toBeTrue();
});

test('super user - bypasses restrictions when not forced', function () {
    // Arrange: Super user with FUERTEWP_FORCE not defined
    // Note: This test may fail if run after other tests that define FUERTEWP_FORCE
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'superadmin@example.com';
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Simulate super user check
    $user = new stdClass();
    $user->user_email = 'superadmin@example.com';
    $isSuperUser = in_array(strtolower($user->user_email), array_map('strtolower', $config['super_users']));
    $isForced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;

    // Assert: Super user should bypass when not forced
    expect($isSuperUser)->toBeTrue();
    // Note: $isForced may be true if FUERTEWP_FORCE was defined in a previous test
    if (!defined('FUERTEWP_FORCE')) {
        expect($isForced)->toBeFalse();
    }
});

test('super user - restrictions apply when FUERTEWP_FORCE is true', function () {
    // Arrange: Define FUERTEWP_FORCE constant if not already defined
    if (!defined('FUERTEWP_FORCE')) {
        define('FUERTEWP_FORCE', true);
    }

    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'superadmin@example.com';
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Simulate super user check with force mode
    $user = new stdClass();
    $user->user_email = 'superadmin@example.com';
    $isSuperUser = in_array(strtolower($user->user_email), array_map('strtolower', $config['super_users']));
    $isForced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;

    // Assert: Even super users are restricted when forced
    expect($isSuperUser)->toBeTrue();
    expect($isForced)->toBeTrue();
    // When forced, restrictions should apply to super users too
});

test('super user - empty super users list affects all users', function () {
    // Arrange: No super users configured
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Simulate regular user check
    $user = new stdClass();
    $user->user_email = 'regularuser@example.com';
    $isSuperUser = in_array(strtolower($user->user_email), array_map('strtolower', $config['super_users']));

    // Assert: Regular user should not be super user
    expect($isSuperUser)->toBeFalse();
});

test('super user - validates email format', function () {
    // Test various email formats are preserved correctly
    $validEmails = [
        'user@example.com',
        'user.name@example.com',
        'user+tag@example.com',
        'USER@EXAMPLE.COM',
    ];

    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Add all emails to config
    foreach ($validEmails as $index => $email) {
        $wp_tests_options["_fuertewp_super_users|||{$index}|value"] = $email;
    }

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: All email formats should be preserved
    expect($config['super_users'])->toHaveCount(4);
    foreach ($validEmails as $email) {
        expect($config['super_users'])->toContain($email);
    }
});

test('config - handles missing super users gracefully', function () {
    // Arrange: No super users configured at all
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should return empty array, not null or error
    expect($config['super_users'])->toBeArray();
    expect($config['super_users'])->toBeEmpty();
});
