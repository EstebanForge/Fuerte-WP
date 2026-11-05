<?php

/**
 * Migration Utility for Fuerte-WP.
 *
 * Migrates data from Carbon Fields individual options to HyperFields single array storage.
 *
 * @since      1.8.0
 */

// No access outside WP
defined('ABSPATH') || die();

class Fuerte_Wp_Migrator
{
    /**
     * Bump when a new legacy storage shape needs a one-time pass.
     */
    public const MIGRATION_VERSION = '1';

    /**
     * Autoloaded marker: one in-memory get_option gates the whole pass.
     */
    public const MIGRATED_OPTION = 'fuertewp_legacy_migrated';

    /**
     * get_option() returns false both for missing options and for stored
     * false values; this default disambiguates them so a legacy "off" toggle
     * is preserved instead of dropped.
     */
    private const OPTION_MISSING = '__fuertewp_option_missing__';

    /**
     * Run the migration from legacy options to HyperFields.
     *
     * One-time per site: the sentinel option gates the pass, and scanned
     * legacy rows are deleted afterwards, so steady-state requests pay one
     * in-memory get_option. Idempotent across crash windows: the fold skips
     * keys HyperFields already holds, and nothing is deleted unless the
     * settings write succeeded, so any interrupted or failed pass recovers
     * on the next request without overwriting newer saved values.
     *
     * @return array Migration results
     */
    public static function migrate()
    {
        if (get_option(self::MIGRATED_OPTION) === self::MIGRATION_VERSION) {
            return [
                'migrated_keys' => 0,
                'settings_updated' => false,
                'skipped' => true,
            ];
        }

        $settings = get_option('fuertewp_settings', []);
        $migrated_count = 0;
        $legacy_found = false;

        // List of all keys to migrate (without prefix)
        $keys = [
            'super_users',
            'access_denied_message',
            'recovery_email',
            'sender_email_enable',
            'sender_email',
            'autoupdate_core',
            'autoupdate_plugins',
            'autoupdate_themes',
            'autoupdate_translations',
            'autoupdate_frequency',
            'login_enable',
            'registration_enable',
            'login_max_attempts',
            'login_lockout_duration',
            'login_increasing_lockout',
            'login_ip_headers',
            'login_gdpr_message',
            'login_data_retention',
            'login_url_hiding_enabled',
            'custom_login_slug',
            'login_url_type',
            'redirect_invalid_logins',
            'redirect_invalid_logins_url',
            'restrictions_restapi_loggedin_only',
            'restrictions_restapi_disable_app_passwords',
            'restrictions_disable_xmlrpc',
            'restrictions_htaccess_security_rules',
            'restrictions_disable_admin_create_edit',
            'restrictions_disable_weak_passwords',
            'restrictions_force_strong_passwords',
            'restrictions_disable_admin_bar_roles',
            'restrictions_restrict_permalinks',
            'restrictions_restrict_acf',
            'restrictions_disable_theme_editor',
            'restrictions_disable_plugin_editor',
            'restrictions_disable_theme_install',
            'restrictions_disable_plugin_install',
            'restrictions_disable_customizer_css',
            'emails_fatal_error',
            'emails_automatic_updates',
            'emails_comment_awaiting_moderation',
            'emails_comment_has_been_published',
            'emails_user_reset_their_password',
            'emails_user_confirm_personal_data_export_request',
            'emails_new_user_created',
            'emails_network_new_site_created',
            'emails_network_new_user_site_registered',
            'emails_network_new_site_activated',
            'tweaks_use_site_logo_login',
            'restricted_scripts',
            'restricted_pages',
            'removed_menus',
            'removed_submenus',
            'removed_adminbar_menus',
            'deferred_plugins',
            'deferred_themes',
            'blocked_plugins',
            'blocked_themes',
        ];

        // Multiselect keys that used Carbon Fields pattern
        $multiselect_keys = [
            'super_users',
            'restrictions_disable_admin_bar_roles',
            'restricted_scripts',
            'restricted_pages',
            'removed_menus',
            'removed_submenus',
            'removed_adminbar_menus',
            'deferred_plugins',
            'deferred_themes',
            'blocked_plugins',
            'blocked_themes',
        ];

        $seen_legacy = [];

        foreach ($keys as $key) {
            $hf_key = "fuertewp_{$key}";
            $already_present = isset($settings[$hf_key]);
            $is_multiselect = in_array($key, $multiselect_keys, true);

            $value = get_option("_fuertewp_{$key}", self::OPTION_MISSING);

            if ($value !== self::OPTION_MISSING) {
                $seen_legacy[] = "_fuertewp_{$key}";
            }

            if ($is_multiselect) {
                $row_names = [];
                $row_values = self::load_legacy_multiselect("fuertewp_{$key}", $row_names);

                foreach ($row_names as $row_name) {
                    $seen_legacy[] = $row_name;
                }

                if ($row_values !== null) {
                    // Rows are the source of truth; the single option is only
                    // a dual-write artifact and is never folded.
                    $value = $row_values;
                } else {
                    // Singles for multiselect keys were never a read path.
                    $value = self::OPTION_MISSING;
                }
            }

            // Only migrate if not already in HyperFields
            if ($already_present) {
                continue;
            }

            if ($value !== self::OPTION_MISSING && $value !== null) {
                $settings[$hf_key] = $value;
                $migrated_count++;
                $legacy_found = true;
            }
        }

        $saved = true;

        if ($legacy_found) {
            $saved = update_option('fuertewp_settings', $settings);

            if ($saved) {
                Fuerte_Wp_Config::invalidate_cache();
            }
        }

        // Nothing is deleted unless the fold actually persisted: a failed
        // settings write must leave every legacy row in place for the retry
        // on the next request.
        if ($saved) {
            foreach ($seen_legacy as $option_name) {
                delete_option($option_name);
            }

            update_option(self::MIGRATED_OPTION, self::MIGRATION_VERSION, true);
        }

        return [
            'migrated_keys' => $saved ? $migrated_count : 0,
            'settings_updated' => $saved && $legacy_found,
            'skipped' => false,
        ];
    }

