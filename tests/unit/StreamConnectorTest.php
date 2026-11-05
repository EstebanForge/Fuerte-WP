<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
});

afterEach(function () {
    tearDown();
});

/**
 * Tests for Fuerte_Wp_Stream_Connector: fuerte-wp option changes logged into Stream.
 *
 * The connector is gated on Stream being active; these tests exercise the
 * connector contract against the WP_Stream\Connector stub in
 * tests/stream-connector-stub.php.
 */
class CapturingStreamConnector extends Fuerte_Wp_Stream_Connector {
    public array $logs = [];

    public function log($message, $args, $object_id, $context, $action, $user_id = null) {
        $this->logs[] = [
            'message' => $message,
            'args' => $args,
            'object_id' => $object_id,
            'context' => $context,
            'action' => $action,
            'user_id' => $user_id,
        ];
        return true;
    }
}

test('stream connector targets only the fuertewp_settings option hook', function () {
    $connector = new Fuerte_Wp_Stream_Connector();

    expect($connector->name)->toBe('fuerte-wp');
    expect($connector->actions)->toBe(['update_option_fuertewp_settings']);
    // Registered in every context: CLI/cron writes to fuertewp_settings must
    // be logged too, and Stream skips register_frontend=false connectors
    // outside is_admin().
    expect($connector->register_frontend)->toBeTrue();
});

test('stream connector labels are non-empty', function () {
    $connector = new Fuerte_Wp_Stream_Connector();

    expect($connector->get_label())->not->toBeEmpty();
    expect($connector->get_context_labels())->toBeArray()->not->toBeEmpty();
    expect($connector->get_action_labels())->toBeArray()->not->toBeEmpty();
});

test('option change is logged with changed keys, settings context, updated action', function () {
    $connector = new CapturingStreamConnector();

    $old = [
        'fuertewp_super_users' => ['admin@example.com'],
        'fuertewp_autoupdate_core' => true,
    ];
    $new = [
        'fuertewp_super_users' => ['admin@example.com'],
        'fuertewp_autoupdate_core' => false,
        'fuertewp_new_field' => 'value',
    ];

    $connector->callback_update_option_fuertewp_settings($old, $new);

    expect($connector->logs)->toHaveCount(1);
    $entry = $connector->logs[0];
    expect($entry['message'])->toContain('Fuerte-WP');
    expect($entry['context'])->toBe('settings');
    expect($entry['action'])->toBe('updated');
    expect($entry['args']['changed_keys'])->toBeArray()->toContain('fuertewp_autoupdate_core');
    expect($entry['args']['changed_keys'])->toContain('fuertewp_new_field');
    expect($entry['args']['changed_keys'])->not->toContain('fuertewp_super_users');
});

test('identical old and new values log nothing', function () {
    $connector = new CapturingStreamConnector();

    $value = ['fuertewp_autoupdate_core' => true];

    $connector->callback_update_option_fuertewp_settings($value, $value);

    expect($connector->logs)->toBeEmpty();
});

test('non-array values log nothing', function () {
    $connector = new CapturingStreamConnector();

    $connector->callback_update_option_fuertewp_settings(null, '');

    expect($connector->logs)->toBeEmpty();
});
