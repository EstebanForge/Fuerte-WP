<?php

beforeEach(function () {
    // Clear config cache
    Fuerte_Wp_Config::invalidate_cache();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

afterEach(function () {
    // Clear config cache
    Fuerte_Wp_Config::invalidate_cache();
    // Clear test options
    global $wp_tests_options;
    $wp_tests_options = [];
});

/**
 * Configuration Deferred Updates Tests
 */
test('config - loads deferred plugins from database', function () {
    // Arrange: Set up deferred plugins in database
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';
    $wp_tests_options['_fuertewp_deferred_plugins|||1|value'] = 'akismet/akismet.php';

    // Act: Get config (force bypass cache to load from database)
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should contain deferred plugins
    expect($config)->toHaveKey('deferred_plugins');
    expect($config['deferred_plugins'])->toBeArray();
    expect($config['deferred_plugins'])->toContain('hello-dolly/hello.php');
    expect($config['deferred_plugins'])->toContain('akismet/akismet.php');
});

test('config - loads deferred themes from database', function () {
    // Arrange: Set up deferred themes in database
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';
    $wp_tests_options['_fuertewp_deferred_themes|||1|value'] = 'twentytwentyfour';

    // Act: Get config (force bypass cache to load from database)
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should contain deferred themes
    expect($config)->toHaveKey('deferred_themes');
    expect($config['deferred_themes'])->toBeArray();
    expect($config['deferred_themes'])->toContain('twentytwentythree');
    expect($config['deferred_themes'])->toContain('twentytwentyfour');
});

test('config - returns empty arrays when no deferred items exist', function () {
    // Arrange: Ensure no deferred items in database
    global $wp_tests_options;
    $wp_tests_options = [];

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should return empty arrays
    expect($config['deferred_plugins'])->toBeArray();
    expect($config['deferred_themes'])->toBeArray();
    expect($config['deferred_plugins'])->toBeEmpty();
    expect($config['deferred_themes'])->toBeEmpty();
});

test('config - includes deferred updates in enforcer config', function () {
    // Arrange: Set up deferred plugins and themes in database
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';
    $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';

    // Act: Get config
    $config = Fuerte_Wp_Config::get_config(true);

    // Assert: Should contain deferred updates
    expect($config)->toHaveKey('deferred_plugins');
    expect($config)->toHaveKey('deferred_themes');
    expect($config['deferred_plugins'])->toContain('hello-dolly/hello.php');
    expect($config['deferred_themes'])->toContain('twentytwentythree');
});

test('config - caches deferred updates config', function () {
    // Arrange: Set up deferred plugins in database
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';

    // Act: Get config twice
    $config1 = Fuerte_Wp_Config::get_config(true);
    $config2 = Fuerte_Wp_Config::get_config();

    // Assert: Both should return same deferred plugins
    expect($config1['deferred_plugins'])->toBe($config2['deferred_plugins']);
    expect($config1['deferred_plugins'])->toContain('hello-dolly/hello.php');
});

test('config - invalidates cache when requested', function () {
    // Arrange: Set up one deferred plugin and get config
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';

    $config1 = Fuerte_Wp_Config::get_config(true);

    // Act: Add another plugin and invalidate cache
    $wp_tests_options['_fuertewp_deferred_plugins|||1|value'] = 'akismet/akismet.php';
    Fuerte_Wp_Config::invalidate_cache();
    $config2 = Fuerte_Wp_Config::get_config();

    // Assert: New config should have both plugins
    expect($config2['deferred_plugins'])->toHaveCount(2);
});
