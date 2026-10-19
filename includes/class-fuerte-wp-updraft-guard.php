<?php

/**
 * UpdraftCentral kill switch.
 *
 * Mitigation for the 2026-10-19 TeamUpdraft connection-key theft campaign.
 * Disables the UpdraftCentral remote-control client inside UpdraftPlus: the
 * client machinery never boots, udrpc-shaped requests are denied at the
 * door, and connection keys are unreadable even if a future version boots
 * the machinery anyway. Backups keep working; remote control, migration
 * receiving, and UpdraftPlus Vault disconnects die.
 *
 * @link       https://actitud.xyz
 * @since      1.14.0
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

class Fuerte_Wp_Updraft_Guard
{
    /**
     * Options holding UpdraftCentral connection keys. Keys never expire
     * vendor-side and a stolen key equals admin-level command execution, so
     * reads are blinded. updraft_central_keys is absent from UpdraftPlus
     * 1.26.11; it covers the standalone UpdraftCentral plugin and legacy
     * installs.
     *
     * @since 1.14.0
     *
     * @var string[]
     */
    private const KEY_OPTIONS = [
        'updraft_central_localkeys',
        'updraft_migrator_localkeys',
        'updraft_central_keys',
    ];

    /**
     * Arm the kill switch. Runs at plugin-file include time so every filter
     * exists before plugins_loaded priority 10, where UpdraftPlus attaches
     * its Central host and the factory boots the client. The earliest a
     * normal plugin can act; only mu-plugins or wp-config.php beat it.
     *
     * @since 1.14.0
     */
    public static function boot(): void
    {
        if (!self::enabled()) {
            return;
        }

        if (self::is_udrpc_request()) {
            http_response_code(403);
            exit;
        }

        self::arm_filters();
    }

    /**
     * Detect a udrpc-shaped request before UpdraftPlus boots any listener.
     *
     * @since 1.14.0
     *
     * @return bool
     */
    public static function is_udrpc_request(): bool
    {
        if (!empty($_POST['udrpc_message'])) {
            return true;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return is_string($user_agent) && strpos($user_agent, 'class-udrpc.php') !== false;
    }

    /**
     * Whether the kill switch is enabled. File config wins over database,
     * mirroring Fuerte_Wp_Config::get_config() semantics. Default: on.
     *
     * @since 1.14.0
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        global $fuertewp;

        if (!empty($fuertewp) && is_array($fuertewp)) {
            return (bool) ($fuertewp['restrictions']['block_updraftcentral'] ?? true);
        }

        $settings = get_option('fuertewp_settings', []);

        return (bool) ($settings['fuertewp_restrictions_block_updraftcentral'] ?? true);
    }

    /**
     * Register the request-deny and key-blinding filters.
     *
     * @since 1.14.0
     */
    private static function arm_filters(): void
    {
        // UpdraftPlus attaches its Central host at plugins_loaded priority 10,
        // then the factory applies this filter and boots the client. Wiping
        // the list at 9999, inside the same apply_filters call, means
        // central/bootstrap.php is never included: no listeners, no ajax
        // endpoints, no autologin.
        add_filter('updraftcentral_host_plugins', '__return_empty_array', FUERTEWP_LATE_PRIORITY);

        foreach (self::KEY_OPTIONS as $key_option) {
            // Late priority: the blinding must outlast any filter a hostile
            // plugin registers at the default priority.
            add_filter("pre_option_{$key_option}", '__return_empty_array', FUERTEWP_LATE_PRIORITY);
            add_filter("pre_site_option_{$key_option}", '__return_empty_array', FUERTEWP_LATE_PRIORITY);

            // Blinding reads alone leaves write paths live: a key stored while
            // the guard is on would revive if the guard is later removed.
            add_filter("pre_update_option_{$key_option}", '__return_empty_array', FUERTEWP_LATE_PRIORITY);
            add_filter("pre_update_site_option_{$key_option}", '__return_empty_array', FUERTEWP_LATE_PRIORITY);
        }

        // The UpdraftPlus_Options abstraction and the multisite addon store
        // keys in their own bucket (updraftplus_options), unreachable by
        // pre_option; both read paths end in this filter.
        add_filter('updraftplus_get_option', [self::class, 'blind_key_option'], FUERTEWP_LATE_PRIORITY, 2);
    }

    /**
     * Blind UpdraftCentral connection keys on read so no remote listener can
     * arm and stolen or silently re-created keys stay inert.
     *
     * @since 1.14.0
     *
     * @param mixed $value Current option value.
     * @param mixed $option Option name.
     *
     * @return mixed
     */
    public static function blind_key_option($value, $option)
    {
        return in_array($option, self::KEY_OPTIONS, true) ? [] : $value;
    }
}
