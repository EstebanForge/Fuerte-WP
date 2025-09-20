<?php

/**
 * The file that defines the core plugin class.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://actitud.xyz
 * @since      1.3.0
 */

// No access outside WP
defined('ABSPATH') || die();

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.3.0
 * @author     Esteban Cuevas <esteban@attitude.cl>
 */
class Fuerte_Wp
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.3.0
     * @var      Fuerte_Wp_Loader       Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.3.0
     * @var      string       The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.3.0
     * @var      string       The current version of the plugin.
     */
    protected $version;

    public $pagenow;
    public $fuertewp;
    protected $enforcer;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.3.0
     */
    public function __construct()
    {
        if (defined('FUERTEWP_VERSION')) {
            $this->version = FUERTEWP_VERSION;
        } else {
            $this->version = '0.0.1';
        }

        $this->plugin_name = 'fuerte-wp';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

        $this->run_enforcer();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Fuerte_Wp_Loader. Orchestrates the hooks of the plugin.
     * - Fuerte_Wp_i18n. Defines internationalization functionality.
     * - Fuerte_Wp_Admin. Defines all hooks for the admin area.
     * - Fuerte_Wp_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.3.0
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__))
            . 'includes/class-fuerte-wp-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__))
            . 'includes/class-fuerte-wp-i18n.php';

        /*
         * The class responsible for defining all actions that occur in the admin area.
         * Load conditionally to improve performance
         */
        if (
            is_admin()
            || wp_doing_ajax()
            || (function_exists('wp_doing_rest') && wp_doing_rest())
        ) {
            require_once plugin_dir_path(dirname(__FILE__))
                . 'admin/class-fuerte-wp-admin.php';
        }

        /*
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site. Load conditionally to improve performance
         */
        if (!is_admin()) {
            require_once plugin_dir_path(dirname(__FILE__))
                . 'public/class-fuerte-wp-public.php';
        }

        $this->loader = new Fuerte_Wp_Loader();

        /**
         * The main Enforcer class.
         */
        require_once plugin_dir_path(dirname(__FILE__))
            . 'includes/class-fuerte-wp-enforcer.php';

        /**
         * Extra classes.
         */
        require_once plugin_dir_path(dirname(__FILE__))
            . 'includes/class-fuerte-wp-two-factor.php';

        /**
         * Auto-update manager class.
         */
        require_once plugin_dir_path(dirname(__FILE__))
            . 'includes/class-fuerte-wp-auto-update-manager.php';
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Fuerte_Wp_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.3.0
     */
    private function set_locale()
    {
        $plugin_i18n = new Fuerte_Wp_i18n();

        $this->loader->add_action(
            'plugins_loaded',
            $plugin_i18n,
            'load_plugin_textdomain',
        );
    }

    /**
     * Runs the main Enforcer Class at plugins_loaded.
     */
    private function run_enforcer()
    {
        $this->enforcer = new Fuerte_Wp_Enforcer();

        // https://codex.wordpress.org/Plugin_API/Action_Reference
        $this->loader->add_action('init', $this->enforcer, 'run');
    }

    /**
     * Setup admin hooks after WordPress is fully loaded.
     *
     * @since    1.6.0
     */
    public function setup_admin_hooks()
    {
        $this->define_admin_hooks();
    }

    /**
     * Check if current user can manage options, safely.
     *
     * @since    1.6.0
     * @return   bool
     */
    private function can_manage_options()
    {
        // Check if WordPress functions are available
        if (
            !function_exists('wp_get_current_user')
            || !function_exists('current_user_can')
        ) {
            error_log('Fuerte-WP: WordPress functions not available yet');

            return false;
        }

        $result = current_user_can('manage_options');
        error_log(
            'Fuerte-WP: current_user_can result: '
                . ($result ? 'true' : 'false'),
        );

        return $result;
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.3.0
     */
    private function define_admin_hooks()
    {
        error_log('Fuerte-WP: define_admin_hooks() called');
        // Set up admin hooks, but delay the capability check until WordPress is loaded
        if (is_admin() && class_exists('Fuerte_Wp_Admin')) {
            error_log(
                'Fuerte-WP: Setting up admin hooks with delayed capability check',
            );
            $plugin_admin = new Fuerte_Wp_Admin(
                $this->get_plugin_name(),
                $this->get_version(),
            );

            $this->loader->add_action(
                'admin_enqueue_scripts',
                $plugin_admin,
                'enqueue_styles',
            );
            $this->loader->add_action(
                'admin_enqueue_scripts',
                $plugin_admin,
                'enqueue_scripts',
            );

            // Carbon Fields registration should happen immediately
            $this->loader->add_action(
                'carbon_fields_register_fields',
                $plugin_admin,
                'fuertewp_plugin_options',
            );

            // Add capability check for other admin functionality
            add_action('admin_init', function () use ($plugin_admin) {
                if ($this->can_manage_options()) {
                    error_log(
                        'Fuerte-WP: Admin capabilities confirmed, enabling full admin functionality',
                    );
                    // Add other admin-specific hooks here if needed
                }
            });

            $this->loader->add_action(
                'carbon_fields_theme_options_container_saved',
                $plugin_admin,
                'fuertewp_theme_options_saved',
                10,
                2,
            );

            $this->loader->add_action(
                'plugin_action_links_' . FUERTEWP_PLUGIN_BASE,
                $plugin_admin,
                'add_action_links',
            );
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.3.0
     */
    private function define_public_hooks()
    {
        // Only load public functionality when needed
        if (!is_admin() && class_exists('Fuerte_Wp_Public')) {
            $plugin_public = new Fuerte_Wp_Public(
                $this->get_plugin_name(),
                $this->get_version(),
            );

            $this->loader->add_action(
                'wp_enqueue_scripts',
                $plugin_public,
                'enqueue_styles',
            );
            $this->loader->add_action(
                'wp_enqueue_scripts',
                $plugin_public,
                'enqueue_scripts',
            );
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.3.0
     */
    public function run()
    {
        global $fuertewp, $pagenow;

        $this->fuertewp = $fuertewp;
        $this->pagenow = $pagenow;
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.3.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.3.0
     * @return    Fuerte_Wp_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.3.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
