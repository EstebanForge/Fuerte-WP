<?php

/**
 * UpdraftCentral kill switch tests.
 *
 * Covers request detection, the enable/opt-out gate (file config wins over
 * database, mirroring Fuerte_Wp_Config), filter arming with priorities, and
 * key blinding. The gate must default to protection ON.
 *
 * @since 1.14.0
 */

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

const UDRPC_KEY_OPTIONS = [
    'updraft_central_localkeys',
    'updraft_migrator_localkeys',
    'updraft_central_keys',
];

beforeEach(function () {
    if (!class_exists('Fuerte_Wp_Updraft_Guard')) {
        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-updraft-guard.php';
    }

    setUp();

    $_POST = [];
    unset($_SERVER['HTTP_USER_AGENT']);
    $GLOBALS['fuertewp'] = null;

    global $wp_tests_hooks, $wp_tests_options;
    $wp_tests_hooks = [];
    $wp_tests_options = [];
});

afterEach(function () {
    tearDown();

    $_POST = [];
    unset($_SERVER['HTTP_USER_AGENT']);
    $GLOBALS['fuertewp'] = null;

    global $wp_tests_hooks, $wp_tests_options;
    $wp_tests_hooks = [];
    $wp_tests_options = [];
});

it('detects a udrpc-shaped POST request', function () {
    $_POST['udrpc_message'] = '{"some":"udrpc envelope"}';

    expect(Fuerte_Wp_Updraft_Guard::is_udrpc_request())->toBeTrue();
});

it('detects the udrpc client user agent', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible) class-udrpc.php/1.16';

    expect(Fuerte_Wp_Updraft_Guard::is_udrpc_request())->toBeTrue();
});

it('ignores ordinary traffic', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0';

    expect(Fuerte_Wp_Updraft_Guard::is_udrpc_request())->toBeFalse();
});

it('tolerates a hostile array user agent without a fatal', function () {
    $_SERVER['HTTP_USER_AGENT'] = ['crafted', 'header'];

    expect(Fuerte_Wp_Updraft_Guard::is_udrpc_request())->toBeFalse();
});

it('is enabled by default when nothing is saved', function () {
    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeTrue();
});

it('respects an explicit database opt-out', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeFalse();
});

it('lets the file config re-enable protection over a database opt-out', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];
    $GLOBALS['fuertewp'] = [
        'restrictions' => ['block_updraftcentral' => true],
    ];

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeTrue();
});

it('lets the file config opt out regardless of the database', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => true,
    ];
    $GLOBALS['fuertewp'] = [
        'restrictions' => ['block_updraftcentral' => false],
    ];

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeFalse();
});

it('falls through to the database when the file config is an empty array', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];
    $GLOBALS['fuertewp'] = [];

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeFalse();
});

it('falls through to the database when the file global is not an array', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];
    $GLOBALS['fuertewp'] = 'garbage';

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeFalse();
});

it('defaults to on when the file config exists but omits the key', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];
    $GLOBALS['fuertewp'] = [
        'general' => ['sender_email_enable' => true],
    ];

    expect(Fuerte_Wp_Updraft_Guard::enabled())->toBeTrue();
});

it('arms the factory wipe at the late priority on boot', function () {
    Fuerte_Wp_Updraft_Guard::boot();

    global $wp_tests_hooks;
    $registrations = $wp_tests_hooks['updraftcentral_host_plugins'] ?? [];

    expect($registrations)->toHaveCount(1)
        ->and($registrations[0]['function'])->toBe('__return_empty_array')
        ->and($registrations[0]['priority'])->toBe(FUERTEWP_LATE_PRIORITY);
});

it('arms key blinding on all three connection-key options on boot', function () {
    Fuerte_Wp_Updraft_Guard::boot();

    global $wp_tests_hooks;

    foreach (UDRPC_KEY_OPTIONS as $key_option) {
        // Reads (single-site and multisite) and both write paths are blinded:
        // a key stored while the guard is on must not revive if the guard is
        // later removed.
        $tags = [
            "pre_option_{$key_option}",
            "pre_site_option_{$key_option}",
            "pre_update_option_{$key_option}",
            "pre_update_site_option_{$key_option}",
        ];

        foreach ($tags as $tag) {
            $registrations = $wp_tests_hooks[$tag] ?? [];

            expect($registrations)->toHaveCount(1)
                ->and($registrations[0]['function'])->toBe('__return_empty_array')
                ->and($registrations[0]['priority'])->toBe(FUERTEWP_LATE_PRIORITY);
        }
    }
});

it('arms the updraftplus_get_option blinding with two arguments', function () {
    Fuerte_Wp_Updraft_Guard::boot();

    global $wp_tests_hooks;
    $registrations = $wp_tests_hooks['updraftplus_get_option'] ?? [];

    expect($registrations)->toHaveCount(1)
        ->and($registrations[0]['function'])->toBe([Fuerte_Wp_Updraft_Guard::class, 'blind_key_option'])
        ->and($registrations[0]['priority'])->toBe(FUERTEWP_LATE_PRIORITY)
        ->and($registrations[0]['accepted_args'])->toBe(2);
});

