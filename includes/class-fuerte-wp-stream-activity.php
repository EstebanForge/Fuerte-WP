<?php

/**
 * Stream activity integration.
 *
 * Surfaces security-relevant events from the Stream plugin log
 * (xwp/stream) as a Fuerte-WP admin tab and a scheduled digest email.
 * All event data comes from Stream's own tables; logins and failed
 * logins come from Fuerte_Wp_Login_Logger, which Fuerte-WP captures
 * independently of Stream.
 */

// No access outside WP.
defined('ABSPATH') || die();

class Fuerte_Wp_Stream_Activity
{
    /**
     * Stream table (prefixed with $wpdb->base_prefix at query time).
     *
     * @since 1.14.0
     */
    public const STREAM_TABLE = 'stream';

    /**
     * Digest cron schedule names.
     *
     * @since 1.14.0
     */
    public const SCHEDULE_DAILY = 'fuertewp_stream_digest_daily';
    public const SCHEDULE_WEEKLY = 'fuertewp_stream_digest_weekly';
    public const SCHEDULE_MONTHLY = 'fuertewp_stream_digest_monthly';

    /**
     * Valid digest frequencies.
     *
     * @since 1.14.0
     *
     * @var array
     */
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /**
     * Digest section labels, keyed by section name.
     *
     * @since 1.14.0
     *
     * @var array
     */
    public const SECTION_LABELS = [
        'updates' => 'Updates applied',
        'lifecycle' => 'Plugin and theme activity',
        'new_admins' => 'New administrator accounts',
        'admin_passwords' => 'Administrator password changes',
        'logins' => 'Successful logins',
        'failed_logins' => 'Failed logins',
    ];

    /**
     * Boot the integration.
     *
     * Called procedurally at fuerte-wp.php include time. Hook timing is
     * load-bearing:
     * - Stream loads its classes at plugins_loaded 20 and instantiates its
     *   Connectors at init 9 (where the wp_stream_connectors filter is
     *   applied), so our connector filter must be added at plugins_loaded 30.
     * - Digest cron runners cannot go through Fuerte_Wp_Hook_Manager because
     *   it only initializes inside Fuerte_Wp_Enforcer::run(), which exits
     *   early on foreign cron events.
     *
     * @since 1.14.0
     */
    public static function boot()
    {
        add_action('plugins_loaded', [__CLASS__, 'register_connector'], 30);
        add_filter('cron_schedules', [__CLASS__, 'add_monthly_schedule']);
        add_action('init', [__CLASS__, 'schedule_digest'], 20);
        add_action(self::SCHEDULE_DAILY, [__CLASS__, 'run_daily_digest']);
        add_action(self::SCHEDULE_WEEKLY, [__CLASS__, 'run_weekly_digest']);
        add_action(self::SCHEDULE_MONTHLY, [__CLASS__, 'run_monthly_digest']);
    }

    /**
     * Register a monthly recurrence for WP-Cron (core ships none).
     *
     * @since 1.14.0
     *
     * @param array $schedules Existing cron schedules.
     *
     * @return array
     */
    public static function add_monthly_schedule($schedules)
    {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Once Monthly', 'fuerte-wp'),
        ];