    /**
     * Load multiselect values from legacy storage.
     *
     * Two shapes existed:
     * - The plugin's own Carbon-compatible rows: _{field}|||{index}|value
     * - Raw Carbon Fields 3.x complex keys:
     *   _carbon_fields_theme_options_fuerte-wp|{field}|{group}|value
     *
     * Both shapes are always queried: sites that moved shim-era rows into CF3
     * storage can hold both, shape 1 wins for values, and every fetched row
     * name (including CF companion rows like |_type) lands in $row_names so
     * migrate() deletes the whole legacy set.
     */
    private static function load_legacy_multiselect($field_name, array &$row_names = [])
    {
        global $wpdb;

        $values = [];
        $cf3_values = [];

        // Shape 1: plugin's Carbon-compatible rows.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             ORDER BY option_name ASC",
            '_' . $field_name . '|||%'
        ));

        foreach ((array) $rows as $row) {
            $row_names[] = $row->option_name;

            if (preg_match('/\|\|\|(\d+)\|value$/', $row->option_name, $matches)) {
                $values[intval($matches[1])] = $row->option_value;
            }
        }

        // Shape 2: raw Carbon Fields 3.x complex keys.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             ORDER BY option_name ASC",
            '_carbon_fields_theme_options_fuerte-wp|' . $field_name . '|%'
        ));

        foreach ((array) $rows as $row) {
            $row_names[] = $row->option_name;

            if (preg_match('/\|(\d+)\|value$/', $row->option_name, $matches)) {
                $cf3_values[intval($matches[1])] = $row->option_value;
            }
        }

        if (empty($values)) {
            $values = $cf3_values;
        }

        if (empty($values)) {
            return null;
        }

        ksort($values);

        return array_values($values);
    }
}
