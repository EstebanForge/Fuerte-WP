<?php
/**
 * PHPUnit Bootstrap File
 *
 * @since 1.8.0
 */

// Define test constants
define('FUERTEWP_TESTING', true);
define('FUERTEWP_PATH', dirname(__DIR__) . '/');
define('FUERTEWP_URL', 'https://example.com/wp-content/plugins/fuerte-wp/');
define('FUERTEWP_VERSION', '1.9.0');
define('FUERTEWP_LATE_PRIORITY', 9999);

// Mock WordPress constants
define('ABSPATH', sys_get_temp_dir() . '/wordpress/');
define('WPINC', 'wp-includes');
define('WP_DEBUG', true);
define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins/');

// Load composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Load WordPress function mocks BEFORE loading plugin files
require_once __DIR__ . '/wordpress-mocks.php';

// Initialize Brain Monkey
// Load Brain Monkey API (functions, not class methods in v2)
require_once dirname(__DIR__) . '/vendor/brain/monkey/inc/api.php';
Brain\Monkey\setUp();

// Load Carbon Fields early
if (!defined('Carbon_Fields\\URL')) {
    define('Carbon_Fields\\URL', FUERTEWP_URL . 'vendor/htmlburger/carbon-fields/');
    define('Carbon_Fields\\COMPACT_INPUT', true);
    define('Carbon_Fields\\COMPACT_INPUT_KEY', 'fuertewp_carbonfields');
}

// Load helper functions
if (file_exists(dirname(__DIR__) . '/includes/helpers.php')) {
    require_once dirname(__DIR__) . '/includes/helpers.php';
}

// Load configuration class
if (file_exists(dirname(__DIR__) . '/includes/class-fuerte-wp-config.php')) {
    require_once dirname(__DIR__) . '/includes/class-fuerte-wp-config.php';
}

// Load auto-update manager class
if (file_exists(dirname(__DIR__) . '/includes/class-fuerte-wp-auto-update-manager.php')) {
    require_once dirname(__DIR__) . '/includes/class-fuerte-wp-auto-update-manager.php';
}

// Register shutdown function to tear down Brain Monkey
register_shutdown_function(function () {
    Brain\Monkey\tearDown();
});
