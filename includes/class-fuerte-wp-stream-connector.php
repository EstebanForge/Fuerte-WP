<?php

/**
 * Stream connector for Fuerte-WP settings changes.
 *
 * Stream's settings connector only tracks options saved through
 * options.php or WP-CLI; plugin settings pages saving their option
 * array directly are invisible to it. This connector hooks
 * update_option_fuertewp_settings (fired by HyperFields' single
 * update_option() on save) so Fuerte-WP configuration changes appear
 * in Stream's log.
 *
 * Loaded only when Stream is active (see
 * Fuerte_Wp_Stream_Activity::register_connector()).
 */

// No access outside WP.
defined('ABSPATH') || die();

use WP_Stream\Connector;

class Fuerte_Wp_Stream_Connector extends Connector
{
    /**
     * Connector slug shown in Stream's UI.
     *
     * @since 1.14.0
     *
     * @var string
     */
    public $name = 'fuerte-wp';

    /**
     * Actions registered with Stream.
     *
     * HyperFields persists the whole admin page as one array option, so a
     * single dynamic hook covers every settings save.
     *
     * @since 1.14.0
     *
     * @var array
     */
    public $actions = [
        'update_option_fuertewp_settings',
    ];

    /**
     * Register in every context (admin, frontend, CLI, cron, REST): the hook
     * is update_option_fuertewp_settings, which only fires on actual option
     * changes — CLI writes (wp option update) must be logged too, and Stream
     * skips connectors with register_frontend=false outside is_admin().
     *
     * @since 1.14.0
     *
     * @var bool
     */
    public $register_frontend = true;

    /**
     * Translated connector label.
     *
     * @since 1.14.0
     *
     * @return string
     */
    public function get_label()
    {
        return esc_html__('Fuerte-WP', 'fuerte-wp');
    }

    /**
     * Translated context labels.
     *
     * @since 1.14.0
     *
     * @return array
     */
    public function get_context_labels()
    {
        return [
            'settings' => esc_html__('Settings', 'fuerte-wp'),
        ];
    }

    /**
     * Translated action labels.
     *
     * @since 1.14.0
     *
     * @return array
     */
    public function get_action_labels()
    {
        return [
            'updated' => esc_html__('Updated', 'fuerte-wp'),
        ];
    }

    /**
     * Log a Fuerte-WP settings change.
     *
     * @since 1.14.0
     *
     * @action update_option_fuertewp_settings
     *
     * @param mixed $old_value Previous option value.
     * @param mixed $new_value New option value.
     *
     * @return bool
     */
    public function callback_update_option_fuertewp_settings($old_value, $new_value)
    {
        if (!is_array($old_value) || !is_array($new_value)) {
            return false;
        }

        $changed_keys = $this->get_changed_keys($old_value, $new_value);

        if (empty($changed_keys)) {
            return false;
        }

        $this->log(
            __('Fuerte-WP settings updated', 'fuerte-wp'),
            [
                'changed_keys' => $changed_keys,
                'changed_count' => count($changed_keys),
            ],
            null,
            'settings',
            'updated'
        );

        return true;
    }
}