it('registers nothing when opted out in the database', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = [
        'fuertewp_restrictions_block_updraftcentral' => false,
    ];

    Fuerte_Wp_Updraft_Guard::boot();

    global $wp_tests_hooks;

    expect($wp_tests_hooks)->toBeEmpty();
});

it('blinds key options and passes everything else through', function () {
    foreach (UDRPC_KEY_OPTIONS as $key_option) {
        expect(Fuerte_Wp_Updraft_Guard::blind_key_option(['stolen' => 'key'], $key_option))->toBe([])
            ->and(Fuerte_Wp_Updraft_Guard::blind_key_option(['real' => 'data'], $key_option))->toBe([]);
    }

    expect(Fuerte_Wp_Updraft_Guard::blind_key_option(['keep' => 'me'], 'updraftplus_settings'))
        ->toBe(['keep' => 'me']);
});

/**
 * Run the guard in a fresh PHP process: boot()'s deny path calls exit(),
 * which cannot be exercised in-process, and http_response_code(403) is a
 * no-op under CLI so only the exit itself is observable. The harness prints
 * the number of armed filters only if boot() returns.
 *
 * @param string $preboot PHP statements to run before boot().
 * @param bool   $opt_out Whether the harness database reports an opt-out.
 *
 * @return array{0: string, 1: int} Process output and exit code.
 */
function fuertewp_updraft_guard_harness(string $preboot, bool $opt_out = false): array
{
    $get_option = $opt_out
        ? "function get_option(\$k, \$d = false) { return ['fuertewp_restrictions_block_updraftcentral' => false]; }"
        : 'function get_option($k, $d = false) { return $d; }';

    $harness = <<<'PHP'
<?php
define('ABSPATH', 'x/');
define('WPINC', 'wp-includes');
define('FUERTEWP_LATE_PRIORITY', 9999);
__GET_OPTION__
function add_filter(...$args) { global $armed; $armed[] = $args[0]; }
$armed = [];
require __GUARD__;
__PREBOOT__
Fuerte_Wp_Updraft_Guard::boot();
echo 'REACHED filters=' . count($armed);
PHP;

    $harness = str_replace(
        ['__GET_OPTION__', '__GUARD__', '__PREBOOT__'],
        [
            $get_option,
            var_export(FUERTEWP_PATH . 'includes/class-fuerte-wp-updraft-guard.php', true),
            $preboot,
        ],
        $harness
    );

    $file = tempnam(sys_get_temp_dir(), 'fuertewp-guard-');
    file_put_contents($file, $harness);

    try {
        exec(PHP_BINARY . ' ' . escapeshellarg($file) . ' 2>&1', $output_lines, $exit_code);
    } finally {
        unlink($file);
    }

    return [implode("\n", $output_lines), $exit_code];
}

it('exits before arming filters for a udrpc-shaped request', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_POST['udrpc_message'] = 'x';");

    expect($output)->not->toContain('REACHED')
        ->and($exit_code)->toBe(0);
});

it('exits before arming filters for the udrpc client user agent', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_SERVER['HTTP_USER_AGENT'] = 'WordPress/6.9; class-udrpc.php/1.16; https://x.test';");

    expect($output)->not->toContain('REACHED')
        ->and($exit_code)->toBe(0);
});

it('exits before arming filters when the udrpc message is an array', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_POST['udrpc_message'] = ['crafted'];");

    expect($output)->not->toContain('REACHED')
        ->and($exit_code)->toBe(0);
});

it('treats a falsy udrpc message as ordinary traffic', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_POST['udrpc_message'] = '0';");

    expect($output)->toContain('REACHED filters=14')
        ->and($exit_code)->toBe(0);
});

it('passes udrpc requests through when opted out in the database', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_POST['udrpc_message'] = 'x';", true);

    expect($output)->toContain('REACHED filters=0')
        ->and($exit_code)->toBe(0);
});

it('passes udrpc requests through when the file config opts out', function () {
    [$output, $exit_code] = fuertewp_updraft_guard_harness("\$_POST['udrpc_message'] = 'x'; \$GLOBALS['fuertewp'] = ['restrictions' => ['block_updraftcentral' => false]];");

    expect($output)->toContain('REACHED filters=0')
        ->and($exit_code)->toBe(0);
});

it('boots the guard after the FUERTEWP_DISABLE escape hatch, at include scope', function () {
    $entry = file_get_contents(FUERTEWP_PATH . 'fuerte-wp.php');

    $disable_pos = strpos($entry, 'FUERTEWP_DISABLE');
    $boot_pos = strpos($entry, 'Fuerte_Wp_Updraft_Guard::boot()');

    expect($disable_pos)->toBeInt()
        ->and($boot_pos)->toBeInt()
        ->and($boot_pos)->toBeGreaterThan($disable_pos)
        // Column 0 proves top-level scope: a hook-wrapped boot would lose
        // the race against plugins_loaded.
        ->and((bool)preg_match('/^Fuerte_Wp_Updraft_Guard::boot\(\);$/m', $entry))->toBeTrue();
});
