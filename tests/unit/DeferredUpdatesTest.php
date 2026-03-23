<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    // Clear config cache before each test
    Fuerte_Wp_Config::invalidate_cache();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

afterEach(function () {
    tearDown();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

/**
 * Deferred Updates Feature Tests
 */
test('deferred updates - excludes deferred plugins from auto updates', function () {
    // Arrange: Set up WordPress options with deferred plugins (multiselect format)
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';
    $wp_tests_options['_fuertewp_deferred_plugins|||1|value'] = 'akismet/akismet.php';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a plugin item object that IS in deferred list
    $pluginItem = new stdClass();
    $pluginItem->plugin = 'hello-dolly/hello.php';

    // Act: Call the exclude method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->exclude_deferred_plugins(true, $pluginItem);

    // Assert: Plugin should be excluded (returns false)
    expect($result)->toBeFalse();
});

test('deferred updates - allows non deferred plugins to auto update', function () {
    // Arrange: Set up WordPress options with deferred plugins (multiselect format)
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'akismet/akismet.php';
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = '';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a plugin item object that's NOT in deferred list
    $pluginItem = new stdClass();
    $pluginItem->plugin = 'hello-dolly/hello.php';

    // Act: Call the exclude method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->exclude_deferred_plugins(true, $pluginItem);

    // Assert: Plugin should NOT be excluded (returns original value)
    expect($result)->toBeTrue();
});

test('deferred updates - excludes deferred themes from auto updates', function () {
    // Arrange: Set up WordPress options with deferred themes (multiselect format)
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = '';
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';
    $wp_tests_options['_fuertewp_deferred_themes|||1|value'] = 'twentytwentyfour';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a theme item object that IS in deferred list
    $themeItem = new stdClass();
    $themeItem->theme = 'twentytwentythree';

    // Act: Call the exclude method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->exclude_deferred_themes(true, $themeItem);

    // Assert: Theme should be excluded (returns false)
    expect($result)->toBeFalse();
});

test('deferred updates - allows non deferred themes to auto update', function () {
    // Arrange: Set up WordPress options with deferred themes (multiselect format)
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = '';
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a theme item object that's NOT in deferred list
    $themeItem = new stdClass();
    $themeItem->theme = 'twentytwentyfour';

    // Act: Call the exclude method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->exclude_deferred_themes(true, $themeItem);

    // Assert: Theme should NOT be excluded (returns original value)
    expect($result)->toBeTrue();
});

test('deferred updates - handles empty deferred lists gracefully', function () {
    // Arrange: Set up WordPress options with empty deferred lists
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = '';
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = '';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create plugin and theme items
    $pluginItem = new stdClass();
    $pluginItem->plugin = 'hello-dolly/hello.php';

    $themeItem = new stdClass();
    $themeItem->theme = 'twentytwentythree';

    // Act: Call both exclude methods
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $pluginResult = $manager->exclude_deferred_plugins(true, $pluginItem);
    $themeResult = $manager->exclude_deferred_themes(true, $themeItem);

    // Assert: Both should return original value (true)
    expect($pluginResult)->toBeTrue();
    expect($themeResult)->toBeTrue();
});

test('deferred updates - handles missing config keys gracefully', function () {
    // Arrange: Clear all options (simulating missing config)
    global $wp_tests_options;
    $wp_tests_options = [];

    // Invalidate cache
    Fuerte_Wp_Config::invalidate_cache();

    // Create plugin and theme items
    $pluginItem = new stdClass();
    $pluginItem->plugin = 'hello-dolly/hello.php';

    $themeItem = new stdClass();
    $themeItem->theme = 'twentytwentythree';

    // Act: Call both exclude methods
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $pluginResult = $manager->exclude_deferred_plugins(true, $pluginItem);
    $themeResult = $manager->exclude_deferred_themes(true, $themeItem);

    // Assert: Both should return original value (true) when keys are missing
    expect($pluginResult)->toBeTrue();
    expect($themeResult)->toBeTrue();
});
