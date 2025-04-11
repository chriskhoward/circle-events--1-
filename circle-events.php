<?php
/**
 * Plugin Name: Circle.so Events Integration
 * Description: Displays events from Circle.so community on your WordPress site.
 * Version: 1.1.0
 * Author: Circle Events Team
 * Text Domain: circle-events
 * Domain Path: /languages
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define plugin constants
 */
define('CIRCLE_EVENTS_VERSION', '1.1.0');
define('CIRCLE_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CIRCLE_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The main plugin class responsible for coordinating all components
 */
class Circle_Events {
    /**
     * The single instance of the class.
     *
     * @var Circle_Events|null
     */
    private static ?Circle_Events $instance = null;

    /**
     * Encryption handler instance
     * 
     * @var Circle_Events_Encryption
     */
    private Circle_Events_Encryption $encryption;

    /**
     * Input sanitizer instance
     * 
     * @var Circle_Events_Input_Sanitizer
     */
    private Circle_Events_Input_Sanitizer $input_sanitizer;

    /**
     * Output escaper instance
     * 
     * @var Circle_Events_Output_Escaper
     */
    private Circle_Events_Output_Escaper $output_escaper;

    /**
     * Rate limiter instance
     * 
     * @var Circle_Events_Rate_Limiter
     */
    private Circle_Events_Rate_Limiter $rate_limiter;

    /**
     * Error handler instance
     * 
     * @var Circle_Events_Error_Handler
     */
    private Circle_Events_Error_Handler $error_handler;

    /**
     * Capability checker instance
     * 
     * @var Circle_Events_Capability_Checker
     */
    private Circle_Events_Capability_Checker $capability_checker;

    /**
     * Migration handler instance
     * 
     * @var Circle_Events_Migration
     */
    private Circle_Events_Migration $migration;

    /**
     * Settings manager instance
     * 
     * @var Circle_Events_Settings_Manager
     */
    private Circle_Events_Settings_Manager $settings_manager;

    /**
     * API instance
     * 
     * @var Circle_Events_API
     */
    private Circle_Events_API $api;

    /**
     * Cache manager instance
     * 
     * @var Circle_Events_Cache_Manager
     */
    private Circle_Events_Cache_Manager $cache_manager;

    /**
     * Template manager instance
     * 
     * @var Circle_Events_Template_Manager
     */
    private Circle_Events_Template_Manager $template_manager;

    /**
     * AJAX handler instance
     * 
     * @var Circle_Events_Ajax_Handler
     */
    private Circle_Events_Ajax_Handler $ajax_handler;

    /**
     * Shortcode handler instance
     * 
     * @var Circle_Events_Shortcode_Handler
     */
    private Circle_Events_Shortcode_Handler $shortcode_handler;

    /**
     * Main Circle_Events Instance.
     * Ensures only one instance of Circle_Events is loaded or can be loaded.
     *
     * @return Circle_Events
     */
    public static function instance(): Circle_Events {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Circle_Events Constructor.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies(): void {
        // Create necessary directories if they don't exist
        $this->create_plugin_directories();
        
        // Include core classes
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-encryption.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-input-sanitizer.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-output-escaper.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-rate-limiter.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-error-handler.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-capability-checker.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-migration.php';
        
        // Include manager classes
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-settings-manager.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-api.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-cache-manager.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-template-manager.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-ajax-handler.php';
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-shortcode-handler.php';
        
        // Include widget class
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-widget.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components(): void {
        // Initialize core components
        $this->encryption = new Circle_Events_Encryption();
        $this->input_sanitizer = new Circle_Events_Input_Sanitizer();
        $this->output_escaper = new Circle_Events_Output_Escaper();
        $this->rate_limiter = new Circle_Events_Rate_Limiter();
        $this->error_handler = new Circle_Events_Error_Handler();
        $this->capability_checker = new Circle_Events_Capability_Checker();
        $this->migration = new Circle_Events_Migration($this->encryption);
        
        // Initialize managers with dependency injection
        $this->settings_manager = new Circle_Events_Settings_Manager(
            $this->encryption,
            $this->input_sanitizer,
            $this->output_escaper
        );
        
        $this->api = new Circle_Events_API(
            $this->settings_manager->get_api_token(),
            $this->settings_manager->get_community_id(),
            $this->rate_limiter,
            $this->error_handler
        );
        
        $this->cache_manager = new Circle_Events_Cache_Manager(
            $this->settings_manager,
            $this->api
        );
        
        $this->template_manager = new Circle_Events_Template_Manager(
            $this->settings_manager,
            $this->output_escaper
        );
        
        $this->ajax_handler = new Circle_Events_Ajax_Handler(
            $this->settings_manager,
            $this->template_manager,
            $this->api,
            $this->cache_manager,
            $this->input_sanitizer,
            $this->capability_checker
        );
        
        $this->shortcode_handler = new Circle_Events_Shortcode_Handler(
            $this->settings_manager,
            $this->template_manager,
            $this->cache_manager,
            $this->input_sanitizer
        );
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks(): void {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Initialize settings manager
        $this->settings_manager->init();
        
        // Initialize cache manager
        $this->cache_manager->init();
        
        // Initialize template manager
        $this->template_manager->register_template_hooks();
        
        // Initialize AJAX handler
        $this->ajax_handler->init();
        
        // Initialize shortcode handler
        $this->shortcode_handler->init();
        
        // Register widget
        add_action('widgets_init', [$this, 'register_widgets']);
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Run migrations if needed
        add_action('admin_init', [$this, 'check_version']);
    }

    /**
     * Create plugin directories
     */
    private function create_plugin_directories(): void {
        // Create directories if they don't exist
        $directories = [
            'includes',
            'templates',
            'assets/css',
            'assets/js',
        ];
        
        foreach ($directories as $directory) {
            $path = CIRCLE_EVENTS_PLUGIN_DIR . $directory;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
    }

    /**
     * Check if migration is needed
     */
    public function check_version(): void {
        // Get the previous version
        $previous_version = get_option('circle_events_version', '');
        
        // Run migrations if needed
        if (empty($previous_version) || version_compare($previous_version, CIRCLE_EVENTS_VERSION, '<')) {
            $this->migration->run_migrations(CIRCLE_EVENTS_VERSION, $previous_version);
        }
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        // Add default options
        $default_settings = [
            'api_token' => '',
            'community_id' => '',
            'cache_duration' => 3600, // 1 hour
            'events_count' => 10,
            'display_options' => [
                'show_date' => true,
                'show_time' => true,
                'show_description' => true,
                'show_location' => true,
            ],
        ];
        
        add_option('circle_events_settings', $default_settings);
        add_option('circle_events_version', CIRCLE_EVENTS_VERSION);
        
        // Create a transient to flag redirect after activation
        set_transient('circle_events_activation_redirect', true, 30);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear any scheduled events
        wp_clear_scheduled_hook('circle_events_sync');
        
        // Clear transients
        delete_transient('circle_events_data');
    }

    /**
     * Register widgets
     */
    public function register_widgets(): void {
        register_widget('Circle_Events_Widget');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'circle-events-style',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/css/circle-events.css',
            [],
            CIRCLE_EVENTS_VERSION
        );
        
        wp_enqueue_script(
            'circle-events-script',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/js/circle-events.js',
            ['jquery'],
            CIRCLE_EVENTS_VERSION,
            true
        );
        
        wp_localize_script(
            'circle-events-script',
            'circle_events_params',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('circle_events_ajax_nonce'),
            ]
        );
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets(string $hook): void {
        if ('settings_page_circle-events-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'circle-events-admin-style',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/css/circle-events-admin.css',
            [],
            CIRCLE_EVENTS_VERSION
        );
        
        wp_enqueue_script(
            'circle-events-admin-script',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/js/circle-events-admin.js',
            ['jquery'],
            CIRCLE_EVENTS_VERSION,
            true
        );
        
        // Localize script with admin data
        wp_localize_script(
            'circle-events-admin-script',
            'circle_events_admin',
            [
                'nonce' => wp_create_nonce('circle_events_admin_nonce'),
                'ajax_url' => admin_url('admin-ajax.php'),
            ]
        );
    }
}

/**
 * Initialize the plugin
 */
function circle_events_init() {
    return Circle_Events::instance();
}

// Start the plugin
circle_events_init();