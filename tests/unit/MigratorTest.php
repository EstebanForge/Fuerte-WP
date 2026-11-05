<?php

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

beforeEach(function () {
    setUp();
    global $wp_tests_options, $wp_tests_force_save_failure;
    $wp_tests_options = [];
    $wp_tests_force_save_failure = false;
    Fuerte_Wp_Config::invalidate_cache();
});

afterEach(function () {
    tearDown();
    global $wp_tests_options, $wp_tests_force_save_failure;
    $wp_tests_options = [];
    $wp_tests_force_save_failure = false;
});

/**
 * Legacy-storage migration tests
 *
 * migrate() must run its scan exactly once per site (autoloaded sentinel),
 * fold legacy rows into fuertewp_settings, delete only the scanned settings
 * rows (never plugin state options), and never overwrite HyperFields values.
 */
test('migrate returns early and changes nothing when the sentinel is set', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_legacy_migrated'] = Fuerte_Wp_Migrator::MIGRATION_VERSION;
    $wp_tests_options['fuertewp_settings'] = ['fuertewp_sender_email' => 'kept@x.cl'];
    $wp_tests_options['_fuertewp_sender_email'] = 'legacy@x.cl';
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'ghost@x.cl';

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['skipped'])->toBeTrue();
    expect($result['migrated_keys'])->toBe(0);
    expect($result['settings_updated'])->toBeFalse();
    expect($wp_tests_options['_fuertewp_sender_email'])->toBe('legacy@x.cl');
    expect(isset($wp_tests_options['_fuertewp_super_users|||0|value']))->toBeTrue();
    expect($wp_tests_options['fuertewp_settings']['fuertewp_sender_email'])->toBe('kept@x.cl');
});

test('migrate marks the sentinel even when no legacy rows exist', function () {
    global $wp_tests_options;

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['settings_updated'])->toBeFalse();
    expect($result['migrated_keys'])->toBe(0);
    expect($wp_tests_options['fuertewp_legacy_migrated'])->toBe(Fuerte_Wp_Migrator::MIGRATION_VERSION);
});

test('migrate folds singles, multiselect rows and CF3 keys, deletes them, keeps state options', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_sender_email'] = 'sec@x.cl';
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'a@x.cl';
    $wp_tests_options['_fuertewp_super_users|||1|value'] = 'b@x.cl';
    $wp_tests_options['_carbon_fields_theme_options_fuerte-wp|fuertewp_deferred_plugins|0|value'] = 'akismet/akismet.php';
    $wp_tests_options['_fuertewp_login_db_version'] = '3';

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['migrated_keys'])->toBe(3);
    expect($result['settings_updated'])->toBeTrue();

    $settings = $wp_tests_options['fuertewp_settings'];
    expect($settings['fuertewp_sender_email'])->toBe('sec@x.cl');
    expect($settings['fuertewp_super_users'])->toBe(['a@x.cl', 'b@x.cl']);
    expect($settings['fuertewp_deferred_plugins'])->toBe(['akismet/akismet.php']);

    // Scanned legacy rows are removed after the fold.
    expect(isset($wp_tests_options['_fuertewp_sender_email']))->toBeFalse();
    expect(isset($wp_tests_options['_fuertewp_super_users|||0|value']))->toBeFalse();
    expect(isset($wp_tests_options['_fuertewp_super_users|||1|value']))->toBeFalse();
    expect(isset($wp_tests_options['_carbon_fields_theme_options_fuerte-wp|fuertewp_deferred_plugins|0|value']))->toBeFalse();

    // Plugin state options are not migration targets and survive.
    expect($wp_tests_options['_fuertewp_login_db_version'])->toBe('3');
    expect($wp_tests_options['fuertewp_legacy_migrated'])->toBe(Fuerte_Wp_Migrator::MIGRATION_VERSION);
});

