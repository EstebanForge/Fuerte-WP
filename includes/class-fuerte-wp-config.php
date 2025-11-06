<?php

/**
 * Simple Configuration Manager for Fuerte-WP
 *
 * Uses WordPress transients for configuration storage and caching.
 * Much simpler than the previous complex config cache system.
 *
 * @link       https://actitud.xyz
 * @since      1.7.0
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Simple configuration manager class.
 *
 * @since 1.7.0
 */
class Fuerte_Wp_Config
{
    /**
     * Transient key for configuration cache.
     *
     * @since 1.7.0
     * @var string
     */
    private static $transient_key = 'fuertewp_config';

    /**
     * Cache expiration time in seconds (12 hours).
     *
     * @since 1.7.0
     * @var int
     */
    private static $cache_expiration = 12 * HOUR_IN_SECONDS;

    /**
     * Get configuration value.
     *
     * @since 1.7.0
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public static function get($key, $default = null)
    {
        $config = self::get_config();

        // Support dot notation for nested keys
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $config;

            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return $default;
                }
                $value = $value[$k];
            }

            return $value;
        }

        return isset($config[$key]) ? $config[$key] : $default;
    }

    /**
     * Get the entire configuration array.
     *
     * @since 1.7.0
     * @return array Configuration array
     */
    public static function get_config()
    {
        // Try to get from transient first
        $cached_config = get_transient(self::$transient_key);
        if ($cached_config !== false) {
            return $cached_config;
        }

        // Load from file first if it exists (file takes priority)
        $config = self::load_from_file();
        if (empty($config)) {
            // Fallback to database if no file config
            $config = self::load_from_database();
        }

        self::save_config($config);

        return $config;
    }

    /**
     * Load configuration from wp-config-fuerte.php file.
     *
     * @since 1.7.0
     * @return array Configuration array from file, empty array if file doesn't exist
     */
    private static function load_from_file()
    {
        if (!file_exists(ABSPATH . 'wp-config-fuerte.php')) {
            return [];
        }

        // Include the file to get the $fuertewp global variable
        global $fuertewp;

        // Store current state to avoid conflicts
        $original_fuertewp = $fuertewp ?? [];

        // Clear and reload the file
        $fuertewp = [];
        require_once ABSPATH . 'wp-config-fuerte.php';

        // Get the configuration
        $config = is_array($fuertewp) ? $fuertewp : [];

        // Restore original state
        $fuertewp = $original_fuertewp;

        return $config;
    }

    /**
     * Save configuration to transient.
     *
     * @since 1.7.0
     * @param array $config Configuration array
     * @return bool Success status
     */
    public static function save_config($config)
    {
        return set_transient(self::$transient_key, $config, self::$cache_expiration);
    }

    /**
     * Invalidate configuration cache.
     *
     * @since 1.7.0
     * @return void
     */
    public static function invalidate_cache()
    {
        delete_transient(self::$transient_key);
    }

