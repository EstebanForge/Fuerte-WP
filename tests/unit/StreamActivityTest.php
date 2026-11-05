<?php

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Brain\Monkey\Functions\Functions;

beforeEach(function () {
    setUp();
    global $wp_tests_options;
    $wp_tests_options = [];
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    global $wp_tests_options;
    $wp_tests_options = [];
    Fuerte_Wp_Config::invalidate_cache();
});

/**
 * Tests for Fuerte_Wp_Stream_Activity: Stream detection gate, digest section
 * query contracts, digest email body, and digest delivery gating.
 */
class TestableStreamActivity extends Fuerte_Wp_Stream_Activity {
    public array $login_attempts = [];

    protected function fetch_login_attempts($args) {
        return $this->login_attempts;
    }
}

test('is_active() returns false when Stream is not loaded', function () {
    // WP_Stream\Plugin is never defined in the unit-test process.
    expect(Fuerte_Wp_Stream_Activity::is_active())->toBeFalse();
});

test('boot() registers connector filter, scheduler, and cron runners', function () {
    Fuerte_Wp_Stream_Activity::boot();

    global $wp_tests_hooks;

    $plugins = $wp_tests_hooks['plugins_loaded'][0] ?? null;
    expect($plugins)->not->toBeNull();
    expect($plugins['function'])->toBe(['Fuerte_Wp_Stream_Activity', 'register_connector']);
    // After Stream's plugins_loaded 20 boot, before its init 9 Connectors instantiation.
    expect($plugins['priority'])->toBe(30);

    $init = $wp_tests_hooks['init'][0] ?? null;
    expect($init)->not->toBeNull();
    expect($init['function'])->toBe(['Fuerte_Wp_Stream_Activity', 'schedule_digest']);
    expect($init['priority'])->toBe(20);

    expect($wp_tests_hooks['fuertewp_stream_digest_daily'][0]['function'])
        ->toBe(['Fuerte_Wp_Stream_Activity', 'run_daily_digest']);
    expect($wp_tests_hooks['fuertewp_stream_digest_weekly'][0]['function'])
        ->toBe(['Fuerte_Wp_Stream_Activity', 'run_weekly_digest']);
    expect($wp_tests_hooks['fuertewp_stream_digest_monthly'][0]['function'])
        ->toBe(['Fuerte_Wp_Stream_Activity', 'run_monthly_digest']);
    expect($wp_tests_hooks['cron_schedules'][0]['function'])
        ->toBe(['Fuerte_Wp_Stream_Activity', 'add_monthly_schedule']);
});

test('updates section queries installer connector, updated action, time window', function () {
    $query = Fuerte_Wp_Stream_Activity::section_query('updates', '2026-01-01 00:00:00', '2026-01-02 00:00:00');

    expect($query['params'])->toContain('installer');
    expect($query['params'])->toContain('updated');
    expect($query['params'])->toContain('2026-01-01 00:00:00');
    expect($query['params'])->toContain('2026-01-02 00:00:00');
    expect(implode(' ', $query['where']))->toContain('created >=');
    expect(implode(' ', $query['where']))->toContain('created <=');
});

test('lifecycle section covers installed, activated, deactivated, deleted for plugins and themes', function () {
    $query = Fuerte_Wp_Stream_Activity::section_query('lifecycle', '2026-01-01 00:00:00', '2026-01-02 00:00:00');

    foreach (['installed', 'activated', 'deactivated', 'deleted'] as $action) {
        expect($query['params'])->toContain($action);
    }
    expect($query['params'])->toContain('plugins');
    expect($query['params'])->toContain('themes');
    expect($query['params'])->toContain('installer');
});

test('new_admins section joins stream_meta on the Administrator role', function () {
    $query = Fuerte_Wp_Stream_Activity::section_query('new_admins', '2026-01-01 00:00:00', '2026-01-02 00:00:00');

    $sql = implode(' ', $query['where']);
    expect($sql)->toContain('EXISTS');
    expect($sql)->toContain('meta_key');
    expect($sql)->toContain('record_id = s.ID');
    expect($query['params'])->toContain('users');
    expect($query['params'])->toContain('created');
    expect($query['params'])->toContain('roles');
    expect($query['params'])->toContain('%Administrator%');
});

test('admin_passwords section keys on password-reset with administrator actor', function () {
    $query = Fuerte_Wp_Stream_Activity::section_query('admin_passwords', '2026-01-01 00:00:00', '2026-01-02 00:00:00');

    expect($query['params'])->toContain('users');
    expect($query['params'])->toContain('password-reset');
    expect($query['params'])->toContain('administrator');
});

test('unknown section name returns empty query', function () {
    $query = Fuerte_Wp_Stream_Activity::section_query('bogus_section', '2026-01-01 00:00:00', '2026-01-02 00:00:00');

    expect($query['where'])->toBeEmpty();
    expect($query['params'])->toBeEmpty();
});

test('add_connector loads and appends the fuerte-wp connector when enabled', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_stream_enable' => true,
    ];

    $result = Fuerte_Wp_Stream_Activity::add_connector([]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf('Fuerte_Wp_Stream_Connector');
    expect($result[0]->name)->toBe('fuerte-wp');
});

test('digest_window daily covers the previous day', function () {
    // 2026-03-10 12:00:00 UTC.
    $now = gmmktime(12, 0, 0, 3, 10, 2026);
    [$start, $end] = Fuerte_Wp_Stream_Activity::digest_window('daily', $now);

    expect(gmdate('Y-m-d', $start))->toBe('2026-03-09');
    expect(gmdate('H:i:s', $start))->toBe('00:00:00');
    expect(gmdate('Y-m-d H:i:s', $end))->toBe('2026-03-09 23:59:59');
});

