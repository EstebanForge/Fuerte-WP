<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\when;

/**
 * Login URL Hider — AJAX & Cron Guard Tests
 *
 * Regression tests for the bug where `early_wp_admin_check()` blocked
 * WooCommerce Action Scheduler async requests dispatched to admin-ajax.php.
 *
 * NOTE: Tests that would trigger `exit` (unauthenticated admin redirect) are
 * written as source-level regression checks instead, since PHPUnit cannot
 * survive a bare `exit` call without `exit`-wrapper infrastructure.
 *
 * @see class-fuerte-wp-login-url-hider.php early_wp_admin_check()
 * @since 1.9.3
 */

beforeEach(function () {
    setUp();

    // Load Logger (required by Login URL Hider constructor)
    if (!class_exists('Fuerte_Wp_Logger')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-logger.php';
    }

    // Load Login URL Hider
    if (!class_exists('Fuerte_Wp_Login_URL_Hider')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-login-url-hider.php';
    }

    // Reset singleton between tests
    $ref = new ReflectionClass('Fuerte_Wp_Login_URL_Hider');
    $prop = $ref->getProperty('instance');
    $prop->setValue(null, null);

    // Default $_SERVER
    $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
    $_SERVER['HTTP_USER_AGENT'] = 'Test/1.0';

    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
});

// ---------------------------------------------------------------------------
// AJAX guard: early_wp_admin_check() must NOT reach redirect logic
// ---------------------------------------------------------------------------

test('AJAX requests bypass early_wp_admin_check without redirect', function () {
    $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php?action=as_async_request_queue_runner';

    when('wp_doing_ajax')->justReturn(true);
    when('wp_doing_cron')->justReturn(false);
    when('is_user_logged_in')->justReturn(false);

    // If wp_safe_redirect is called, the test fails
    $redirected = false;
    when('wp_safe_redirect')->alias(function () use (&$redirected) {
        $redirected = true;
    });

    $hider = Fuerte_Wp_Login_URL_Hider::get_instance();
    $hider->early_wp_admin_check();

    expect($redirected)->toBeFalse('wp_safe_redirect should not be called for AJAX requests');
});

test('Cron requests bypass early_wp_admin_check without redirect', function () {
    $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';

    when('wp_doing_ajax')->justReturn(false);
    when('wp_doing_cron')->justReturn(true);
    when('is_user_logged_in')->justReturn(false);

    $redirected = false;
    when('wp_safe_redirect')->alias(function () use (&$redirected) {
        $redirected = true;
    });

    $hider = Fuerte_Wp_Login_URL_Hider::get_instance();
    $hider->early_wp_admin_check();

    expect($redirected)->toBeFalse('wp_safe_redirect should not be called for cron requests');
});

// ---------------------------------------------------------------------------
// Action Scheduler specific: nopriv AJAX to admin-ajax.php
// ---------------------------------------------------------------------------

test('Action Scheduler async runner request is not blocked', function () {
    $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php?action=as_async_request_queue_runner&nonce=abc123';

    when('wp_doing_ajax')->justReturn(true);
    when('wp_doing_cron')->justReturn(false);
    when('is_user_logged_in')->justReturn(false);

    $redirected = false;
    when('wp_safe_redirect')->alias(function () use (&$redirected) {
        $redirected = true;
    });

    $hider = Fuerte_Wp_Login_URL_Hider::get_instance();
    $hider->early_wp_admin_check();

    expect($redirected)->toBeFalse('AS async request should not be redirected');
});

// ---------------------------------------------------------------------------
// Logged-in admin: should pass through without redirect
// ---------------------------------------------------------------------------

test('logged-in admin requests pass through without redirect', function () {
    $_SERVER['REQUEST_URI'] = '/wp-admin/index.php';

    when('wp_doing_ajax')->justReturn(false);
    when('wp_doing_cron')->justReturn(false);
    when('is_user_logged_in')->justReturn(true);

    $redirected = false;
    when('wp_safe_redirect')->alias(function () use (&$redirected) {
        $redirected = true;
    });

    $hider = Fuerte_Wp_Login_URL_Hider::get_instance();
    $hider->early_wp_admin_check();

    expect($redirected)->toBeFalse('logged-in admin should not be redirected');
});

// ---------------------------------------------------------------------------
// Front-end: not a wp-admin path, no redirect
// ---------------------------------------------------------------------------

test('front-end requests are not redirected', function () {
    $_SERVER['REQUEST_URI'] = '/shop/sale-items/';

    when('wp_doing_ajax')->justReturn(false);
    when('wp_doing_cron')->justReturn(false);
    when('is_user_logged_in')->justReturn(false);

    $redirected = false;
    when('wp_safe_redirect')->alias(function () use (&$redirected) {
        $redirected = true;
    });

    $hider = Fuerte_Wp_Login_URL_Hider::get_instance();
    $hider->early_wp_admin_check();

    expect($redirected)->toBeFalse('front-end requests should not be redirected');
});

// ---------------------------------------------------------------------------
// Code-structure regression: guards must exist in source
// ---------------------------------------------------------------------------

test('early_wp_admin_check source contains both wp_doing_ajax and wp_doing_cron guards', function () {
    $source = file_get_contents(FUERTEWP_PATH . 'includes/class-fuerte-wp-login-url-hider.php');

    // Extract early_wp_admin_check method body
    $pattern = '/public function early_wp_admin_check\(\)(.*?)^\s{4}\}/ms';
    preg_match($pattern, $source, $matches);

    expect($matches)->toHaveCount(2, 'early_wp_admin_check method not found in source');

    $body = $matches[1];
    expect($body)->toContain('wp_doing_ajax()');
    expect($body)->toContain('wp_doing_cron()');

    // Guards must appear BEFORE is_wp_admin_request check
    $ajaxPos = strpos($body, 'wp_doing_ajax()');
    $cronPos = strpos($body, 'wp_doing_cron()');
    $adminPos = strpos($body, 'is_wp_admin_request()');
    expect($ajaxPos)->toBeLessThan($adminPos);
    expect($cronPos)->toBeLessThan($adminPos);
});

test('protect_wp_admin_access uses wp_doing_ajax not bare defined check', function () {
    $source = file_get_contents(FUERTEWP_PATH . 'includes/class-fuerte-wp-login-url-hider.php');

    // Must use wp_doing_ajax() wrapper (fixes value-check gap)
    expect($source)->toContain('$is_doing_ajax = wp_doing_ajax()');

    // Must NOT contain the old bare defined() check for this variable
    expect($source)->not->toContain("\$is_doing_ajax = defined('DOING_AJAX')");
});

// ---------------------------------------------------------------------------
// Unauthenticated admin redirect: source-level test (avoids exit)
// ---------------------------------------------------------------------------

test('early_wp_admin_check would redirect unauthenticated admin requests', function () {
    $source = file_get_contents(FUERTEWP_PATH . 'includes/class-fuerte-wp-login-url-hider.php');

    $pattern = '/public function early_wp_admin_check\(\)(.*?)^\s{4}\}/ms';
    preg_match($pattern, $source, $matches);
    $body = $matches[1];

    // After AJAX/Cron guards, must check is_wp_admin_request
    expect($body)->toContain('is_wp_admin_request()');

    // Must check is_user_logged_in
    expect($body)->toContain('is_user_logged_in()');

    // Must call wp_safe_redirect for unauthenticated users
    expect($body)->toContain('wp_safe_redirect');

    // Must call exit after redirect
    expect($body)->toContain('exit');
});
