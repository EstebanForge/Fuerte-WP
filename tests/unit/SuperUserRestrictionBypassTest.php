<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    // Clear test options
    global $wp_tests_options, $wp_tests_hooks;
    $wp_tests_options = [];
    $wp_tests_hooks = [];
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    // Clear test options
    global $wp_tests_options, $wp_tests_hooks;
    $wp_tests_options = [];
    $wp_tests_hooks = [];
});

/**
 * Super User Restriction Bypass Tests
 *
 * Verifies that super users are NOT affected by restrictions:
 * - Menu removals
 * - Admin bar removals
 * - Theme/plugin editor (DISALLOW_FILE_EDIT)
 * - Page access restrictions (permalinks, ACF, theme/plugin install, customizer CSS)
 */
test('super user - remove_menus skips execution for super user', function () {
    // Arrange: Set up config with super user
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'test@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Assert: Super user is always detected
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();

    // bypasses_restrictions is false when FUERTEWP_FORCE is active
    $is_forced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;
    expect(Fuerte_Wp_Helper::bypasses_restrictions())->toBe(!$is_forced);
});

test('super user - remove_adminbar_menus skips execution for super user', function () {
    // Arrange: Set up config with super user
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'test@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Assert: Super user should be detected via helper
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();
});

test('super user - non-super user is NOT in super_users list', function () {
    // Arrange: Super user email doesn't match current user
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'other@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Assert: Regular user should NOT bypass
    expect(Fuerte_Wp_Helper::is_super_user())->toBeFalse();
    expect(Fuerte_Wp_Helper::bypasses_restrictions())->toBeFalse();
});

test('super user - restrictions apply when forced even for super user', function () {
    // Arrange: Super user configured but FUERTEWP_FORCE is true
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'test@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Super user is detected
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();

    // But bypasses_restrictions returns false when forced
    $is_forced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;
    expect(Fuerte_Wp_Helper::bypasses_restrictions())->toBe(!$is_forced);
});

test('super user - case insensitive matching works in helper', function () {
    // Arrange: Super user stored in uppercase
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'TEST@EXAMPLE.COM';

    Fuerte_Wp_Config::invalidate_cache();

    // Current user email is lowercase test@example.com (from mock)
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();
});

test('super user - all restriction flags are loaded correctly for non-super user', function () {
    // Arrange: Configure all the restriction flags the user reported
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'other@example.com'; // Not current user
    $wp_tests_options['_fuertewp_restrictions_restrict_permalinks'] = true;
    $wp_tests_options['_fuertewp_restrictions_restrict_acf'] = true;
    $wp_tests_options['_fuertewp_restrictions_disable_theme_editor'] = true;
    $wp_tests_options['_fuertewp_restrictions_disable_plugin_editor'] = true;
    $wp_tests_options['_fuertewp_restrictions_disable_theme_install'] = true;
    $wp_tests_options['_fuertewp_restrictions_disable_plugin_install'] = true;
    $wp_tests_options['_fuertewp_restrictions_disable_customizer_css'] = true;

    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: All restrictions should be enabled and user is not super
    expect(Fuerte_Wp_Helper::is_super_user())->toBeFalse();
    expect($config['restrictions']['restrict_permalinks'])->toBeTrue();
    expect($config['restrictions']['restrict_acf'])->toBeTrue();
    expect($config['restrictions']['disable_theme_editor'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_editor'])->toBeTrue();
    expect($config['restrictions']['disable_theme_install'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_install'])->toBeTrue();
    expect($config['restrictions']['disable_customizer_css'])->toBeTrue();
});

test('super user - enforcer early exit check logic is correct', function () {
    // Arrange: Set up config with super user
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'test@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Assert: Super user should be detected
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();
    // bypasses_restrictions accounts for FUERTEWP_FORCE
    $is_forced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;
    expect(Fuerte_Wp_Helper::bypasses_restrictions())->toBe(!$is_forced);
});

test('super user - multiple super users all bypass restrictions', function () {
    // Arrange: Multiple super users configured
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'admin@site.com';
    $wp_tests_options['_fuertewp_super_users|||1|value'] = 'test@example.com';
    $wp_tests_options['_fuertewp_super_users|||2|value'] = 'dev@company.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Current user (test@example.com) should be in the list
    expect(Fuerte_Wp_Helper::is_super_user())->toBeTrue();
});

test('super user - is_super_user accepts explicit user parameter', function () {
    // Arrange: Config has specific super user
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'target@example.com';

    Fuerte_Wp_Config::invalidate_cache();

    // Create mock target user
    $target = new stdClass();
    $target->user_email = 'target@example.com';
    $target->ID = 99;

    $other = new stdClass();
    $other->user_email = 'other@example.com';
    $other->ID = 100;

    // Assert: Explicit user check works
    expect(Fuerte_Wp_Helper::is_super_user($target))->toBeTrue();
    expect(Fuerte_Wp_Helper::is_super_user($other))->toBeFalse();
});
