<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\when;

beforeEach(function () {
    if (!class_exists('Fuerte_Wp_Enforcer')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-enforcer.php';
    }
    setUp();
});

afterEach(function () {
    tearDown();
});

test('enforcer methods - restrict_rest_api method exists and works', function () {
    // Mock the helper function using Brain\Monkey
    when('fuertewp_restapi_loggedin_only')->returnArg(1);

    // Act & Assert (using Pest's global expect)
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_rest_api'))->toBeTrue();
    
    $result = Fuerte_Wp_Enforcer::restrict_rest_api('original');
    expect($result)->toBe('original');
});

test('enforcer methods - restrict_plugin_installation method exists', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_plugin_installation'))->toBeTrue();
});

test('enforcer methods - restrict_theme_installation method exists', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'restrict_theme_installation'))->toBeTrue();
});

test('enforcer methods - filter_email_notifications method exists and returns false', function () {
    expect(method_exists('Fuerte_Wp_Enforcer', 'filter_email_notifications'))->toBeTrue();
    
    $result = Fuerte_Wp_Enforcer::filter_email_notifications('any_value');
    expect($result)->toBeFalse();
});

test('enforcer methods - methods are callable as static', function () {
    // This is how Hook_Manager calls them
    $callback = ['Fuerte_Wp_Enforcer', 'restrict_rest_api'];
    expect(is_callable($callback))->toBeTrue();
    
    $callback = ['Fuerte_Wp_Enforcer', 'filter_email_notifications'];
    expect(is_callable($callback))->toBeTrue();
});