test('migrate never overwrites existing HyperFields values and still deletes dead rows', function () {
    global $wp_tests_options;
    $wp_tests_options['fuertewp_settings'] = ['fuertewp_sender_email' => 'new@x.cl'];
    $wp_tests_options['_fuertewp_sender_email'] = 'old@x.cl';

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['migrated_keys'])->toBe(0);
    expect($result['settings_updated'])->toBeFalse();
    expect($wp_tests_options['fuertewp_settings']['fuertewp_sender_email'])->toBe('new@x.cl');
    expect(isset($wp_tests_options['_fuertewp_sender_email']))->toBeFalse();
    expect($wp_tests_options['fuertewp_legacy_migrated'])->toBe(Fuerte_Wp_Migrator::MIGRATION_VERSION);
});

test('migrate keeps the site clean on the request after the sentinel lands', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_sender_email'] = 'sec@x.cl';

    Fuerte_Wp_Migrator::migrate();
    $afterFirst = $wp_tests_options;

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['skipped'])->toBeTrue();
    expect($wp_tests_options)->toBe($afterFirst);
});

test('migrate deletes nothing and sets no sentinel when the settings save fails', function () {
    global $wp_tests_options, $wp_tests_force_save_failure;
    $wp_tests_options['_fuertewp_sender_email'] = 'sec@x.cl';
    $wp_tests_force_save_failure = true;

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['skipped'])->toBeFalse();
    expect($result['migrated_keys'])->toBe(0);
    expect($result['settings_updated'])->toBeFalse();
    expect($wp_tests_options['_fuertewp_sender_email'])->toBe('sec@x.cl');
    expect(isset($wp_tests_options['fuertewp_legacy_migrated']))->toBeFalse();

    // Retry on the next request succeeds.
    $wp_tests_force_save_failure = false;
    $result = Fuerte_Wp_Migrator::migrate();
    expect($result['migrated_keys'])->toBe(1);
    expect(isset($wp_tests_options['_fuertewp_sender_email']))->toBeFalse();
    expect($wp_tests_options['fuertewp_legacy_migrated'])->toBe(Fuerte_Wp_Migrator::MIGRATION_VERSION);
});

test('migrate folds and deletes legacy false values, not just missing options', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_restrictions_disable_xmlrpc'] = false;

    $result = Fuerte_Wp_Migrator::migrate();

    expect($result['migrated_keys'])->toBe(1);
    expect($wp_tests_options['fuertewp_settings']['fuertewp_restrictions_disable_xmlrpc'])->toBeFalse();
    expect(isset($wp_tests_options['_fuertewp_restrictions_disable_xmlrpc']))->toBeFalse();
});

test('migrate deletes CF companion rows alongside value rows', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_super_users|||0|value'] = 'a@x.cl';
    $wp_tests_options['_fuertewp_super_users|||0|_type'] = 'fuertewp_super_users';

    Fuerte_Wp_Migrator::migrate();

    expect($wp_tests_options['fuertewp_settings']['fuertewp_super_users'])->toBe(['a@x.cl']);
    expect(isset($wp_tests_options['_fuertewp_super_users|||0|_type']))->toBeFalse();
    expect(isset($wp_tests_options['_fuertewp_super_users|||0|value']))->toBeFalse();
});

test('migrate folds shape-1 values over CF3 rows and deletes both row sets', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'shape1/one.php';
    $wp_tests_options['_carbon_fields_theme_options_fuerte-wp|fuertewp_blocked_plugins|0|value'] = 'cf3/two.php';

    $result = Fuerte_Wp_Migrator::migrate();

    expect($wp_tests_options['fuertewp_settings']['fuertewp_blocked_plugins'])->toBe(['shape1/one.php']);
    expect(isset($wp_tests_options['_fuertewp_blocked_plugins|||0|value']))->toBeFalse();
    expect(isset($wp_tests_options['_carbon_fields_theme_options_fuerte-wp|fuertewp_blocked_plugins|0|value']))->toBeFalse();
});

test('migrate deletes multiselect single artifacts without folding them', function () {
    global $wp_tests_options;
    $wp_tests_options['_fuertewp_blocked_plugins'] = ['stale/artifact.php'];

    $result = Fuerte_Wp_Migrator::migrate();

    // The single was never a read path for multiselect keys: delete only.
    expect($result['migrated_keys'])->toBe(0);
    expect(isset($wp_tests_options['fuertewp_settings']['fuertewp_blocked_plugins']))->toBeFalse();
    expect(isset($wp_tests_options['_fuertewp_blocked_plugins']))->toBeFalse();
});
