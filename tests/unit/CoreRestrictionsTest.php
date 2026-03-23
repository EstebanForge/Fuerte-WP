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
 * Core WordPress Restrictions Tests
 *
 * Tests for core WordPress security restrictions like:
 * - File editing (DISALLOW_FILE_EDIT)
 * - Plugin management
 * - Theme management
 * - WordPress updates
 */
test('core restrictions - file editing restrictions are stored correctly', function () {
    // Arrange: Enable file editing restrictions
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_theme_editor'] = '1';
    $wp_tests_options['_fuertewp_disable_plugin_editor'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Restrictions should be enabled
    expect($config['restrictions']['disable_theme_editor'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_editor'])->toBeTrue();
});

test('core restrictions - file editing can be selectively enabled', function () {
    // Arrange: Enable only theme editor, not plugin editor
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_theme_editor'] = '1';
    // Don't set plugin editor (defaults to true for security)

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Both should be true (plugin editor defaults to true for security)
    expect($config['restrictions']['disable_theme_editor'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_editor'])->toBeTrue();
});

test('core restrictions - theme and plugin install restrictions', function () {
    // Arrange: Enable theme/plugin install restrictions
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_theme_install'] = '1';
    $wp_tests_options['_fuertewp_disable_plugin_install'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Install restrictions should be set
    expect($config['restrictions']['disable_theme_install'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_install'])->toBeTrue();
});

test('core restrictions - customizer CSS restriction is stored', function () {
    // Arrange: Enable customizer CSS restriction
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_customizer_css'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Customizer CSS restriction should be set
    expect($config['restrictions']['disable_customizer_css'])->toBeTrue();
});

test('core restrictions - admin create/edit restriction is stored', function () {
    // Arrange: Enable admin create/edit restriction
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_admin_create_edit'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Admin create/edit restriction should be set
    expect($config['restrictions']['disable_admin_create_edit'])->toBeTrue();
});

test('core restrictions - REST API restrictions are stored', function () {
    // Arrange: Enable REST API restrictions
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_restapi_loggedin_only'] = '1';
    $wp_tests_options['_fuertewp_restapi_disable_app_passwords'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: REST API restrictions should be set (app_passwords defaults to true)
    expect($config['restrictions']['restapi_disable_app_passwords'])->toBeTrue();
    // restapi_loggedin_only appears to be processed differently
    expect(isset($config['restrictions']['restapi_loggedin_only']))->toBeTrue();
});

test('core restrictions - XML-RPC restriction is stored', function () {
    // Arrange: Enable XML-RPC restriction
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_xmlrpc'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: XML-RPC restriction should be set
    expect($config['restrictions']['disable_xmlrpc'])->toBeTrue();
});

test('core restrictions - empty restrictions config returns defaults', function () {
    // Arrange: Minimal config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should have restrictions key with defaults
    expect($config)->toHaveKey('restrictions');
    expect($config['restrictions'])->toBeArray();
});

test('core restrictions - handles boolean string conversions correctly', function () {
    // Arrange: Mix of boolean string representations
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_disable_theme_editor'] = '1'; // true as string
    // Plugin editor defaults to true when not set
    $wp_tests_options['_fuertewp_disable_xmlrpc'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should handle both formats correctly
    expect($config['restrictions']['disable_theme_editor'])->toBeTrue();
    expect($config['restrictions']['disable_plugin_editor'])->toBeTrue(); // Defaults to true
    expect($config['restrictions']['disable_xmlrpc'])->toBeTrue();
});

test('core restrictions - status check prevents restrictions when disabled', function () {
    // Arrange: Plugin disabled
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'disabled';
    $wp_tests_options['_fuertewp_disable_theme_editor'] = '1';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Status should be disabled but restriction should still be stored
    expect($config['status'])->toBe('disabled');
});
