<?php

/**
 * Main Enfocer class
 *
 * @link       https://actitud.xyz
 * @since      1.3.0
 *
 * @package    Fuerte_Wp
 * @subpackage Fuerte_Wp/includes
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * Main Fuerte-WP Class
 */
class Fuerte_Wp_Enforcer
{
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	public $pagenow;
	public $fuertewp;
	public $current_user;
	public $config;
	/**
	 * Auto-update manager instance
	 * @var Fuerte_Wp_Auto_Update_Manager|null
	 */
	public $auto_update_manager = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		//$this->config = $this->config_setup();
	}

	/**
	 * Access this plugin instance
	 */
	public static function get_instance()
	{
		/**
		 * To run like:
		 * add_action( 'plugins_loaded', [ Fuerte_Wp_Enforcer::get_instance(), 'init' ] );
		 */
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Init the plugin
	 */
	public function run()
	{
		// Initialize auto-update manager
		$this->auto_update_manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

		$this->enforcer();
	}

	/**
	 * Get cached configuration section with granular caching
	 */
	private function get_cached_config_section($section, $callback, $expire = DAY_IN_SECONDS)
	{
		$cache_key = 'fuertewp_' . $section . '_' . FUERTEWP_VERSION;
		$value = wp_cache_get($cache_key, 'fuertewp');

		if (false === $value) {
			$value = call_user_func($callback);
			wp_cache_set($cache_key, $value, 'fuertewp', $expire);
		}

		return $value;
	}

	/**
	 * Get processed list from cached data
	 */
	private function get_processed_list($raw_data)
	{
		if (empty($raw_data)) {
			return [];
		}

		return array_map('trim', explode(PHP_EOL, $raw_data));
	}

	/**
	 * Get configuration options using batch queries for better performance
	 */
	private function get_config_options_batch()
	{
		global $wpdb;

		// Define all option names we need to retrieve
		$option_names = [
			'fuertewp_status',
			'fuertewp_super_users',
			'fuertewp_access_denied_message',
			'fuertewp_recovery_email',
			'fuertewp_sender_email_enable',
			'fuertewp_sender_email',
			'fuertewp_autoupdate_core',
			'fuertewp_autoupdate_plugins',
			'fuertewp_autoupdate_themes',
			'fuertewp_autoupdate_translations',
			'fuertewp_autoupdate_frequency',
			'fuertewp_tweaks_use_site_logo_login',
			'fuertewp_emails_fatal_error',
			'fuertewp_emails_automatic_updates',
			'fuertewp_emails_comment_awaiting_moderation',
			'fuertewp_emails_comment_has_been_published',
			'fuertewp_emails_user_reset_their_password',
			'fuertewp_emails_user_confirm_personal_data_export_request',
			'fuertewp_emails_new_user_created',
			'fuertewp_emails_network_new_site_created',
			'fuertewp_emails_network_new_user_site_registered',
			'fuertewp_emails_network_new_site_activated',
			'fuertewp_restrictions_restapi_loggedin_only',
			'fuertewp_restrictions_restapi_disable_app_passwords',
			'fuertewp_restrictions_disable_xmlrpc',
			'fuertewp_restrictions_htaccess_security_rules',
			'fuertewp_restrictions_disable_admin_create_edit',
			'fuertewp_restrictions_disable_weak_passwords',
			'fuertewp_restrictions_force_strong_passwords',
			'fuertewp_restrictions_disable_admin_bar_roles',
			'fuertewp_restrictions_restrict_permalinks',
			'fuertewp_restrictions_restrict_acf',
			'fuertewp_restrictions_disable_theme_editor',
			'fuertewp_restrictions_disable_plugin_editor',
			'fuertewp_restrictions_disable_theme_install',
			'fuertewp_restrictions_disable_plugin_install',
			'fuertewp_restrictions_disable_customizer_css',
			'fuertewp_restricted_scripts',
			'fuertewp_restricted_pages',
			'fuertewp_removed_menus',
			'fuertewp_removed_submenus',
			'fuertewp_removed_adminbar_menus'
		];

		// Create placeholders for prepared statement
		$placeholders = implode(',', array_fill(0, count($option_names), '%s'));

		// Batch query to get all options at once
		$query = $wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
			$option_names
		);

		$results = $wpdb->get_results($query, ARRAY_A);

		// Convert to associative array for easy access
		$options = [];
		foreach ($results as $row) {
			$options[$row['option_name']] = $row['option_value'];
		}

		return $options;
	}

	/**
	 * Config Setup
	 */
	private function config_setup()
	{
		global $fuertewp, $current_user;

		// Try to get from file config first (highest priority)
		if (file_exists(ABSPATH . 'wp-config-fuerte.php') && is_array($fuertewp) && !empty($fuertewp)) {
			return $fuertewp;
		}

		// If Fuerte-WP hasn't been init yet
		if (!fuertewp_option_exists('_fuertewp_status')) {
			if (!isset($current_user) || empty($current_user)) {
				$current_user = wp_get_current_user();
			}

			// Load default sample config and sets defaults
			$fuertewp_pre = $fuertewp;
			if (file_exists(FUERTEWP_PATH . 'config-sample/wp-config-fuerte.php')) {
				require_once FUERTEWP_PATH . 'config-sample/wp-config-fuerte.php';
				$defaults = $fuertewp;
				$fuertewp = $fuertewp_pre;

				// Only set Carbon Fields options if functions are available
				if (function_exists('carbon_set_theme_option')) {
					$seed_defaults = function () use ($current_user, $defaults, &$fuertewp_pre) {
						// status
						carbon_set_theme_option('fuertewp_status', 'enabled');

						// super_users
						carbon_set_theme_option('fuertewp_super_users', $current_user->user_email);

						// general
						foreach ($defaults['general'] as $key => $value) {
							carbon_set_theme_option('fuertewp_' . $key, $value);
						}

						// tweaks
						foreach ($defaults['tweaks'] as $key => $value) {
							carbon_set_theme_option('fuertewp_tweaks_' . $key, $value);
						}

						// emails
						foreach ($defaults['emails'] as $key => $value) {
							carbon_set_theme_option('fuertewp_emails_' . $key, $value);
						}

						// restrictions
						foreach ($defaults['restrictions'] as $key => $value) {
							carbon_set_theme_option('fuertewp_restrictions_' . $key, $value);
						}

						// restricted_scripts
						$value = implode(PHP_EOL, $defaults['restricted_scripts']);
						carbon_set_theme_option('fuertewp_restricted_scripts', $value);

						// restricted_pages
						$value = implode(PHP_EOL, $defaults['restricted_pages']);
						carbon_set_theme_option('fuertewp_restricted_pages', $value);

						// removed_menus
						$value = implode(PHP_EOL, $defaults['removed_menus']);
						carbon_set_theme_option('fuertewp_removed_menus', $value);

						// removed_submenus
						$value = implode(PHP_EOL, $defaults['removed_submenus']);
						carbon_set_theme_option('fuertewp_removed_submenus', $value);

						// removed_adminbar_menus
						$value = implode(PHP_EOL, $defaults['removed_adminbar_menus']);
						carbon_set_theme_option('fuertewp_removed_adminbar_menus', $value);

						unset($fuertewp_pre, $value);
					};

					if (did_action('carbon_fields_fields_registered')) {
						$seed_defaults();
					} else {
						add_action('carbon_fields_fields_registered', $seed_defaults, 1);
					}
				}

				unset($defaults, $fuertewp_pre);
			}
		}

		// Get options from cache
		$version_to_string = str_replace('.', '', FUERTEWP_VERSION);
		if (false === ($fuertewp = get_transient('fuertewp_cache_config_' . $version_to_string))) {
			// Use batch query to get all options at once
			$options = $this->get_config_options_batch();

			// Extract values from batch results with fallbacks
			$status                    = isset($options['fuertewp_status']) ? $options['fuertewp_status'] : null;

			// general
			$super_users               = isset($options['fuertewp_super_users']) ? $options['fuertewp_super_users'] : null;
			$access_denied_message     = isset($options['fuertewp_access_denied_message']) ? $options['fuertewp_access_denied_message'] : null;
			$recovery_email            = isset($options['fuertewp_recovery_email']) ? $options['fuertewp_recovery_email'] : null;
			$sender_email_enable       = isset($options['fuertewp_sender_email_enable']) ? $options['fuertewp_sender_email_enable'] : null;
			$sender_email              = isset($options['fuertewp_sender_email']) ? $options['fuertewp_sender_email'] : null;
			$autoupdate_core           = isset($options['fuertewp_autoupdate_core']) && $options['fuertewp_autoupdate_core'] == 'yes';
			$autoupdate_plugins        = isset($options['fuertewp_autoupdate_plugins']) && $options['fuertewp_autoupdate_plugins'] == 'yes';
			$autoupdate_themes         = isset($options['fuertewp_autoupdate_themes']) && $options['fuertewp_autoupdate_themes'] == 'yes';
			$autoupdate_translations   = isset($options['fuertewp_autoupdate_translations']) && $options['fuertewp_autoupdate_translations'] == 'yes';
			$autoupdate_frequency     = isset($options['fuertewp_autoupdate_frequency']) ? $options['fuertewp_autoupdate_frequency'] : null;

			// tweaks
			$use_site_logo_login       = isset($options['fuertewp_tweaks_use_site_logo_login']) && $options['fuertewp_tweaks_use_site_logo_login'] == 'yes';

			// emails
			$fatal_error                               = isset($options['fuertewp_emails_fatal_error']) && $options['fuertewp_emails_fatal_error'] == 'yes';
			$automatic_updates                         = isset($options['fuertewp_emails_automatic_updates']) && $options['fuertewp_emails_automatic_updates'] == 'yes';
			$comment_awaiting_moderation               = isset($options['fuertewp_emails_comment_awaiting_moderation']) && $options['fuertewp_emails_comment_awaiting_moderation'] == 'yes';
			$comment_has_been_published                = isset($options['fuertewp_emails_comment_has_been_published']) && $options['fuertewp_emails_comment_has_been_published'] == 'yes';
			$user_reset_their_password                 = isset($options['fuertewp_emails_user_reset_their_password']) && $options['fuertewp_emails_user_reset_their_password'] == 'yes';
			$user_confirm_personal_data_export_request = isset($options['fuertewp_emails_user_confirm_personal_data_export_request']) && $options['fuertewp_emails_user_confirm_personal_data_export_request'] == 'yes';
			$new_user_created                          = isset($options['fuertewp_emails_new_user_created']) && $options['fuertewp_emails_new_user_created'] == 'yes';
			$network_new_site_created                  = isset($options['fuertewp_emails_network_new_site_created']) && $options['fuertewp_emails_network_new_site_created'] == 'yes';
			$network_new_user_site_registered          = isset($options['fuertewp_emails_network_new_user_site_registered']) && $options['fuertewp_emails_network_new_user_site_registered'] == 'yes';
			$network_new_site_activated                = isset($options['fuertewp_emails_network_new_site_activated']) && $options['fuertewp_emails_network_new_site_activated'] == 'yes';

			// REST API
			$restapi_loggedin_only     = isset($options['fuertewp_restrictions_restapi_loggedin_only']) ? $options['fuertewp_restrictions_restapi_loggedin_only'] : null;
			$disable_app_passwords     = isset($options['fuertewp_restrictions_restapi_disable_app_passwords']) && $options['fuertewp_restrictions_restapi_disable_app_passwords'] == 'yes';

			// restrictions
			$disable_xmlrpc            = isset($options['fuertewp_restrictions_disable_xmlrpc']) && $options['fuertewp_restrictions_disable_xmlrpc'] == 'yes';
			$htaccess_security_rules    = isset($options['fuertewp_restrictions_htaccess_security_rules']) && $options['fuertewp_restrictions_htaccess_security_rules'] == 'yes';
			$disable_admin_create_edit = isset($options['fuertewp_restrictions_disable_admin_create_edit']) && $options['fuertewp_restrictions_disable_admin_create_edit'] == 'yes';
			$disable_weak_passwords    = isset($options['fuertewp_restrictions_disable_weak_passwords']) && $options['fuertewp_restrictions_disable_weak_passwords'] == 'yes';
			$force_strong_passwords    = isset($options['fuertewp_restrictions_force_strong_passwords']) && $options['fuertewp_restrictions_force_strong_passwords'] == 'yes';
			$disable_admin_bar_roles   = isset($options['fuertewp_restrictions_disable_admin_bar_roles']) ? $options['fuertewp_restrictions_disable_admin_bar_roles'] : null;
			$restrict_permalinks       = isset($options['fuertewp_restrictions_restrict_permalinks']) ? $options['fuertewp_restrictions_restrict_permalinks'] : null;
			$restrict_acf              = isset($options['fuertewp_restrictions_restrict_acf']) ? $options['fuertewp_restrictions_restrict_acf'] : null;
			$disable_theme_editor      = isset($options['fuertewp_restrictions_disable_theme_editor']) && $options['fuertewp_restrictions_disable_theme_editor'] == 'yes';
			$disable_plugin_editor     = isset($options['fuertewp_restrictions_disable_plugin_editor']) && $options['fuertewp_restrictions_disable_plugin_editor'] == 'yes';
			$disable_theme_install     = isset($options['fuertewp_restrictions_disable_theme_install']) && $options['fuertewp_restrictions_disable_theme_install'] == 'yes';
			$disable_plugin_install    = isset($options['fuertewp_restrictions_disable_plugin_install']) && $options['fuertewp_restrictions_disable_plugin_install'] == 'yes';
			$disable_customizer_css    = isset($options['fuertewp_restrictions_disable_customizer_css']) && $options['fuertewp_restrictions_disable_customizer_css'] == 'yes';

			// restricted_scripts
			$restricted_scripts = $this->get_processed_list(isset($options['fuertewp_restricted_scripts']) ? $options['fuertewp_restricted_scripts'] : '');

			// restricted_pages
			$restricted_pages = $this->get_processed_list(isset($options['fuertewp_restricted_pages']) ? $options['fuertewp_restricted_pages'] : '');

			// removed_menus
			$removed_menus = $this->get_processed_list(isset($options['fuertewp_removed_menus']) ? $options['fuertewp_removed_menus'] : '');

			// removed_submenus
			$removed_submenus = $this->get_processed_list(isset($options['fuertewp_removed_submenus']) ? $options['fuertewp_removed_submenus'] : '');

			// removed_adminbar_menus
			$removed_adminbar_menus = $this->get_processed_list(isset($options['fuertewp_removed_adminbar_menus']) ? $options['fuertewp_removed_adminbar_menus'] : '');

			// Main config array, mimics wp-config-fuerte.php
			$fuertewp = [
				'status'      => $status,
				'super_users' => $super_users,
				'general'     => [
					'access_denied_message'         => $access_denied_message,
					'recovery_email'                => $recovery_email,
					'sender_email_enable'           => $sender_email_enable,
					'sender_email'                  => $sender_email,
					'autoupdate_core'               => $autoupdate_core,
					'autoupdate_plugins'            => $autoupdate_plugins,
					'autoupdate_themes'             => $autoupdate_themes,
					'autoupdate_translations'       => $autoupdate_translations,
					'autoupdate_frequency'          => $autoupdate_frequency,
				],
				'tweaks'     => [
					'use_site_logo_login'           => $use_site_logo_login,
				],
				'rest_api' => [
					'loggedin_only'                 => $restapi_loggedin_only,
					'disable_app_passwords'         => $disable_app_passwords,
				],
				'restrictions' => [
					'disable_xmlrpc'                => $disable_xmlrpc,
					'htaccess_security_rules'       => $htaccess_security_rules,
					'disable_admin_create_edit'     => $disable_admin_create_edit,
					'disable_weak_passwords'        => $disable_weak_passwords,
					'force_strong_passwords'        => $force_strong_passwords,
					'disable_admin_bar_roles'       => $disable_admin_bar_roles,
					'restrict_permalinks'           => $restrict_permalinks,
					'restrict_acf'                  => $restrict_acf,
					'disable_theme_editor'          => $disable_theme_editor,
					'disable_plugin_editor'         => $disable_plugin_editor,
					'disable_theme_install'         => $disable_theme_install,
					'disable_plugin_install'        => $disable_plugin_install,
					'disable_customizer_css'        => $disable_customizer_css,
				],
				'emails' => [
					'fatal_error'                               => $fatal_error,
					'automatic_updates'                         => $automatic_updates,
					'comment_awaiting_moderation'               => $comment_awaiting_moderation,
					'comment_has_been_published'                => $comment_has_been_published,
					'user_reset_their_password'                 => $user_reset_their_password,
					'user_confirm_personal_data_export_request' => $user_confirm_personal_data_export_request,
					'new_user_created'                          => $new_user_created,
					'network_new_site_created'                  => $network_new_site_created,
					'network_new_user_site_registered'          => $network_new_user_site_registered,
					'network_new_site_activated'                => $network_new_site_activated,
				],
				'restricted_scripts'     => $restricted_scripts,
				'restricted_pages'       => $restricted_pages,
				'removed_menus'          => $removed_menus,
				'removed_submenus'       => $removed_submenus,
				'removed_adminbar_menus' => $removed_adminbar_menus,
			];

			// Store our processed config inside a transient, with long expiration date. Cache auto-clears when Fuerte-WP options are saved.
			set_transient('fuertewp_cache_config_' . $version_to_string, $fuertewp, 30 * DAY_IN_SECONDS);
		}

		return $fuertewp;
	}

	/**
	 * Register hooks conditionally based on configuration
	 */
	private function register_conditional_hooks($fuertewp)
	{
		// Only register email-related hooks if email features are enabled
		$this->register_email_hooks($fuertewp);

		// Only register security-related hooks if security features are enabled
		$this->register_security_hooks($fuertewp);

		// Only register UI-related hooks if UI features are enabled
		$this->register_ui_hooks($fuertewp);

		// Only register restriction-related hooks if restrictions are enabled
		$this->register_restriction_hooks($fuertewp);
	}

	/**
	 * Register email-related hooks
	 */
	private function register_email_hooks($fuertewp)
	{
		// Always register recovery email hook (core functionality)
		add_filter('recovery_mode_email', [__CLASS__, 'recovery_email_address'], FUERTEWP_LATE_PRIORITY);

		// Only register sender email hooks if enabled
		if (isset($fuertewp['general']['sender_email_enable']) && true === $fuertewp['general']['sender_email_enable']) {
			add_filter('wp_mail_from', [__CLASS__, 'sender_email_address'], FUERTEWP_LATE_PRIORITY);
			add_filter('wp_mail_from_name', [__CLASS__, 'sender_email_address'], FUERTEWP_LATE_PRIORITY);
		}

		// Only register email notification hooks if any email features are disabled
		$email_hooks_needed = false;
		foreach ($fuertewp['emails'] as $key => $value) {
			if (false === $value) {
				$email_hooks_needed = true;
				break;
			}
		}

		if ($email_hooks_needed) {
			$this->register_email_notification_hooks($fuertewp);
		}
	}

	/**
	 * Register email notification hooks
	 */
	private function register_email_notification_hooks($fuertewp)
	{
		// Comment notifications
		if (isset($fuertewp['emails']['comment_awaiting_moderation']) && false === $fuertewp['emails']['comment_awaiting_moderation']) {
			add_filter('notify_moderator', '__return_false', FUERTEWP_LATE_PRIORITY);
		}

		if (isset($fuertewp['emails']['comment_has_been_published']) && false === $fuertewp['emails']['comment_has_been_published']) {
			add_filter('notify_post_author', '__return_false', FUERTEWP_LATE_PRIORITY);
		}

		// User management notifications
		if (isset($fuertewp['emails']['user_reset_their_password']) && false === $fuertewp['emails']['user_reset_their_password']) {
			remove_action('after_password_reset', 'wp_password_change_notification', FUERTEWP_LATE_PRIORITY);
		}

		if (isset($fuertewp['emails']['user_confirm_personal_data_export_request']) && false === $fuertewp['emails']['user_confirm_personal_data_export_request']) {
			remove_action('user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification', FUERTEWP_LATE_PRIORITY);
		}

		if (isset($fuertewp['emails']['new_user_created']) && false === $fuertewp['emails']['new_user_created']) {
			remove_action('register_new_user', 'wp_send_new_user_notifications', FUERTEWP_LATE_PRIORITY);
			remove_action('edit_user_created_user', 'wp_send_new_user_notifications', FUERTEWP_LATE_PRIORITY);
			remove_action('network_site_new_created_user', 'wp_send_new_user_notifications', FUERTEWP_LATE_PRIORITY);
			remove_action('network_site_users_created_user', 'wp_send_new_user_notifications', FUERTEWP_LATE_PRIORITY);
			remove_action('network_user_new_created_user', 'wp_send_new_user_notifications', FUERTEWP_LATE_PRIORITY);
		}

		// Update notifications
		if (isset($fuertewp['emails']['automatic_updates']) && false === $fuertewp['emails']['automatic_updates']) {
			add_filter('auto_core_update_send_email', '__return_false', FUERTEWP_LATE_PRIORITY);
			add_filter('send_core_update_notification_email', '__return_false', FUERTEWP_LATE_PRIORITY);
			add_filter('auto_plugin_update_send_email', '__return_false');
			add_filter('auto_theme_update_send_email', '__return_false');
		}

		// Network notifications
		if (isset($fuertewp['emails']['network_new_site_created']) && false === $fuertewp['emails']['network_new_site_created']) {
			add_filter('send_new_site_email', '__return_false', FUERTEWP_LATE_PRIORITY);
		}

		if (isset($fuertewp['emails']['network_new_user_site_registered']) && false === $fuertewp['emails']['network_new_user_site_registered']) {
			add_filter('wpmu_signup_blog_notification', '__return_false', FUERTEWP_LATE_PRIORITY);
		}

		if (isset($fuertewp['emails']['network_new_site_activated']) && false === $fuertewp['emails']['network_new_site_activated']) {
			remove_action('wp_initialize_site', 'newblog_notify_siteadmin', FUERTEWP_LATE_PRIORITY);
		}

		// Error handler
		if (isset($fuertewp['emails']['fatal_error']) && false === $fuertewp['emails']['fatal_error']) {
			define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
		}
	}

	/**
	 * Register security-related hooks
	 */
	private function register_security_hooks($fuertewp)
	{
		// XML-RPC restrictions
		if (isset($fuertewp['restrictions']['disable_xmlrpc']) && true === $fuertewp['restrictions']['disable_xmlrpc']) {
			add_filter('xmlrpc_enabled', '__return_false', FUERTEWP_LATE_PRIORITY);
			add_filter('xmlrpc_methods', function () {
				return [];
			}, FUERTEWP_LATE_PRIORITY);

			add_action('init', function () {
				if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
					header('HTTP/1.1 403 Forbidden');
					die('403 Forbidden - XML-RPC functionality is disabled on this site.');
				}
			}, FUERTEWP_LATE_PRIORITY);
		}

		// REST API restrictions
		if (isset($fuertewp['rest_api']['loggedin_only']) && true === $fuertewp['rest_api']['loggedin_only']) {
			add_filter('rest_authentication_errors', 'fuertewp_restapi_loggedin_only');
		}
	}

	/**
	 * Register UI-related hooks
	 */
	private function register_ui_hooks($fuertewp)
	{
		// Custom UI tweaks (CSS, JS, login logo)
		add_filter('admin_footer', [__CLASS__, 'custom_javascript'], FUERTEWP_LATE_PRIORITY);
		add_filter('login_head', [__CLASS__, 'custom_javascript'], FUERTEWP_LATE_PRIORITY);
		add_filter('admin_head', [__CLASS__, 'custom_css'], FUERTEWP_LATE_PRIORITY);
		add_filter('login_head', [__CLASS__, 'custom_css'], FUERTEWP_LATE_PRIORITY);
		add_action('login_enqueue_scripts', [__CLASS__, 'custom_login_logo'], FUERTEWP_LATE_PRIORITY);
		add_action('login_headerurl', [__CLASS__, 'custom_login_url'], FUERTEWP_LATE_PRIORITY);
		add_action('login_headertitle', [__CLASS__, 'custom_login_title'], FUERTEWP_LATE_PRIORITY);
	}

	/**
	 * Register restriction-related hooks
	 */
	private function register_restriction_hooks($fuertewp)
	{
		// Admin-specific restrictions
		if (is_admin()) {
			// Menu and admin bar restrictions
			add_filter('admin_menu', [__CLASS__, 'remove_menus'], FUERTEWP_LATE_PRIORITY);
			add_filter('admin_bar_menu', [__CLASS__, 'remove_adminbar_menus'], FUERTEWP_LATE_PRIORITY);

			// User role restrictions
			if (isset($fuertewp['restrictions']['disable_admin_create_edit']) && true === $fuertewp['restrictions']['disable_admin_create_edit']) {
				add_filter('editable_roles', [__CLASS__, 'create_edit_role_check'], FUERTEWP_LATE_PRIORITY);
			}

			// Application passwords
			if (isset($fuertewp['rest_api']['disable_app_passwords']) && true === $fuertewp['rest_api']['disable_app_passwords']) {
				add_filter('wp_is_application_passwords_available', '__return_false', FUERTEWP_LATE_PRIORITY);
			}

			// ACF restrictions
			if (isset($fuertewp['restrictions']['restrict_acf']) && true === $fuertewp['restrictions']['restrict_acf']) {
				add_filter('acf/settings/show_admin', '__return_false', FUERTEWP_LATE_PRIORITY);
			}
		}

		// Front-end admin bar restrictions
		if (!is_admin() && isset($fuertewp['restrictions']['disable_admin_bar_roles']) && !empty($fuertewp['restrictions']['disable_admin_bar_roles'])) {
			if (is_array($fuertewp['restrictions']['disable_admin_bar_roles'])) {
				foreach ($fuertewp['restrictions']['disable_admin_bar_roles'] as $role) {
					if (true === $this->has_role($role)) {
						add_filter('show_admin_bar', '__return_false', FUERTEWP_LATE_PRIORITY);
						break; // Only need to add this once
					}
				}
			}
		}
	}

	/**
	 * Enforcer method
	 */
	protected function enforcer()
	{
		global $pagenow, $current_user;

		$fuertewp = $this->config_setup();

		if (!isset($current_user)) {
			$current_user = wp_get_current_user();
		}

		// Early exit if plugin is disabled
		if (!isset($fuertewp['status']) || $fuertewp['status'] != 'enabled') {
			return;
		}

		/**
		 * Themes & Plugins auto updates - managed via cronjob
		 */
		$this->auto_update_manager->manage_updates($fuertewp);

		/**
		 * htaccess security rules
		 */
		if (isset($fuertewp['restrictions']['htaccess_security_rules']) && true === $fuertewp['restrictions']['htaccess_security_rules']) {
			// Ensure we are running Apache
			if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false) {
				// Read .htaccess file contents, if exists
				$htaccessFile = ABSPATH . '.htaccess';

				// Check if we can write to .htaccess
				if (file_exists($htaccessFile) && is_writable($htaccessFile)) {
					$currentContent = file_get_contents($htaccessFile);

					// If .htaccess doesn't contain our rules, add them
					if (false === stripos($currentContent, '# BEGIN Fuerte-WP')) {
						global $fuertewp_htaccess;

						// Write .htaccess file, add our rules at the very end
						file_put_contents($htaccessFile, $currentContent . PHP_EOL . $fuertewp_htaccess);
					}
				}
			}
		}

		// Check if current user should be affected by Fuerte-WP
		$is_super_user = in_array(strtolower($current_user->user_email), $fuertewp['super_users']);
		$is_forced = defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE;

		// Early exit for super users (unless forced)
		if ($is_super_user && !$is_forced) {
			return;
		}

		if ($is_forced || !$is_super_user) {
			// Register hooks conditionally based on configuration
			$this->register_conditional_hooks($fuertewp);

			// wp-admin only tweaks
			if (is_admin()) {
				// Fuerte-WP self-protect
				$this->self_protect();

				// Disable Theme Editor
				if (isset($fuertewp['restrictions']['disable_theme_editor']) && true === $fuertewp['restrictions']['disable_theme_editor']) {
					if ($pagenow == 'theme-editor.php') {
						$this->access_denied();
					}
				}

				// Disable Plugin Editor
				if (isset($fuertewp['restrictions']['disable_plugin_editor']) && true === $fuertewp['restrictions']['disable_plugin_editor']) {
					if ($pagenow == 'plugin-editor.php') {
						$this->access_denied();
					}
				}

				// Both? Theme and Plugin Editor?
				if ((isset($fuertewp['restrictions']['disable_theme_editor']) && true === $fuertewp['restrictions']['disable_theme_editor']) && (isset($fuertewp['restrictions']['disable_plugin_editor']) && true === $fuertewp['restrictions']['disable_plugin_editor'])) {
					define('DISALLOW_FILE_EDIT', true);
				}

				// Disable Theme Install
				if (isset($fuertewp['restrictions']['disable_theme_install']) && true === $fuertewp['restrictions']['disable_theme_install']) {
					if ($pagenow == 'theme-install.php') {
						$this->access_denied();
					}
				}

				// Disable Plugin Install
				if (isset($fuertewp['restrictions']['disable_plugin_install']) && true === $fuertewp['restrictions']['disable_plugin_install']) {
					if ($pagenow == 'plugin-install.php') {
						$this->access_denied();
					}
				}

				// Disable WP Customizer Additional CSS editor
				if (isset($fuertewp['restrictions']['disable_customizer_css']) && true === $fuertewp['restrictions']['disable_customizer_css']) {
					if ($pagenow == 'customize.php') {
						add_action('customize_register', 'fuertewp_customizer_remove_css_editor');
					}
				}

				// Disallowed wp-admin scripts
				if (isset($fuertewp['restricted_scripts']) && in_array($pagenow, $fuertewp['restricted_scripts']) && !wp_doing_ajax()) {
					$this->access_denied();
				}

				// Disallowed wp-admin pages
				if (isset($fuertewp['restricted_pages']) && isset($_REQUEST['page']) && in_array($_REQUEST['page'], $fuertewp['restricted_pages']) && !wp_doing_ajax()) {
					$this->access_denied();
				}

				// No user switching
				if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'switch_to_user') {
					$this->access_denied();
				}

				// No protected users editing
				if ($pagenow == 'user-edit.php') {
					if (isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id'])) {
						$user_info = get_userdata($_REQUEST['user_id']);

						if (in_array(strtolower($user_info->user_email), $fuertewp['super_users'])) {
							$this->access_denied();
						}
					}
				}

				// No protected users deletion
				if ($pagenow == 'users.php') {
					if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete') {
						if (isset($_REQUEST['users'])) {
							// Single user
							foreach ($_REQUEST['users'] as $user) {
								$user_info = get_userdata($user);

								if (in_array(strtolower($user_info->user_email), $fuertewp['super_users'])) {
									$this->access_denied();
								}
							}
						} elseif (isset($_REQUEST['user'])) {
							// Batch deletion
							$user_info = get_userdata($_REQUEST['user']);

							if (in_array(strtolower($user_info->user_email), $fuertewp['super_users'])) {
								$this->access_denied();
							}
						}
					}
				}

				// ACF restrictions
				if (isset($fuertewp['restrictions']['restrict_acf']) && true === $fuertewp['restrictions']['restrict_acf']) {
					if (in_array($pagenow, ['post.php']) && isset($_GET['post']) && 'acf-field-group' === get_post_type($_GET['post'])) {
						$this->access_denied();
					}

					if (in_array($pagenow, ['edit.php', 'post-new.php']) && isset($_GET['post_type']) && 'acf-field-group' === $_GET['post_type']) {
						$this->access_denied();
					}
				}

				// Permalinks restrictions
				if (isset($fuertewp['restrictions']['restrict_permalinks']) && true === $fuertewp['restrictions']['restrict_permalinks']) {
					// No Permalinks config access
					if (in_array($pagenow, ['options-permalink.php'])) {
						$this->access_denied();
					}

					add_action('admin_menu', function () {
						remove_submenu_page('options-general.php', 'options-permalink.php');
					}, FUERTEWP_LATE_PRIORITY);
				}
			} // is_admin()
		} // user affected by Fuerte-WP
	}

	/**
	 * Fuerte-WP self-protection
	 */
	private function self_protect()
	{
		global $pagenow;

		// Remove Fuerte-WP from admin menu
		add_action('admin_menu', function () {
			remove_submenu_page('options-general.php', 'crb_carbon_fields_container_fuerte-wp.php');
		}, FUERTEWP_LATE_PRIORITY);

		// Prevent direct deactivation
		if (
			isset($_REQUEST['action'])
			&& $_REQUEST['action'] == 'deactivate'
			&& $pagenow == 'plugins.php'
			&& isset($_REQUEST['plugin'])
			&& stripos($_REQUEST['plugin'], 'fuerte-wp') !== false
		) {
			$this->access_denied();
		}

		// Check if a non super-user is accessing our plugin options
		if ($pagenow == 'options-general.php' && isset($_REQUEST['page']) && $_REQUEST['page'] == 'crb_carbon_fields_container_fuerte-wp.php') {
			$this->access_denied();
		}

		// Hide deactivation link
		add_filter('plugin_action_links', function ($actions, $plugin_file) {
			if (plugin_basename(FUERTEWP_PLUGIN_BASE) === $plugin_file) {
				unset($actions['deactivate']);
			}

			return $actions;
		}, FUERTEWP_LATE_PRIORITY, 2);
	}

	/**
	 * Prints and ends WP execution with "Access denied" message
	 */
	protected function access_denied()
	{
		global $fuertewp;

		if (!isset($fuertewp['general']['access_denied_message']) || empty($fuertewp['general']['access_denied_message'])) {
			$fuertewp['general']['access_denied_message'] = 'Access denied.';
		}

		wp_die($fuertewp['general']['access_denied_message']);
		return false;
	}

	/**
	 * Set WP sender email address
	 *
	 * @return string    Email address
	 */
	static function sender_email_address(): string
	{
		global $fuertewp;

		$sender_email_address = $fuertewp['general']['sender_email'] ?? 'no-reply@' . parse_url(home_url())['host'];

		// Remove www from hostname
		return str_replace('www.', '', $sender_email_address);
	}

	/**
	 * Change WP recovery email adresss
	 *
	 * @return string    Email address
	 */
	static function recovery_email_address(): array
	{
		global $fuertewp, $pagenow, $current_user;

		$recovery_email = $fuertewp['general']['recovery_email'] ?? 'dev@' . parse_url(home_url())['host'];
		$email_data['to'] = $recovery_email;

		return $email_data;
	}

	/**
	 * Remove wp-admin menus
	 */
	static function remove_menus()
	{
		global $fuertewp;

		if (isset($fuertewp['restricted_scripts']) && !empty($fuertewp['restricted_scripts'])) {
			foreach ($fuertewp['restricted_scripts'] as $item) {
				if (substr($item, 0, 2) === '//') {
					// Commented item, skip it
					continue;
				}

				remove_menu_page($item);
			}
		}

		if (isset($fuertewp['removed_menus']) && !empty($fuertewp['removed_menus'])) {
			foreach ($fuertewp['removed_menus'] as $slug) {
				remove_menu_page($slug);
			}
		}

		if (isset($fuertewp['removed_submenus']) && !empty($fuertewp['removed_submenus'])) {
			$submenu_parts = [];
			foreach ($fuertewp['removed_submenus'] as $item) {
				$submenu_parts = explode('|', $item);
				$submenu_parts = array_map('trim', $submenu_parts);

				remove_submenu_page($submenu_parts[0], $submenu_parts[1]);
			}
		}
	}

	/**
	 * Remove adminbar menus (nodes)
	 */
	static function remove_adminbar_menus($wp_admin_bar)
	{
		global $fuertewp;

		if (isset($fuertewp['removed_adminbar_menus']) && !empty($fuertewp['removed_adminbar_menus'])) {
			foreach ($fuertewp['removed_adminbar_menus'] as $item) {
				$wp_admin_bar->remove_node($item);
			}

			define('UPDRAFTPLUS_ADMINBAR_DISABLE', true);
		}
	}

	/**
	 * Check if a role can be created/edited
	 *
	 * @return array    Roles array, without administrator role
	 */
	static function create_edit_role_check($roles): array
	{
		unset($roles['administrator']);

		return $roles;
	}

	/**
	 * Check current user role
	 * https://wordpress.org/support/article/roles-and-capabilities/
	 *
	 * @return bool    True if it has the role
	 */
	static function has_role($role = 'subscriber'): bool
	{
		$user = wp_get_current_user();

		return in_array($role, (array) $user->roles);
	}

	/**
	 * Custom Javascript at footer
	 */
	static function custom_javascript()
	{
		global $fuertewp;
?>
		<script type="text/javascript">
			document.addEventListener("DOMContentLoaded", function() {
				<?php
				// Disable typing a custom password (new user, profile edit, lost password).
				// Needed outside wp-admin, because reset password screen
				if (isset($fuertewp['restrictions']['force_strong_passwords']) && true === $fuertewp['restrictions']['force_strong_passwords']) :
				?>
					if (document.body.classList.contains('user-new-php') ||
						document.body.classList.contains('user-edit-php') ||
						document.body.classList.contains('login') ||
						document.body.classList.contains('profile-php')) {
						document.getElementById('pass1').setAttribute('readonly', 'readonly');
					}
				<?php
				endif;
				?>
			});
		</script>
	<?php
	}

	/**
	 * Custom CSS at header
	 */
	static function custom_css()
	{
		global $fuertewp;
	?>
		<style type="text/css">
			<?php
			// Hides "Confirm use of weak password" checkbox on weak password, forcing a medium one at the very minimum.
			// Needed outside wp-admin, because reset password screen
			if (isset($fuertewp['restrictions']['disable_weak_passwords']) && true === $fuertewp['restrictions']['disable_weak_passwords']) :
			?>.pw-weak {
				display: none !important;
			}

			<?php
			endif;
			?><?php
				// Hides ACF cog that allow users access ACF editable meta boxes UI
				if (isset($fuertewp['restrictions']['restrict_acf']) && true === $fuertewp['restrictions']['restrict_acf']) :
				?>.wp-admin h3.hndle.ui-sortable-handle a.acf-hndle-cog {
				display: none !important;
				visibility: hidden !important;
			}

			<?php
				endif;
			?>
		</style>
		<?php
	}

	/**
	 * WP Login custom logo
	 */
	static function custom_login_logo()
	{
		global $fuertewp;

		if (isset($fuertewp['tweaks']['use_site_logo_login']) && true === $fuertewp['tweaks']['use_site_logo_login']) {
			if (!has_custom_logo()) {
				return;
			}

		?>
			<style type="text/css">
				#login h1 a,
				.login h1 a {
					background-image: url(<?php echo esc_url(wp_get_attachment_url(get_theme_mod('custom_logo'))); ?>);
					background-repeat: no-repeat;
					padding-bottom: 20px;
					filter: drop-shadow(0px 0px 4px #3c434a);
				}
			</style>
<?php
		}
	}

	/**
	 * WP Login custom logo URL
	 *
	 * @return string    Blog URL
	 */
	static function custom_login_url()
	{
		global $fuertewp;

		if (isset($fuertewp['tweaks']['use_site_logo_login']) && true === $fuertewp['tweaks']['use_site_logo_login']) {
			return home_url();
		}
	}

	/**
	 * WP Login custom logo title
	 *
	 * @return string    Blog name
	 */
	static function custom_login_title()
	{
		global $fuertewp;

		if (isset($fuertewp['tweaks']['use_site_logo_login']) && true === $fuertewp['tweaks']['use_site_logo_login']) {
			return get_bloginfo('name');
		}
	}


	// Work in Progress...
	static function recommended_plugins()
	{
		global $fuertewp, $pagenow;

		$show_notice            = false;
		$plugin_recommendations = [];

		if (!isset($fuertewp['recommended_plugins']) || empty($fuertewp['recommended_plugins'])) {
			return;
		}

		if (current_user_can('activate_plugins') && (!wp_doing_ajax())) {
			if (is_array($fuertewp['recommended_plugins'])) {
				foreach ($fuertewp['recommended_plugins'] as $plugin) {
					if (!is_plugin_active($plugin) && !is_plugin_active_for_network($plugin)) {
						$show_notice              = true;
						$plugin_recommendations[] = $plugin;
					}
				}
			}
		}

		if (true === $show_notice && ($pagenow == 'plugins.php' || (isset($_REQUEST['page']) && $_REQUEST['page'] == 'wc-settings') || $pagenow == 'options-general.php')) {
			//add_action( 'admin_notices', 'fuertewp_recommended_plugins_notice' );
		}
	}
} // Class Fuerte_Wp_Enforcer
