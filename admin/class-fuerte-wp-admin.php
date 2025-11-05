<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://actitud.xyz
 * @since      1.0.0
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */
class Fuerte_Wp_Admin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @var      string       The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @var      string       The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        // Only load on Fuerte-WP admin pages for performance
        $screen = get_current_screen();
        if (!$screen || !strpos($screen->id, 'fuerte-wp')) {
            return;
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        // Only load on Fuerte-WP admin pages for performance
        $screen = get_current_screen();
        if (!$screen || !strpos($screen->id, 'fuerte-wp')) {
            return;
        }
    }

    public function fuertewp_plugin_options()
    {
        global $fuertewp;

        /*
         * No admin options if main config file exists physically
         */
        if (
            file_exists(ABSPATH . 'wp-config-fuerte.php')
            && is_array($fuertewp)
            && !empty($fuertewp)
        ) {
            return;
        }

        // Get site's domain. Avoids error: Undefined array key "SERVER_NAME".
        $domain = parse_url(get_site_url(), PHP_URL_HOST);

        Container::make('theme_options', __('Fuerte-WP', 'fuerte-wp'))
            ->set_page_parent('options-general.php')
            ->set_page_file('fuerte-wp-options')

            ->add_tab(__('Main Options', 'fuerte-wp'), [
                Field::make(
                    'checkbox',
                    'fuertewp_status',
                    __('Enable Fuerte-WP.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('enabled')
                    ->set_help_text(
                        __(
                            'Check the option to enable Fuerte-WP.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'multiselect',
                    'fuertewp_super_users',
                    __('Super Administrators.', 'fuerte-wp'),
                )
                    ->add_options('fuertewp_get_admin_users')
                    ->set_help_text(
                        __(
                            'Users that will not be affected by Fuerte-WP rules. Only administrators emails are listed here.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'separator',
                    'fuertewp_separator_general',
                    __('General', 'fuerte-wp'),
                ),

                Field::make(
                    'text',
                    'fuertewp_access_denied_message',
                    __('Access denied message.', 'fuerte-wp'),
                )
                    ->set_default_value('Access denied.')
                    ->set_help_text(
                        __(
                            'General access denied message shown to non super users.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'text',
                    'fuertewp_recovery_email',
                    __('Recovery email.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_attribute('type', 'email')
                    /* translators: %s: site domain */
                    ->set_help_text(
                        sprintf(
                            __(
                                'Admin recovery email. If empty, dev@%s will be used.<br/>This email will receive fatal errors from WP, and not the administration email in the General Settings. Check <a href="https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/" target="_blank">fatal error recovery mode</a>.',
                                'fuerte-wp',
                            ),
                            $domain,
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_sender_email_enable',
                    __('Use a different sender email.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        sprintf(
                            __(
                                'Use a different email (than the <a href="%s">administrator one</a>) for all emails that WordPress sends.',
                                'fuerte-wp',
                            ),
                            admin_url('options-general.php'),
                        ),
                    ),

                Field::make(
                    'text',
                    'fuertewp_sender_email',
                    __('Sender email.', 'fuerte-wp'),
                )
                    ->set_conditional_logic([
                        'relation' => 'AND',
                        [
                            'field' => 'fuertewp_sender_email_enable',
                            'value' => true,
                            'compare' => '=',
                        ],
                    ])
                    ->set_default_value('')
                    ->set_attribute('type', 'email')
                    /* translators: %s: site domain */
                    ->set_help_text(
                        sprintf(
                            __(
                                'Default site sender email. If empty, no-reply@%1$s will be used.<br/>Emails sent by WP will use this email address. Make sure to check your <a href="https://mxtoolbox.com/SPFRecordGenerator.aspx?domain=%1$s&prefill=true" target="_blank">SPF Records</a> to avoid WP emails going to spam.',
                                'fuerte-wp',
                            ),
                            $domain,
                        ),
                    ),

                Field::make(
                    'separator',
                    'fuertewp_separator_updates',
                    __('Updates', 'fuerte-wp'),
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_autoupdate_core',
                    __('Auto-update WordPress core.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Auto-update WordPress to the latest stable version.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_autoupdate_plugins',
                    __('Auto-update Plugins.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Auto-update Plugins to their latest stable version.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_autoupdate_themes',
                    __('Auto-update Themes.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Auto-update Themes to their latest stable version.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_autoupdate_translations',
                    __('Auto-update Translations.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Auto-update Translations to their latest stable version.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'select',
                    'fuertewp_autoupdate_frequency',
                    __('Update check frequency', 'fuerte-wp'),
                )
                    ->add_options([
                        'six_hours' => __('Every 6 hours', 'fuerte-wp'),
                        'twelve_hours' => __('Every 12 hours', 'fuerte-wp'),
                        'daily' => __('Every 24 hours', 'fuerte-wp'),
                        'twodays' => __('Every 48 hours', 'fuerte-wp'),
                    ])
                    ->set_default_value('twelve_hours')
                    ->set_help_text(
                        __(
                            'How often to check for and apply updates.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'separator',
                    'fuertewp_separator_tweaks',
                    __('Tweaks', 'fuerte-wp'),
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_tweaks_use_site_logo_login',
                    __('Use site logo at login.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    /* translators: %s: customizer URL */
                    ->set_help_text(
                        sprintf(
                            __(
                                'Use your site logo, uploaded via <a href="%s" target="_blank">Customizer > Site Identity</a>, for WordPress login page.',
                                'fuerte-wp',
                            ),
                            admin_url(
                                'customize.php?return=%2Fwp-admin%2Foptions-general.php%3Fpage%3Dcrb_carbon_fields_container_fuerte-wp.php',
                            ),
                        ),
                    ),
            ])

            ->add_tab(__('E-mails', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_emails_header',
                    __('Note:', 'fuerte-wp'),
                )->set_html(
                    __(
                        '<p>Here you can enable or disable several WordPress built in emails. <strong>Mark</strong> the ones you want to be <strong>enabled</strong>.</p><p><a href="https://github.com/johnbillion/wp_mail" target="_blank">Check here</a> for full documentation of all automated emails WordPress sends.',
                        'fuerte-wp',
                    ) . '</p>'
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_fatal_error',
                    __('Fatal Error.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Receipt: site admin or recovery email address (main options).',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_automatic_updates',
                    __('Automatic updates.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: site admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_comment_awaiting_moderation',
                    __('Comment awaiting moderation.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: site admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_comment_has_been_published',
                    __('Comment has been published.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: post author.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_user_reset_their_password',
                    __('User reset their password.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: site admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_user_confirm_personal_data_export_request',
                    __(
                        'User confirm personal data export request.',
                        'fuerte-wp',
                    ),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: site admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_new_user_created',
                    __('New user created.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: site admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_network_new_site_created',
                    __('Network: new site created.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: network admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_network_new_user_site_registered',
                    __('Network: new user site registered.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: network admin.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_emails_network_new_site_activated',
                    __('Network: new site activated.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Receipt: network admin.', 'fuerte-wp')),
            ])

            ->add_tab(__('Login Security', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_login_security_header',
                    __('Login Security Information', 'fuerte-wp'),
                )->set_html(
                    '<p>' . __(
                        'Enable login attempt limiting to protect your site from brute force attacks.',
                        'fuerte-wp',
                    ) . '</p>'
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_login_enable',
                    __('Enable Login Security', 'fuerte-wp'),
                )
                    ->set_default_value('enabled')
                    ->set_option_value('enabled')
                    ->set_help_text(__('Enable login attempt limiting and IP blocking.', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_registration_enable',
                    __('Enable Registration Protection', 'fuerte-wp'),
                )
                    ->set_default_value('enabled')
                    ->set_option_value('enabled')
                    ->set_help_text(__('Enable registration attempt limiting and bot blocking. Uses same settings as login security.', 'fuerte-wp')),

                Field::make(
                    'separator',
                    'fuertewp_login_separator_settings',
                    __('Login Attempt Settings', 'fuerte-wp'),
                ),

                Field::make(
                    'text',
                    'fuertewp_login_max_attempts',
                    __('Maximum Login Attempts', 'fuerte-wp'),
                )
                    ->set_default_value(5)
                    ->set_attribute('type', 'number')
                    ->set_attribute('min', 3)
                    ->set_attribute('max', 10)
                    ->set_help_text(__('Number of failed attempts before lockout (3-10).', 'fuerte-wp')),

                Field::make(
                    'text',
                    'fuertewp_login_lockout_duration',
                    __('Lockout Duration (minutes)', 'fuerte-wp'),
                )
                    ->set_default_value(60)
                    ->set_attribute('type', 'number')
                    ->set_attribute('min', 5)
                    ->set_attribute('max', 1440)
                    ->set_help_text(__('How long to lock out after max attempts (5-1440 minutes).', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_login_increasing_lockout',
                    __('Increasing Lockout Duration', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(__('Increase lockout duration exponentially (2x, 4x, 8x, etc.) with each lockout.', 'fuerte-wp')),

                Field::make(
                    'separator',
                    'fuertewp_login_separator_ip',
                    __('IP Detection', 'fuerte-wp'),
                ),

                Field::make(
                    'text',
                    'fuertewp_login_ip_headers',
                    __('Custom IP Headers', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_help_text(
                        __(
                            'Comma-separated list of custom IP headers (e.g., HTTP_X_FORWARDED_FOR). Useful for Cloudflare, Sucuri, or other proxy/CDN services.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'separator',
                    'fuertewp_login_separator_gdpr',
                    __('GDPR Compliance', 'fuerte-wp'),
                ),

                Field::make(
                    'textarea',
                    'fuertewp_login_gdpr_message',
                    __('GDPR Privacy Notice', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_rows(3)
                    ->set_help_text(__('Optional privacy notice displayed below the login form. Leave empty to disable.', 'fuerte-wp')),

                Field::make(
                    'separator',
                    'fuertewp_login_separator_retention',
                    __('Data Retention', 'fuerte-wp'),
                ),

                Field::make(
                    'text',
                    'fuertewp_login_data_retention',
                    __('Data Retention (days)', 'fuerte-wp'),
                )
                    ->set_default_value(30)
                    ->set_attribute('type', 'number')
                    ->set_attribute('min', 1)
                    ->set_attribute('max', 365)
                    ->set_help_text(__('Number of days to keep login logs (1-365). Old records are automatically deleted.', 'fuerte-wp')),
            ])

            ->add_tab(__('REST API', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_restapi_restrictions_header',
                    __('Note:', 'fuerte-wp'),
                )->set_html(__('<p>REST API restrictions.</p>', 'fuerte-wp')),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_restapi_loggedin_only',
                    __(
                        'Restrict REST API usage to logged in users only.',
                        'fuerte-wp',
                    ),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Modern WordPress depends on his REST API. The entire new editor, Gutenberg, uses it. And many more usage instances are common the WP core. You should not disable the REST API entirely, or WordPress will brake. This is the second best option: limit his usage to only logged in users. <a href="https://developer.wordpress.org/rest-api/frequently-asked-questions/" target="_blank">Learn more</a>.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_restapi_disable_app_passwords',
                    __('Disable app passwords.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disable generation of App Passwords, used for the REST API. <a href="https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/" target="_blank">Check here</a> for more info.',
                            'fuerte-wp',
                        ),
                    ),
            ])

            ->add_tab(__('Restrictions', 'fuerte-wp'), [
                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_xmlrpc',
                    __('Disable XML-RPC API.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disable the old and insecure XML-RPC API in WordPress. <a href="https://blog.wpscan.com/is-wordpress-xmlrpc-a-security-problem/" target="_blank">Learn more</a>.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_htaccess_security_rules',
                    __('Enable htaccess security rules', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disable the usage of /wp-admin/install.php wizard, and the execution of php files inside /wp-content/uploads/ folder, by adding restrictions on the htaccess file on the server. If you are using Nginx, please, <a href="https://github.com/EstebanForge/Fuerte-WP/blob/master/FAQ.md" target="_blank">Add them manually</a>.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_admin_create_edit',
                    __('Disable admin creation/edition.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disable the creation of new admin accounts and the editing of existing admin accounts.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_weak_passwords',
                    __('Disable weak passwords.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disable the use of weak passwords. User can\'t uncheck "Confirm use of weak password". Let users type their own password, but must be somewhat secure (following WP built in recommendation library).',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_force_strong_passwords',
                    __('Force strong passwords.', 'fuerte-wp'),
                )
                    ->set_default_value('')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Force strong passwords usage, making password field read-only. Users must use WordPress provided strong password.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'multiselect',
                    'fuertewp_restrictions_disable_admin_bar_roles',
                    __('Disable admin bar for roles.', 'fuerte-wp'),
                )
                    ->add_options('fuertewp_get_wp_roles')
                    ->set_default_value(['subscriber', 'customer'])
                    ->set_help_text(
                        __(
                            'Disable WordPress admin bar for selected roles.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_restrict_permalinks',
                    __('Restrict Permalinks configuration.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Restrict Permalinks configuration access.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_restrict_acf',
                    __('Restrict ACF fields editing.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Restrict Advanced Custom Fields editing access in the backend (Custom Fields menu).',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_theme_editor',
                    __('Disable Theme Editor.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disables the built in Theme code editor.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_plugin_editor',
                    __('Disable Plugin Editor.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disables the built in Plugin code editor.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_theme_install',
                    __('Disable Theme install.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __('Disables installation of new Themes.', 'fuerte-wp'),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_plugin_install',
                    __('Disable Plugin install.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disables installation of new Plugins.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'checkbox',
                    'fuertewp_restrictions_disable_customizer_css',
                    __('Disable Customizer CSS Editor.', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(
                        __(
                            'Disables Customizer Additional CSS Editor.',
                            'fuerte-wp',
                        ),
                    ),
            ])

            ->add_tab(__('Advanced Restrictions', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_advanced_restrictions_header',
                    __('Note:', 'fuerte-wp'),
                )->set_html(
                    __(
                        '<p>Only for power users. Leave a field blank to not use those restrictions.</p>',
                        'fuerte-wp',
                    )
                ),

                Field::make(
                    'textarea',
                    'fuertewp_restricted_scripts',
                    __('Restricted Scripts.', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_default_value(
                        'export.php
//plugins.php
update.php
update-core.php',
                    )
                    ->set_help_text(
                        __(
                            'One per line. Restricted scripts by file name.<br>These file names will be checked against <a href="https://codex.wordpress.org/Global_Variables" target="_blank">$pagenow</a>, and also will be thrown into <a href="https://developer.wordpress.org/reference/functions/remove_menu_page/" target="_blank">remove_menu_page</a>.<br/>You can comment a line with // to not use it.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'textarea',
                    'fuertewp_restricted_pages',
                    __('Restricted Pages.', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_default_value(
                        'wprocket
updraftplus
better-search-replace
backwpup
backwpupjobs
backwpupeditjob
backwpuplogs
backwpupbackups
backwpupsettings
limit-login-attempts
wp_stream_settings
transients-manager
pw-transients-manager
envato-market
elementor-license',
                    )
                    ->set_help_text(
                        __(
                            'One per line. Restricted pages by "page" URL variable.<br/>In wp-admin, checks for URLs like: <i>admin.php?page=</i>',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'textarea',
                    'fuertewp_removed_menus',
                    __('Removed Menus.', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_default_value(
                        'backwpup
check-email-status
limit-login-attempts
envato-market',
                    )
                    ->set_help_text(
                        __(
                            'One per line. Menus to be removed. Use menu <i>slug</i>.<br/>These slugs will be thrown into <a href="https://developer.wordpress.org/reference/functions/remove_menu_page/" target="_blank">remove_menu_page</a>.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'textarea',
                    'fuertewp_removed_submenus',
                    __('Removed Submenus.', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_default_value(
                        'options-general.php|updraftplus
options-general.php|limit-login-attempts
options-general.php|mainwp_child_tab
options-general.php|wprocket
tools.php|export.php
tools.php|transients-manager
tools.php|pw-transients-manager
tools.php|better-search-replace',
                    )
                    ->set_help_text(
                        __(
                            'One per line. Submenus to be removed. Use: <i>parent-menu-slug<strong>|</strong>submenu-slug</i>, separared with a pipe.<br/>These will be thrown into <a href="https://developer.wordpress.org/reference/functions/remove_submenu_page/" target="_blank">remove_submenu_page</a>.',
                            'fuerte-wp',
                        ),
                    ),

                Field::make(
                    'textarea',
                    'fuertewp_removed_adminbar_menus',
                    __('Removed Admin Bar menus.', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_default_value(
                        'wp-logo
tm-suspend
updraft_admin_node',
                    )
                    ->set_help_text(
                        __(
                            'One per line. Admin bar menus to be removed. Use: <i>adminbar-item-node-id</i>.<br/>These nodes will be thrown into <a href="https://developer.wordpress.org/reference/classes/wp_admin_bar/remove_node/#finding-toolbar-node-ids" target="_blank">remove_node</a>. Check the docs on how to find an admin bar node id.',
                            'fuerte-wp',
                        ),
                    ),
            ])

            ->add_tab(__('IP & User Lists', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_ip_lists_header',
                    __('IP Whitelist & Blacklist', 'fuerte-wp'),
                )->set_html(
                    '<p>' . __('Manage IP addresses and ranges that are allowed or blocked.', 'fuerte-wp') . '</p>' .
                    '<p>' . __('Supports single IPs, IPv4/IPv6 addresses, and CIDR notation (e.g., 192.168.1.0/24).', 'fuerte-wp') . '</p>'
                ),

                Field::make(
                    'textarea',
                    'fuertewp_username_whitelist',
                    __('Username Whitelist', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_help_text(__('One username per line. Only these users can log in (leave empty for no restriction).', 'fuerte-wp')),

                Field::make(
                    'separator',
                    'fuertewp_username_separator',
                    __('Username Blacklist', 'fuerte-wp'),
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_block_default_users',
                    __('Block Common Admin Usernames', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(__('Automatically block common admin usernames like "admin", "administrator", "root".', 'fuerte-wp')),

                Field::make(
                    'textarea',
                    'fuertewp_username_blacklist',
                    __('Username Blacklist', 'fuerte-wp'),
                )
                    ->set_rows(4)
                    ->set_help_text(__('One username per line. These usernames cannot register or log in.', 'fuerte-wp')),

                Field::make(
                    'separator',
                    'fuertewp_registration_separator',
                    __('Registration Protection', 'fuerte-wp'),
                ),

                Field::make(
                    'checkbox',
                    'fuertewp_registration_protect',
                    __('Enable Registration Protection', 'fuerte-wp'),
                )
                    ->set_default_value('yes')
                    ->set_option_value('yes')
                    ->set_help_text(__('Apply username blacklist to user registrations.', 'fuerte-wp')),
            ])

            ->add_tab(__('Failed Logins', 'fuerte-wp'), [
                Field::make(
                    'html',
                    'fuertewp_login_logs_viewer',
                    __('Failed Login Attempts', 'fuerte-wp'),
                )
                    ->set_html($this->render_login_logs_viewer()),
            ]);
    }

    /**
     * Render login logs viewer HTML.
     *
     * @since 1.7.0
     * @return string HTML content
     */
    private function render_login_logs_viewer()
    {
        // Enqueue admin scripts
        wp_enqueue_script(
            'fuertewp-login-admin',
            FUERTEWP_URL . 'admin/js/fuerte-wp-login-admin.js',
            ['jquery'],
            FUERTEWP_VERSION,
            true
        );

        wp_localize_script('fuertewp-login-admin', 'fuertewp_login_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fuertewp_admin_nonce'),
            'i18n' => [
                'confirm_clear' => __('Are you sure you want to clear all login logs?', 'fuerte-wp'),
                'confirm_reset' => __('Are you sure you want to reset all lockouts?', 'fuerte-wp'),
                'loading' => __('Loading...', 'fuerte-wp'),
                'error' => __('An error occurred', 'fuerte-wp'),
            ],
        ]);

        // Get stats
        $logger = new Fuerte_Wp_Login_Logger();
        $stats = $logger->get_lockout_stats();

        // Build HTML
        ob_start();
        ?>
        <div id="fuertewp-login-logs">
            <!-- Stats Overview -->
            <div class="fuertewp-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                <div class="stat-box" style="padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;"><?php esc_html_e('Total Lockouts', 'fuerte-wp'); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo (int)$stats['total_lockouts']; ?></p>
                </div>
                <div class="stat-box" style="padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;"><?php esc_html_e('Active Lockouts', 'fuerte-wp'); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo (int)$stats['active_lockouts']; ?></p>
                </div>
                <div class="stat-box" style="padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;"><?php esc_html_e('Failed Today', 'fuerte-wp'); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo (int)$stats['failed_today']; ?></p>
                </div>
                <div class="stat-box" style="padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;"><?php esc_html_e('Failed This Week', 'fuerte-wp'); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo (int)$stats['failed_week']; ?></p>
                </div>
            </div>

            <!-- Actions -->
            <div class="fuertewp-actions" style="margin-bottom: 20px; padding: 15px; background: #f6f7f7; border-radius: 4px;">
                <button type="button" id="fuertewp-export-attempts" class="button button-primary">
                    <?php esc_html_e('Export CSV', 'fuerte-wp'); ?>
                </button>
                <button type="button" id="fuertewp-clear-logs" class="button button-secondary">
                    <?php esc_html_e('Clear Failed Attempts', 'fuerte-wp'); ?>
                </button>
                <button type="button" id="fuertewp-reset-lockouts" class="button button-secondary">
                    <?php esc_html_e('Reset All Lockouts', 'fuerte-wp'); ?>
                </button>
            </div>

            <!-- Logs Table Container -->
            <div id="fuertewp-logs-table-container">
                <p><?php esc_html_e('Loading failed login attempts...', 'fuerte-wp'); ?></p>
            </div>
        </div>

        <style>
        #fuertewp-login-logs .column-ip { width: 120px; }
        #fuertewp-login-logs .column-status { width: 100px; }
        #fuertewp-login-logs .column-actions { width: 100px; }
        #fuertewp-login-logs .status-success { color: #00a32a; font-weight: bold; }
        #fuertewp-login-logs .status-failed { color: #d63638; font-weight: bold; }
        #fuertewp-login-logs .status-blocked { color: #d63638; font-weight: bold; }
        #fuertewp-login-logs .user-agent-cell {
            max-width: 450px;
            overflow-x: auto;
            white-space: nowrap;
            font-family: monospace;
            font-size: 12px;
            background: #f8f9f9;
            padding: 4px;
            border-radius: 3px;
            border: 1px solid #e0e0e0;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Check options & clears Fuerte-WP options cache.
     */
    public function fuertewp_theme_options_saved($data, $options)
    {
        global $current_user;

        // Check if current_user is a super user, if not, add it
        if (!isset($current_user)) {
            $current_user = wp_get_current_user();
        }

        $super_users = carbon_get_theme_option('fuertewp_super_users');

        if (empty($super_users) || !is_array($super_users)) {
            // No users at all. Add current_user back as super user
            carbon_set_theme_option(
                'fuertewp_super_users',
                $current_user->user_email,
            );
        } else {
            if (!in_array($current_user->user_email, $super_users)) {
                // Current_user not found in the array, add it back as super user
                array_unshift($super_users, $current_user->user_email);

                carbon_set_theme_option('fuertewp_super_users', $super_users);
            }
        }

        // Set default login security values if not already set
        $login_enable = carbon_get_theme_option('fuertewp_login_enable');
        if (empty($login_enable)) {
            carbon_set_theme_option('fuertewp_login_enable', 'enabled');
        }

        $registration_enable = carbon_get_theme_option('fuertewp_registration_enable');
        if (empty($registration_enable)) {
            carbon_set_theme_option('fuertewp_registration_enable', 'enabled');
        }

        // Set other default login security values if needed
        $max_attempts = carbon_get_theme_option('fuertewp_login_max_attempts');
        if (empty($max_attempts)) {
            carbon_set_theme_option('fuertewp_login_max_attempts', 5);
        }

        $lockout_duration = carbon_get_theme_option('fuertewp_login_lockout_duration');
        if (empty($lockout_duration)) {
            carbon_set_theme_option('fuertewp_login_lockout_duration', 15);
        }

        // Clears options cache
        $version_to_string = str_replace('.', '', FUERTEWP_VERSION);
        delete_transient('fuertewp_cache_config_' . $version_to_string);
    }

    /**
     * Plugins list Settings link.
     */
    public function add_action_links($links)
    {
        global $fuertewp, $current_user;

        if (!isset($current_user)) {
            $current_user = wp_get_current_user();
        }

        // Check if fuertewp config exists and has super_users
        if (
            !isset($fuertewp)
            || !is_array($fuertewp)
            || empty($fuertewp['super_users'])
        ) {
            return $links;
        }

        if (
            !in_array(
                strtolower($current_user->user_email),
                $fuertewp['super_users'],
            )
            || (defined('FUERTEWP_FORCE') && true === FUERTEWP_FORCE)
        ) {
            return $links;
        }

        $fuertewp_link = [
            /* translators: %s: plugin settings URL */
            sprintf(
                __('<a href="%s">Settings</a>', 'fuerte-wp'),
                admin_url(
                    'options-general.php?page=crb_carbon_fields_container_fuerte-wp.php',
                ),
            ),
        ];

        return array_merge($links, $fuertewp_link);
    }
}
