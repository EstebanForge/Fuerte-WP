<?php

/**
 * Auto-Update Manager class.
 *
 * @link       https://actitud.xyz
 * @since      1.5.0
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Fuerte-WP Auto-Update Manager.
 */
class Fuerte_Wp_Auto_Update_Manager
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = null;

    /**
     * Access this plugin instance.
     */
    public static function get_instance()
    {
        null === self::$instance and self::$instance = new self();

        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Hook into WordPress
        add_action('init', [$this, 'init']);
    }

    /**
     * Initialize the auto-update manager.
     */
    public function init()
    {
        // Add action hook for the cron event
        add_action('fuertewp_trigger_updates', [$this, 'trigger_updates']);
    }

    /**
     * Manage auto-update configuration.
     *
     * @param array $fuertewp The Fuerte-WP configuration
     * @return void
     */
    public function manage_updates($fuertewp)
    {
        $some_updates_enabled = false;

        if (isset($fuertewp['general']['autoupdate_core']) && true === $fuertewp['general']['autoupdate_core']) {
            $some_updates_enabled = true;
        }

        if (isset($fuertewp['general']['autoupdate_plugins']) && true === $fuertewp['general']['autoupdate_plugins']) {
            $some_updates_enabled = true;
        }

        if (isset($fuertewp['general']['autoupdate_themes']) && true === $fuertewp['general']['autoupdate_themes']) {
            $some_updates_enabled = true;
        }

        if (isset($fuertewp['general']['autoupdate_translations']) && true === $fuertewp['general']['autoupdate_translations']) {
            $some_updates_enabled = true;
        }

        if ($some_updates_enabled === true) {
            // Updates enabled, register the cron with configured frequency
            $this->register_autoupdate_cron($fuertewp['general']['autoupdate_frequency']);
        } else {
            // No updates enabled, remove the cron
            $this->remove_autoupdate_cron();
        }
    }

    /**
     * Register autoupdate
     * Forces WordPress, via scheduled task, to perform the update routine
     * with configurable frequency.
     *
     * @param string $frequency The update frequency (six_hours, twelve_hours, daily, twodays)
     * @return void
     */
    protected function register_autoupdate_cron($frequency = 'twelve_hours')
    {
        // Default frequency if not provided
        if (empty($frequency)) {
            $frequency = 'twelve_hours';
        }

        // Clear existing schedule to ensure frequency change takes effect
        wp_clear_scheduled_hook('fuertewp_trigger_updates');

        // Check if event isn't already scheduled with the new frequency
        if (!wp_next_scheduled('fuertewp_trigger_updates')) {
            wp_schedule_event(time(), $frequency, 'fuertewp_trigger_updates');
        }
    }

    /**
     * Remove autoupdate cron.
     *
     * @return void
     */
    protected function remove_autoupdate_cron()
    {
        wp_clear_scheduled_hook('fuertewp_trigger_updates');
    }

    /**
     * Do the updates
     * This method is called by the cronjob to perform scheduled updates.
     *
     * @return void
     */
    public function trigger_updates()
    {
        // Get current configuration from enforcer
        $enforcer = Fuerte_Wp_Enforcer::get_instance();
        $fuertewp = $enforcer->config_setup();

        // Log
        if (function_exists('write_log')) {
            write_log('Fuerte-WP trigger_updates ran at ' . date('Y-m-d H:i:s'));
        }

        // Force fresh update checks by clearing caches
        if (function_exists('wp_clean_update_cache')) {
            wp_clean_update_cache();
        }

        // Clear specific update caches
        delete_site_transient('update_core');
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');

        // Force update checks
        if (isset($fuertewp['general']['autoupdate_core']) && true === $fuertewp['general']['autoupdate_core']) {
            wp_version_check(); // Force core update check
        }

        if (isset($fuertewp['general']['autoupdate_plugins']) && true === $fuertewp['general']['autoupdate_plugins']) {
            wp_update_plugins(); // Force plugin update check
        }

        if (isset($fuertewp['general']['autoupdate_themes']) && true === $fuertewp['general']['autoupdate_themes']) {
            wp_update_themes(); // Force theme update check
        }

        // Set up filters globally for the update process
        if (isset($fuertewp['general']['autoupdate_core']) && true === $fuertewp['general']['autoupdate_core']) {
            add_filter('auto_update_core', '__return_true', FUERTEWP_LATE_PRIORITY);
            add_filter('allow_minor_auto_core_updates', '__return_true', FUERTEWP_LATE_PRIORITY);
            add_filter('allow_major_auto_core_updates', '__return_true', FUERTEWP_LATE_PRIORITY);
        }

        if (isset($fuertewp['general']['autoupdate_plugins']) && true === $fuertewp['general']['autoupdate_plugins']) {
            add_filter('auto_update_plugin', '__return_true', FUERTEWP_LATE_PRIORITY);
        }

        if (isset($fuertewp['general']['autoupdate_themes']) && true === $fuertewp['general']['autoupdate_themes']) {
            add_filter('auto_update_theme', '__return_true', FUERTEWP_LATE_PRIORITY);
        }

        if (isset($fuertewp['general']['autoupdate_translations']) && true === $fuertewp['general']['autoupdate_translations']) {
            add_filter('autoupdate_translations', '__return_true', FUERTEWP_LATE_PRIORITY);
        }

        // Trigger WordPress auto-update process
        wp_maybe_auto_update();

        // Log completion
        if (function_exists('write_log')) {
            write_log('Fuerte-WP trigger_updates completed at ' . date('Y-m-d H:i:s'));
        }
    }
} // Class Fuerte_Wp_Auto_Update_Manager