test('digest_window weekly covers the previous seven days', function () {
    // Tuesday 2026-03-10 12:00:00 UTC.
    $now = gmmktime(12, 0, 0, 3, 10, 2026);
    [$start, $end] = Fuerte_Wp_Stream_Activity::digest_window('weekly', $now);

    expect(gmdate('Y-m-d H:i:s', $start))->toBe('2026-03-03 00:00:00');
    expect(gmdate('Y-m-d H:i:s', $end))->toBe('2026-03-09 23:59:59');
});

test('digest_window monthly covers the previous calendar month', function () {
    // 2026-03-10 12:00:00 UTC: previous month is February 2026 (non-leap).
    $now = gmmktime(12, 0, 0, 3, 10, 2026);
    [$start, $end] = Fuerte_Wp_Stream_Activity::digest_window('monthly', $now);

    expect(gmdate('Y-m-d H:i:s', $start))->toBe('2026-02-01 00:00:00');
    expect(gmdate('Y-m-d H:i:s', $end))->toBe('2026-02-28 23:59:59');
});

test('digest settings default to disabled', function () {
    $settings = Fuerte_Wp_Stream_Activity::digest_settings();

    expect($settings['enable'])->toBeFalse();
    expect($settings['frequency'])->toBe('weekly');
    expect($settings['email'])->toBe('');
});

test('add_connector is inert while activity monitoring is disabled', function () {
    $instances = [['existing-connector']];

    $result = Fuerte_Wp_Stream_Activity::add_connector($instances);

    expect($result)->toBe($instances);
});

test('build_digest renders counts for populated sections', function () {
    $sections = [
        'updates' => [
            ['summary' => 'Updated plugin: Akismet 5.4', 'user' => 'admin', 'created' => '2026-01-01 10:00:00'],
        ],
        'logins' => [
            ['summary' => 'admin logged in from 1.2.3.4', 'user' => 'admin', 'created' => '2026-01-01 09:00:00'],
            ['summary' => 'editor logged in from 1.2.3.4', 'user' => 'editor', 'created' => '2026-01-01 09:05:00'],
        ],
    ];

    $body = Fuerte_Wp_Stream_Activity::build_digest($sections, '2026-01-01');

    expect($body)->toContain('Updated plugin');
    expect($body)->toContain('logged in');
    expect($body)->toContain('2');
});

test('build_digest renders empty state when all sections are empty', function () {
    $body = Fuerte_Wp_Stream_Activity::build_digest([], '2026-01-01');

    expect($body)->toContain('No activity');
});

test('build_digest escapes event summaries', function () {
    $sections = [
        'updates' => [
            ['summary' => '<script>alert(1)</script>', 'user' => 'x', 'created' => '2026-01-01 10:00:00'],
        ],
    ];

    $body = Fuerte_Wp_Stream_Activity::build_digest($sections, '2026-01-01');

    expect($body)->not->toContain('<script>');
    expect($body)->toContain('&lt;script&gt;');
});

test('send_digest sends to admin_email when no custom recipient is set', function () {
    global $wp_tests_options;
    $wp_tests_options['admin_email'] = 'root@example.com';
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_stream_enable' => true,
    ];

    $sent = [];
    when('wp_mail')->alias(function ($to, $subject, $body, $headers = []) use (&$sent) {
        $sent[] = [$to, $subject];
        return true;
    });

    $activity = new TestableStreamActivity();
    $activity->login_attempts = [
        ['username' => 'admin', 'ip' => '1.2.3.4', 'created' => '2026-01-01 09:00:00', 'status' => 'success'],
    ];

    $activity->send_digest();

    expect($sent)->toHaveCount(1);
    expect($sent[0][0])->toBe('root@example.com');
    expect($sent[0][1])->toContain('Activity');
});

test('send_digest sends nothing when digest is disabled', function () {
    global $wp_tests_options;
    // Default state: master switch off.
    $wp_tests_options['fuertewp_settings'] = [];

    $sent = [];
    when('wp_mail')->alias(function ($to, $subject, $body, $headers = []) use (&$sent) {
        $sent[] = [$to, $subject];
        return true;
    });

    $activity = new TestableStreamActivity();
    $activity->send_digest();

    expect($sent)->toBeEmpty();
});

test('send_digest prefers the configured recipient over admin_email', function () {
    global $wp_tests_options;
    $wp_tests_options['admin_email'] = 'root@example.com';
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_stream_enable' => true,
        'fuertewp_stream_digest_email' => 'watcher@example.com',
    ];

    $sent = [];
    when('wp_mail')->alias(function ($to, $subject, $body, $headers = []) use (&$sent) {
        $sent[] = [$to, $subject];
        return true;
    });

    $activity = new TestableStreamActivity();
    $activity->send_digest();

    expect($sent[0][0])->toBe('watcher@example.com');
});

test('login sections come from fuertewp_login_attempts data, not Stream', function () {
    $activity = new TestableStreamActivity();
    $activity->login_attempts = [
        ['username' => 'admin', 'ip' => '1.2.3.4', 'created' => '2026-01-01 09:00:00', 'status' => 'success'],
        ['username' => 'ghost', 'ip' => '9.9.9.9', 'created' => '2026-01-01 09:10:00', 'status' => 'failed'],
    ];

    $start = gmmktime(0, 0, 0, 1, 1, 2026);
    $end = gmmktime(23, 59, 59, 1, 1, 2026);
    $sections = $activity->collect_sections($start, $end);

    expect($sections['logins'])->toHaveCount(1);
    expect($sections['logins'][0]['username'])->toBe('admin');
    expect($sections['failed_logins'])->toHaveCount(1);
    expect($sections['failed_logins'][0]['ip'])->toBe('9.9.9.9');
});