        return $schedules;
    }

    /**
     * Whether the Stream plugin is loaded (installed and active).
     *
     * @since 1.14.0
     *
     * @return bool
     */
    public static function is_active()
    {
        return class_exists('WP_Stream\Plugin');
    }

    /**
     * Register the Fuerte-WP connector with Stream.
     *
     * Runs at plugins_loaded 30: Stream is loaded, but its Connector parent
     * class is only included later inside load_connectors() (init 9). So this
     * only attaches the filter; add_connector() loads the connector file at
     * apply time, when the parent class is guaranteed to exist.
     *
     * @since 1.14.0
     */
    public static function register_connector()
    {
        if (!self::is_active()) {
            return;
        }

        add_filter('wp_stream_connectors', [__CLASS__, 'add_connector']);
    }

    /**
     * Append the Fuerte-WP connector to Stream's connector list.
     *
     * Master switch: fuerte option changes are not logged until the user
     * enables activity monitoring in the Activity tab.
     *
     * @since 1.14.0
     *
     * @param array $instances Stream connector instances.
     *
     * @return array
     */
    public static function add_connector($instances)
    {
        if (!self::digest_settings()['enable']) {
            return $instances;
        }

        require_once FUERTEWP_PATH . 'includes/class-fuerte-wp-stream-connector.php';

        $instances[] = new Fuerte_Wp_Stream_Connector();

        return $instances;
    }

    /**
     * Reschedule (or clear) the digest cron events.
     *
     * Always clears all schedules first: when Stream deactivates or the
     * master switch turns off, stale events must go, not linger firing into
     * a gate that can never pass.
     *
     * @since 1.14.0
     */
    public static function schedule_digest()
    {
        if (!self::is_active()) {
            return;
        }

        $settings = self::digest_settings();

        // Master off: clear anything scheduled, then stop.
        if (!$settings['enable']) {
            self::clear_digest_schedules();

            return;
        }

        switch ($settings['frequency']) {
            case 'monthly':
                $hook = self::SCHEDULE_MONTHLY;
                $recurrence = 'monthly';
                $target = strtotime('first day of next month 00:00');
                break;
            case 'daily':
                $hook = self::SCHEDULE_DAILY;
                $recurrence = 'daily';
                $target = strtotime('tomorrow 00:00');
                break;
            default:
                $hook = self::SCHEDULE_WEEKLY;
                $recurrence = 'weekly';
                $target = strtotime('next sunday 00:00');
        }

        $scheduled = wp_next_scheduled($hook);

        // Already correct: steady state, zero cron writes per request.
        if ($scheduled === $target) {
            return;
        }

        // A due-but-unfired event must be left alone: on Sundays daytime,
        // strtotime('next sunday 00:00') points at NEXT week, so clearing here
        // would silently skip this week's digest. Recurring events that fire
        // re-align themselves with $target via the drift check below.
        if ($scheduled !== false && $scheduled <= time()) {
            return;
        }

        self::clear_digest_schedules();
        wp_schedule_event($target, $recurrence, $hook);
    }

    /**
     * Remove all digest cron events.
     *
     * @since 1.14.0
     */
    private static function clear_digest_schedules()
    {
        wp_clear_scheduled_hook(self::SCHEDULE_DAILY);
        wp_clear_scheduled_hook(self::SCHEDULE_WEEKLY);
        wp_clear_scheduled_hook(self::SCHEDULE_MONTHLY);
    }

    /**
     * Digest settings from the normalized config.
     *
     * @since 1.14.0
     *
     * @return array{enable: bool, frequency: string, email: string}
     */
    public static function digest_settings()
    {
        $config = Fuerte_Wp_Config::get_config();
        $activity = $config['activity'] ?? [];

        $frequency = $activity['stream_digest_frequency'] ?? 'weekly';

        if (!in_array($frequency, self::FREQUENCIES, true)) {
            $frequency = 'weekly';
        }

        return [
            'enable' => (bool) ($activity['stream_enable'] ?? false),
            'frequency' => $frequency,
            'email' => trim($activity['stream_digest_email'] ?? ''),
        ];
    }

    /**
     * Window bounds covered by a digest run.
     *
     * Boundaries are computed in UTC; fetch_section() converts them to the
     * site timezone before querying Stream's created column. Monthly covers
     * the previous calendar month.
     *
     * @since 1.14.0
     *
     * @param string $frequency One of the FREQUENCIES values.
     * @param ?int $now Reference timestamp (defaults to current time).
     *
     * @return array{0: int, 1: int} [start, end] timestamps.
     */
    public static function digest_window($frequency = 'daily', $now = null)
    {
        $now ??= time();

        if ('monthly' === $frequency) {
            return [
                strtotime('first day of previous month 00:00', $now),
                strtotime('last day of previous month 23:59:59', $now),
            ];
        }

        $end = strtotime('today 00:00', $now) - 1;
        $start = strtotime(('weekly' === $frequency ? '-7 days 00:00' : '-1 day 00:00'), $now);

        return [$start, $end];
    }

    /**
     * Query contract for one digest section against Stream's table.
     *
     * Pure builder: returns WHERE fragments and bound params, so tests can
     * assert the category mapping without a database. Connector, context and
     * action values mirror xwp/stream connectors (installer, users).
     *
     * @since 1.14.0
     *
     * @param string $section Section name.
     * @param string $start Local datetime string (inclusive).
     * @param string $end Local datetime string (inclusive).
     *
     * @return array{where: string[], params: string[]}
     */
    public static function section_query($section, $start, $end)
    {
        $where = ['created >= %s', 'created <= %s'];
        $params = [$start, $end];

        switch ($section) {
            case 'updates':
                // installer::updated covers plugin, theme and WordPress core updates
                // (core updates are logged with context "WordPress", capital W).
                $where[] = 'connector = %s';
                $params[] = 'installer';
                $where[] = 'action = %s';
                $params[] = 'updated';
                break;

            case 'lifecycle':
                $where[] = 'connector = %s';
                $params[] = 'installer';
                $where[] = 'action IN (' . implode(', ', array_fill(0, 4, '%s')) . ')';
                array_push($params, 'installed', 'activated', 'deactivated', 'deleted');
                $where[] = 'context IN (' . implode(', ', array_fill(0, 2, '%s')) . ')';
                array_push($params, 'plugins', 'themes');
                break;

            case 'new_admins':
                // users::created with a role list mentioning Administrator. The
                // user_role column holds the ACTING user's role here, so the
                // created account's role must be read from stream_meta.
                $where[] = 'connector = %s';
                $params[] = 'users';
                $where[] = 'action = %s';
                $params[] = 'created';
                $where[] = 'EXISTS (SELECT 1 FROM {meta_table} m WHERE m.record_id = s.ID AND m.meta_key = %s AND m.meta_value LIKE %s)';
                array_push($params, 'roles', '%Administrator%');
                break;

            case 'admin_passwords':
                // users::password-reset: the actor IS the user on this event,
                // so user_role carries the role of the reset account.
                $where[] = 'connector = %s';
                $params[] = 'users';
                $where[] = 'action = %s';
                $params[] = 'password-reset';
                $where[] = 'user_role = %s';
                $params[] = 'administrator';
                break;

            default:
                return ['where' => [], 'params' => []];
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Fetch one section's events from Stream's table.
     *
     * @since 1.14.0
     *
     * @param string $section Section name.
     * @param int $start Window start timestamp.
     * @param int $end Window end timestamp.
     *
     * @return array Normalized rows: [summary, user, created].
     */
    public static function fetch_section($section, $start, $end)
    {
        global $wpdb;

        $query = self::section_query(
            $section,
            wp_date('Y-m-d H:i:s', $start),
            wp_date('Y-m-d H:i:s', $end)
        );

        if (empty($query['where'])) {
            return [];
        }

        $sql = 'SELECT s.ID, s.summary, s.user_id, s.user_role, s.created, s.context, s.action, s.ip, s.object_id'
            . ' FROM ' . $wpdb->base_prefix . self::STREAM_TABLE . ' AS s'
            . ' WHERE ' . implode(' AND ', $query['where'])
            . ' ORDER BY s.created ASC LIMIT 200';

        $sql = str_replace('{meta_table}', $wpdb->base_prefix . self::STREAM_TABLE . '_meta', $sql);

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$query['params']), ARRAY_A);

        return array_map([__CLASS__, 'normalize_stream_row'], is_array($rows) ? $rows : []);
    }

    /**
     * Normalize one Stream row into the digest row shape.
     *
     * @since 1.14.0
     *
     * @param array $row Raw row (ARRAY_A).
     *
     * @return array
     */
    protected static function normalize_stream_row($row)
    {
        $user = '';

        if (!empty($row['user_id'])) {
            $actor = get_user_by('id', (int) $row['user_id']);
            $user = $actor ? $actor->display_name : (string) $row['user_id'];
        }

        return [
            'summary' => (string) ($row['summary'] ?? ''),
            'user' => $user,
            'created' => (string) ($row['created'] ?? ''),
        ];
    }

    /**
     * Fetch login attempts from Fuerte-WP's own table.
     *
     * Test seam: unit tests subclass and override.
     *
     * @since 1.14.0
     *
     * @param array $args Arguments for Fuerte_Wp_Login_Logger::get_attempts().
     *
     * @return array
     */
    protected function fetch_login_attempts($args)
    {
        $logger = new Fuerte_Wp_Login_Logger();

        return $logger->get_attempts($args);
    }

    /**
     * Collect all digest sections between two timestamps.
     *
     * @since 1.14.0
     *
     * @param int $start Window start timestamp.
     * @param int $end Window end timestamp.
     *
     * @return array Section name => rows.
     */
    public function collect_sections($start, $end)
    {
        $sections = [];

        foreach (['updates', 'lifecycle', 'new_admins', 'admin_passwords'] as $section) {
            $sections[$section] = self::fetch_section($section, $start, $end);
        }

        $local_start = wp_date('Y-m-d H:i:s', $start);
        $local_end = wp_date('Y-m-d H:i:s', $end);

        $sections['logins'] = self::filter_by_status(
            $this->fetch_login_attempts([
                'status' => 'success',
                'date_from' => $local_start,
                'date_to' => $local_end,
                'limit' => 200,
            ]),
            'success'
        );

        $sections['failed_logins'] = self::filter_by_status(
            $this->fetch_login_attempts([
                'status' => 'failed',
                'date_from' => $local_start,
                'date_to' => $local_end,
                'limit' => 200,
            ]),
            'failed'
        );

        return $sections;
    }

    /**
     * Keep only attempts with the given status.
     *
     * @since 1.14.0
     *
     * @param array $rows Rows (objects or arrays) from the attempts table.
     * @param string $status Status to keep.
     *
     * @return array
     */
    protected static function filter_by_status($rows, $status)
    {
        $kept = [];

        foreach ((array) $rows as $row) {
            $array = (array) $row;

            if (($array['status'] ?? '') === $status) {
                $kept[] = [
                    'summary' => sprintf(
                        '%s logged in from %s',
                        (string) ($array['username'] ?? ''),
                        (string) ($array['ip_address'] ?? ($array['ip'] ?? ''))
                    ),
                    'user' => (string) ($array['username'] ?? ''),
                    'username' => (string) ($array['username'] ?? ''),
                    'ip' => (string) ($array['ip_address'] ?? ($array['ip'] ?? '')),
                    'created' => (string) ($array['attempt_time'] ?? ($array['created'] ?? '')),
                ];
            }
        }

        return $kept;
    }

    /**
     * Build the digest email body.
     *
     * @since 1.14.0
     *
     * @param array $sections Section name => rows.
     * @param string $date Display date or range label.
     *
     * @return string HTML body.
     */
    public static function build_digest($sections, $date)
    {
        $body = '<h2>' . esc_html(sprintf('Activity digest - %s', $date)) . '</h2>';
        $has_events = false;

        foreach (self::SECTION_LABELS as $section => $label) {
            $rows = $sections[$section] ?? [];

            if (empty($rows)) {
                continue;
            }

            $has_events = true;

            $body .= '<h3>' . esc_html(sprintf('%s (%d)', $label, count($rows))) . '</h3><ul>';

            foreach ($rows as $row) {
                $item = (string) ($row['summary'] ?? '');

                if ('' === $item) {
                    $item = trim((string) ($row['user'] ?? ''));
                }

                $line = esc_html($item);

                if (!empty($row['user']) && '' !== trim($item)) {
                    $line .= ' (' . esc_html((string) $row['user']) . ')';
                }

                if (!empty($row['created'])) {
                    $line .= ' - ' . esc_html((string) $row['created']);
                }

                $body .= '<li>' . $line . '</li>';
            }

            $body .= '</ul>';
        }

        if (!$has_events) {
            $body .= '<p>' . esc_html(sprintf('No activity recorded for %s.', $date)) . '</p>';
        }

        return $body;
    }

    /**
     * Cron callback: send the daily digest.
     *
     * Gates on Stream being active at fire time: Stream can be deactivated
     * between scheduling and the event firing.
     *
     * @since 1.14.0
     */
    public function run_daily_digest()
    {
        if (!self::is_active()) {
            return;
        }

        $this->send_digest('daily');
    }

    /**
     * Cron callback: send the weekly digest.
     *
     * @since 1.14.0
     */
    public function run_weekly_digest()
    {
        if (!self::is_active()) {
            return;
        }

        $this->send_digest('weekly');
    }

    /**
     * Cron callback: send the monthly digest.
     *
     * @since 1.14.0
     */
    public function run_monthly_digest()
    {
        if (!self::is_active()) {
            return;
        }

        $this->send_digest('monthly');
    }

    /**
     * Build and send one digest email.
     *
     * @since 1.14.0
     *
     * @param string $frequency Digest frequency window.
     *
     * @return bool Whether the email was sent.
     */
    public function send_digest($frequency = 'daily')
    {
        $settings = self::digest_settings();

        if (!$settings['enable']) {
            return false;
        }

        [$start, $end] = self::digest_window($frequency);

        $sections = $this->collect_sections($start, $end);

        $date = wp_date('Y-m-d', $start);

        $to = '' !== $settings['email'] ? $settings['email'] : get_option('admin_email');

        $subject = sprintf(
            '[%s] Activity digest - %s',
            get_bloginfo('name'),
            $date
        );

        return (bool) wp_mail(
            $to,
            $subject,
            self::build_digest($sections, $date),
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