    /**
     * Load configuration from database.
     *
     * @since 1.7.0
     * @return array Configuration from database
     */
    private static function load_from_database()
    {
        $config = [];

        // Load from individual options stored by Carbon Fields
        $config['status'] = get_option('fuertewp_status', 'enabled');

        // Load super users from Carbon Fields
        $super_users = get_option('_fuertewp_super_users', '');
        $config['super_users'] = !empty($super_users) ? array($super_users) : array();

        // Load general settings
        $config['general'] = array(
            'access_denied_message' => get_option('_fuertewp_access_denied_message', 'Access denied.'),
            'recovery_email' => get_option('_fuertewp_recovery_email', ''),
            'sender_email_enable' => get_option('_fuertewp_sender_email_enable', true),
            'sender_email' => get_option('_fuertewp_sender_email', ''),
            'autoupdate_core' => get_option('_fuertewp_autoupdate_core', false),
            'autoupdate_plugins' => get_option('_fuertewp_autoupdate_plugins', false),
            'autoupdate_themes' => get_option('_fuertewp_autoupdate_themes', false),
            'autoupdate_translations' => get_option('_fuertewp_autoupdate_translations', false),
            'autoupdate_frequency' => get_option('_fuertewp_autoupdate_frequency', 'daily'),
        );

        // Load login security settings
        $config['login_security'] = array(
            'login_enable' => get_option('_fuertewp_login_enable', 'enabled'),
            'registration_enable' => get_option('_fuertewp_registration_enable', 'enabled'),
            'login_max_attempts' => intval(get_option('_fuertewp_login_max_attempts', 5)),
            'login_lockout_duration' => intval(get_option('_fuertewp_login_lockout_duration', 60)),
            'login_increasing_lockout' => get_option('_fuertewp_login_increasing_lockout', false),
            'login_ip_headers' => get_option('_fuertewp_login_ip_headers', ''),
            'login_gdpr_message' => get_option('_fuertewp_login_gdpr_message', ''),
            'login_data_retention' => intval(get_option('_fuertewp_login_data_retention', 30)),
            'login_url_hiding_enabled' => get_option('_fuertewp_login_url_hiding_enabled', false) ? '1' : '',
            'custom_login_slug' => get_option('_fuertewp_custom_login_slug', 'secure-login'),
            'login_url_type' => get_option('_fuertewp_login_url_type', 'query_param'),
            'redirect_invalid_logins' => get_option('_fuertewp_redirect_invalid_logins', 'home_404'),
            'redirect_invalid_logins_url' => get_option('_fuertewp_redirect_invalid_logins_url', ''),
        );

        // Load restrictions
        $config['restrictions'] = array(
            'restapi_loggedin_only' => get_option('_fuertewp_restrictions_restapi_loggedin_only', false),
            'restapi_disable_app_passwords' => get_option('_fuertewp_restrictions_restapi_disable_app_passwords', true),
            'disable_xmlrpc' => get_option('_fuertewp_restrictions_disable_xmlrpc', true),
            'htaccess_security_rules' => get_option('_fuertewp_restrictions_htaccess_security_rules', true),
            'disable_admin_create_edit' => get_option('_fuertewp_restrictions_disable_admin_create_edit', true),
            'disable_weak_passwords' => get_option('_fuertewp_restrictions_disable_weak_passwords', true),
            'force_strong_passwords' => get_option('_fuertewp_restrictions_force_strong_passwords', false),
            'disable_admin_bar_roles' => get_option('_fuertewp_restrictions_disable_admin_bar_roles', array('subscriber', 'customer')),
            'restrict_permalinks' => get_option('_fuertewp_restrictions_restrict_permalinks', true),
            'restrict_acf' => get_option('_fuertewp_restrictions_restrict_acf', true),
            'disable_theme_editor' => get_option('_fuertewp_restrictions_disable_theme_editor', true),
            'disable_plugin_editor' => get_option('_fuertewp_restrictions_disable_plugin_editor', true),
            'disable_theme_install' => get_option('_fuertewp_restrictions_disable_theme_install', true),
            'disable_plugin_install' => get_option('_fuertewp_restrictions_disable_plugin_install', true),
            'disable_customizer_css' => get_option('_fuertewp_restrictions_disable_customizer_css', true),
        );

        // Load email settings
        $config['emails'] = array(
            'fatal_error' => get_option('_fuertewp_emails_fatal_error', true),
            'automatic_updates' => get_option('_fuertewp_emails_automatic_updates', false),
            'comment_awaiting_moderation' => get_option('_fuertewp_emails_comment_awaiting_moderation', false),
            'comment_has_been_published' => get_option('_fuertewp_emails_comment_has_been_published', false),
            'user_reset_their_password' => get_option('_fuertewp_emails_user_reset_their_password', false),
            'user_confirm_personal_data_export_request' => get_option('_fuertewp_emails_user_confirm_personal_data_export_request', false),
            'new_user_created' => get_option('_fuertewp_emails_new_user_created', true),
            'network_new_site_created' => get_option('_fuertewp_emails_network_new_site_created', false),
            'network_new_user_site_registered' => get_option('_fuertewp_emails_network_new_user_site_registered', false),
            'network_new_site_activated' => get_option('_fuertewp_emails_network_new_site_activated', false),
        );

        // Load tweaks
        $config['tweaks'] = array(
            'use_site_logo_login' => get_option('_fuertewp_tweaks_use_site_logo_login', false),
        );

        return $config;
    }
}