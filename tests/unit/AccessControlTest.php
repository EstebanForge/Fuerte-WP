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
 * Access Control Tests
 *
 * Tests for menu restrictions, page access control, and admin area restrictions
 */
test('access control - removed menus field storage works', function () {
    // Arrange: Configure removed menus using multiselect format
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_removed_menus|||0|value'] = 'plugins.php';
    $wp_tests_options['_fuertewp_removed_menus|||1|value'] = 'themes.php';
    $wp_tests_options['_fuertewp_removed_menus|||2|value'] = 'tools.php';

    // Act: Load using get_field method
    $removedMenus = Fuerte_Wp_Config::get_field('removed_menus', [], true);

    // Assert: Removed menus should be loaded correctly
    expect($removedMenus)->toBeArray();
    expect($removedMenus)->toHaveCount(3);
    expect($removedMenus)->toContain('plugins.php');
    expect($removedMenus)->toContain('themes.php');
    expect($removedMenus)->toContain('tools.php');
});

test('access control - removed submenus field storage works', function () {
    // Arrange: Configure removed submenus
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_removed_submenus|||0|value'] = 'themes.php';
    $wp_tests_options['_fuertewp_removed_submenus|||1|value'] = 'plugins.php';

    // Act: Load using get_field method
    $removedSubmenus = Fuerte_Wp_Config::get_field('removed_submenus', [], true);

    // Assert: Removed submenus should be loaded correctly
    expect($removedSubmenus)->toBeArray();
    expect($removedSubmenus)->toHaveCount(2);
    expect($removedSubmenus)->toContain('themes.php');
    expect($removedSubmenus)->toContain('plugins.php');
});

test('access control - restricted pages field storage works', function () {
    // Arrange: Configure restricted pages
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_restricted_pages|||0|value'] = 'update-core.php';
    $wp_tests_options['_fuertewp_restricted_pages|||1|value'] = 'plugin-install.php';

    // Act: Load using get_field method
    $restrictedPages = Fuerte_Wp_Config::get_field('restricted_pages', [], true);

    // Assert: Restricted pages should be loaded correctly
    expect($restrictedPages)->toBeArray();
    expect($restrictedPages)->toHaveCount(2);
    expect($restrictedPages)->toContain('update-core.php');
    expect($restrictedPages)->toContain('plugin-install.php');
});

test('access control - restricted scripts field storage works', function () {
    // Arrange: Configure restricted scripts
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_restricted_scripts|||0|value'] = 'plugin-editor.php';
    $wp_tests_options['_fuertewp_restricted_scripts|||1|value'] = 'theme-editor.php';

    // Act: Load using get_field method
    $restrictedScripts = Fuerte_Wp_Config::get_field('restricted_scripts', [], true);

    // Assert: Restricted scripts should be loaded correctly
    expect($restrictedScripts)->toBeArray();
    expect($restrictedScripts)->toHaveCount(2);
    expect($restrictedScripts)->toContain('plugin-editor.php');
    expect($restrictedScripts)->toContain('theme-editor.php');
});

test('access control - admin bar menu removals field storage works', function () {
    // Arrange: Configure admin bar menu removals
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_removed_adminbar_menus|||0|value'] = 'wp-logo';
    $wp_tests_options['_fuertewp_removed_adminbar_menus|||1|value'] = 'updates';
    $wp_tests_options['_fuertewp_removed_adminbar_menus|||2|value'] = 'comments';

    // Act: Load using get_field method
    $removedMenus = Fuerte_Wp_Config::get_field('removed_adminbar_menus', [], true);

    // Assert: Admin bar menu removals should be loaded correctly
    expect($removedMenus)->toBeArray();
    expect($removedMenus)->toHaveCount(3);
    expect($removedMenus)->toContain('wp-logo');
    expect($removedMenus)->toContain('updates');
    expect($removedMenus)->toContain('comments');
});

test('access control - empty access restrictions return empty arrays', function () {
    // Arrange: No access restrictions configured

    // Act: Load fields with no data
    $removedMenus = Fuerte_Wp_Config::get_field('removed_menus', [], true);
    $removedSubmenus = Fuerte_Wp_Config::get_field('removed_submenus', [], true);
    $restrictedPages = Fuerte_Wp_Config::get_field('restricted_pages', [], true);

    // Assert: All access control arrays should be empty
    expect($removedMenus)->toBeArray();
    expect($removedMenus)->toBeEmpty();
    expect($removedSubmenus)->toBeArray();
    expect($removedSubmenus)->toBeEmpty();
    expect($restrictedPages)->toBeArray();
    expect($restrictedPages)->toBeEmpty();
});

test('access control - handles special characters in page names', function () {
    // Arrange: Page names with special characters
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_restricted_pages|||0|value'] = 'page.php?action=edit';
    $wp_tests_options['_fuertewp_restricted_pages|||1|value'] = 'admin.php?page=some-settings';

    // Act: Load field
    $restrictedPages = Fuerte_Wp_Config::get_field('restricted_pages', [], true);

    // Assert: Should preserve special characters
    expect($restrictedPages)->toContain('page.php?action=edit');
    expect($restrictedPages)->toContain('admin.php?page=some-settings');
});

test('access control - access denied message is stored correctly', function () {
    // Arrange: Custom access denied message
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_access_denied_message'] = 'You do not have permission to access this area.';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Message should be stored correctly
    expect($config['general']['access_denied_message'])->toBe('You do not have permission to access this area.');
});

test('access control - recovery email is validated and stored', function () {
    // Arrange: Set recovery email
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_status'] = 'enabled';
    $wp_tests_options['_fuertewp_recovery_email'] = 'recovery@example.com';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Recovery email should be stored
    expect($config['general']['recovery_email'])->toBe('recovery@example.com');
});
