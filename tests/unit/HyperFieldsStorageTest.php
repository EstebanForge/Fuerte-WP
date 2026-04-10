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

test('config - handles hyperfields single array storage', function () {
    // Arrange: Create a mock fuertewp_settings option similar to what HyperFields saves
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_status' => 'enabled',
        'fuertewp_super_users' => ['admin@example.com', 'dev@example.com'],
        'fuertewp_removed_menus' => "plugins.php\nthemes.php\ntools.php",
        'fuertewp_login_max_attempts' => 10,
    ];

    // Act: Load config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Check mapping
    expect($config['status'])->toBe('enabled');
    expect($config['super_users'])->toContain('admin@example.com');
    expect($config['super_users'])->toContain('dev@example.com');
    
    // Check advanced restrictions (the textarea one)
    $removedMenus = Fuerte_Wp_Config::get_field('removed_menus', [], true);
    expect($removedMenus)->toBeArray();
    expect($removedMenus)->toHaveCount(3);
    expect($removedMenus)->toContain('plugins.php');
    expect($removedMenus)->toContain('themes.php');
    expect($removedMenus)->toContain('tools.php');
    
    // Check login security
    expect($config['login_security']['login_max_attempts'])->toBe(10);
});

test('config - hybrid storage (hyperfields + legacy)', function () {
    // Arrange: Some in hyperfields, some in legacy _fuertewp_ options
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_status' => 'enabled',
    ];
    // Legacy mapping
    $wp_tests_options['_fuertewp_login_max_attempts'] = 7;
    
    // Act & Assert
    $config = Fuerte_Wp_Config::get_config(true);
    expect($config['status'])->toBe('enabled');
    expect($config['login_security']['login_max_attempts'])->toBe(7);
});
