<?php

beforeEach(function () {
    // Clear static caches before each test
    global $fuertewp_plugins_cache, $fuertewp_themes_cache;
    $fuertewp_plugins_cache = null;
    $fuertewp_themes_cache = null;
});

/**
 * Helper Functions Tests
 */
test('helpers - returns array of installed plugins', function () {
    // Act: Call the helper function
    $plugins = fuertewp_get_installed_plugins();

    // Assert: Should return an array
    expect($plugins)->toBeArray();

    // Assert: Should have expected structure (plugin file => plugin name)
    expect($plugins)->toHaveKey('hello-dolly/hello.php');
    expect($plugins)->toHaveKey('akismet/akismet.php');
});

test('helpers - returns cached plugin list on subsequent calls', function () {
    // Arrange: Clear any existing cache
    global $fuertewp_plugins_cache;
    $fuertewp_plugins_cache = null;

    // Act: Call the function twice
    $firstCall = fuertewp_get_installed_plugins();
    $secondCall = fuertewp_get_installed_plugins();

    // Assert: Both calls should return the same result
    expect($firstCall)->toBe($secondCall);
});

test('helpers - returns array of installed themes', function () {
    // Act: Call the helper function
    $themes = fuertewp_get_installed_themes();

    // Assert: Should return an array
    expect($themes)->toBeArray();

    // Assert: Should have expected structure (theme slug => theme name)
    expect($themes)->toHaveKey('twentytwentythree');
    expect($themes)->toHaveKey('twentytwentyfour');
});

test('helpers - returns cached theme list on subsequent calls', function () {
    // Arrange: Clear any existing cache
    global $fuertewp_themes_cache;
    $fuertewp_themes_cache = null;

    // Act: Call the function twice
    $firstCall = fuertewp_get_installed_themes();
    $secondCall = fuertewp_get_installed_themes();

    // Assert: Both calls should return the same result
    expect($firstCall)->toBe($secondCall);
});
