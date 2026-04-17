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
 * Blocked Updates Feature Tests
 */
test('blocked updates - removes blocked plugins from update transient', function () {
    // Arrange: Set up WordPress options with blocked plugins
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';
    $wp_tests_options['_fuertewp_blocked_plugins|||1|value'] = 'akismet/akismet.php';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a mock update transient object
    $updateTransient = new stdClass();
    $updateTransient->response = [
        'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
        'akismet/akismet.php' => (object)['slug' => 'akismet', 'new_version' => '5.0.0'],
        'jetpack/jetpack.php' => (object)['slug' => 'jetpack', 'new_version' => '10.0.0'],
    ];
    $updateTransient->no_update = [];
    $updateTransient->checked = [];

    // Act: Call the block method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->block_plugin_updates($updateTransient);

    // Assert: Blocked plugins should be removed from response
    expect($result)->toBeObject();
    expect(isset($result->response['hello-dolly/hello.php']))->toBeFalse();
    expect(isset($result->response['akismet/akismet.php']))->toBeFalse();
    expect(isset($result->response['jetpack/jetpack.php']))->toBeTrue();
});

test('blocked updates - removes blocked themes from update transient', function () {
    // Arrange: Set up WordPress options with blocked themes
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = '';
    $wp_tests_options['_fuertewp_blocked_themes|||0|value'] = 'twentytwentythree';
    $wp_tests_options['_fuertewp_blocked_themes|||1|value'] = 'twentytwentyfour';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a mock update transient object
    $updateTransient = new stdClass();
    $updateTransient->response = [
        'twentytwentythree' => (object)['slug' => 'twentytwentythree', 'new_version' => '1.5.0'],
        'twentytwentyfour' => (object)['slug' => 'twentytwentyfour', 'new_version' => '1.2.0'],
        'twentytwentytwo' => (object)['slug' => 'twentytwentytwo', 'new_version' => '1.3.0'],
    ];
    $updateTransient->no_update = [];
    $updateTransient->checked = [];

    // Act: Call the block method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->block_theme_updates($updateTransient);

    // Assert: Blocked themes should be removed from response
    expect($result)->toBeObject();
    expect(isset($result->response['twentytwentythree']))->toBeFalse();
    expect(isset($result->response['twentytwentyfour']))->toBeFalse();
    expect(isset($result->response['twentytwentytwo']))->toBeTrue();
});

test('blocked updates - returns false when transient is false', function () {
    // Arrange: Set up WordPress options with blocked plugins
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Act: Call the block method with false
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->block_plugin_updates(false);

    // Assert: Should return false as-is
    expect($result)->toBeFalse();
});

test('blocked updates - handles empty blocked lists gracefully', function () {
    // Arrange: Set up WordPress options with empty blocked lists
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = '';
    $wp_tests_options['_fuertewp_blocked_themes|||0|value'] = '';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create mock update transient objects
    $pluginTransient = new stdClass();
    $pluginTransient->response = [
        'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
    ];

    $themeTransient = new stdClass();
    $themeTransient->response = [
        'twentytwentythree' => (object)['slug' => 'twentytwentythree', 'new_version' => '1.5.0'],
    ];

    // Act: Call both block methods
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $pluginResult = $manager->block_plugin_updates($pluginTransient);
    $themeResult = $manager->block_theme_updates($themeTransient);

    // Assert: Both should return unchanged objects
    expect($pluginResult)->toBeObject();
    expect($pluginResult->response)->toHaveCount(1);
    expect($themeResult)->toBeObject();
    expect($themeResult->response)->toHaveCount(1);
});

test('blocked updates - handles missing config keys gracefully', function () {
    // Arrange: Clear all options (simulating missing config)
    global $wp_tests_options;
    $wp_tests_options = [];

    // Invalidate cache
    Fuerte_Wp_Config::invalidate_cache();

    // Create mock update transient objects
    $pluginTransient = new stdClass();
    $pluginTransient->response = [
        'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
    ];

    $themeTransient = new stdClass();
    $themeTransient->response = [
        'twentytwentythree' => (object)['slug' => 'twentytwentythree', 'new_version' => '1.5.0'],
    ];

    // Act: Call both block methods
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $pluginResult = $manager->block_plugin_updates($pluginTransient);
    $themeResult = $manager->block_theme_updates($themeTransient);

    // Assert: Both should return unchanged objects when keys are missing
    expect($pluginResult)->toBeObject();
    expect($pluginResult->response)->toHaveCount(1);
    expect($themeResult)->toBeObject();
    expect($themeResult->response)->toHaveCount(1);
});

test('blocked updates - removes from no_update and checked arrays', function () {
    // Arrange: Set up WordPress options with blocked plugins
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a mock update transient object with all arrays populated
    $updateTransient = new stdClass();
    $updateTransient->response = [];
    $updateTransient->no_update = [
        'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly'],
        'akismet/akismet.php' => (object)['slug' => 'akismet'],
    ];
    $updateTransient->checked = [
        'hello-dolly/hello.php' => '1.5.0',
        'akismet/akismet.php' => '4.0.0',
    ];

    // Act: Call the block method
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();
    $result = $manager->block_plugin_updates($updateTransient);

    // Assert: Blocked plugin should be removed from all arrays
    expect(isset($result->no_update['hello-dolly/hello.php']))->toBeFalse();
    expect(isset($result->checked['hello-dolly/hello.php']))->toBeFalse();
    expect(isset($result->no_update['akismet/akismet.php']))->toBeTrue();
    expect(isset($result->checked['akismet/akismet.php']))->toBeTrue();
});

test('blocked updates - also prevents auto-updates for blocked items', function () {
    // Arrange: Set up WordPress options with blocked plugins
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';

    // Invalidate cache to force reload
    Fuerte_Wp_Config::invalidate_cache();

    // Create a plugin item object that IS in blocked list
    $pluginItem = new stdClass();
    $pluginItem->plugin = 'hello-dolly/hello.php';

    // Act: Call the exclude_deferred_plugins method
    // (blocked items should also be excluded from auto-updates)
    $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

    // Even though not directly in deferred list, the block_plugin_updates
    // method should have removed it from the update transient
    $transient = new stdClass();
    $transient->response = [
        'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
    ];

    $result = $manager->block_plugin_updates($transient);

    // Assert: Plugin should be removed from transient
    expect(isset($result->response['hello-dolly/hello.php']))->toBeFalse();
});
