<?php
/**
 * Circle.so Events Shortcode Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Shortcode_Handler
 * 
 * Handles shortcodes for Circle.so events
 */
class Circle_Events_Shortcode_Handler {
    /**
     * Settings manager instance
     * 
     * @var Circle_Events_Settings_Manager
     */
    private Circle_Events_Settings_Manager $settings_manager;

    /**
     * Template manager instance
     * 
     * @var Circle_Events_Template_Manager
     */
    private Circle_Events_Template_Manager $template_manager;

    /**
     * Cache manager instance
     * 
     * @var Circle_Events_Cache_Manager
     */
    private Circle_Events_Cache_Manager $cache_manager;

    /**
     * Input sanitizer instance
     * 
     * @var Circle_Events_Input_Sanitizer
     */
    private Circle_Events_Input_Sanitizer $input_sanitizer;

    /**
     * Constructor
     *
     * @param Circle_Events_Settings_Manager $settings_manager Settings manager instance
     * @param Circle_Events_Template_Manager $template_manager Template manager instance
     * @param Circle_Events_Cache_Manager    $cache_manager    Cache manager instance
     * @param Circle_Events_Input_Sanitizer  $input_sanitizer  Input sanitizer instance
     */
    public function __construct(
        Circle_Events_Settings_Manager $settings_manager,
        Circle_Events_Template_Manager $template_manager,
        Circle_Events_Cache_Manager $cache_manager,
        Circle_Events_Input_Sanitizer $input_sanitizer
    ) {
        $this->settings_manager = $settings_manager;
        $this->template_manager = $template_manager;
        $this->cache_manager = $cache_manager;
        $this->input_sanitizer = $input_sanitizer;
    }

    /**
     * Initialize shortcode handlers
     */
    public function init(): void {
        // Register the main events shortcode
        add_shortcode('circle_events', [$this, 'events_shortcode']);
        
        // Register additional shortcodes
        add_shortcode('circle_events_list', [$this, 'events_list_shortcode']);
        add_shortcode('circle_events_calendar', [$this, 'events_calendar_shortcode']);
    }

    /**
     * Main events shortcode callback
     *
     * @param array $atts Shortcode attributes
     * @return string The shortcode output
     */
    public function events_shortcode(array $atts = []): string {
        // Parse attributes
        $atts = shortcode_atts([
            'display' => 'list', // list, calendar
            'limit' => (string) $this->settings_manager->get_events_count(),
            'status' => 'upcoming', // upcoming, past, all
            'show_date' => '1',
            'show_time' => '1',
            'show_description' => '1',
            'show_location' => '1',
        ], $atts);
        
        // Sanitize attributes
        $atts = $this->input_sanitizer->sanitize_array($atts);
        
        // Determine display type
        $display_type = in_array($atts['display'], ['list', 'calendar'], true) ? $atts['display'] : 'list';
        
        // Determine status filter
        $status = in_array($atts['status'], ['upcoming', 'past', 'all'], true) ? $atts['status'] : 'upcoming';
        
        // Set limit
        $limit = is_numeric($atts['limit']) ? (int) $atts['limit'] : $this->settings_manager->get_events_count();
        
        // Set display options
        $display_options = [
            'show_date' => $this->input_sanitizer->sanitize_checkbox($atts['show_date']),
            'show_time' => $this->input_sanitizer->sanitize_checkbox($atts['show_time']),
            'show_description' => $this->input_sanitizer->sanitize_checkbox($atts['show_description']),
            'show_location' => $this->input_sanitizer->sanitize_checkbox($atts['show_location']),
        ];
        
        // Get events data with filters
        $filters = [
            'limit' => $limit,
            'status' => $status,
        ];
        
        // Get cached events
        $events = $this->cache_manager->get_events_data(false, $filters);
        
        // Render the appropriate template
        if ($display_type === 'calendar') {
            return $this->template_manager->render_calendar_template($events, $display_options);
        }
        
        // Default to list view
        $pagination = [];
        
        // Handle pagination
        if (isset($_GET['event_page']) && is_numeric($_GET['event_page'])) {
            $current_page = max(1, (int) $_GET['event_page']);
            $per_page = $limit;
            
            // Calculate total pages
            $total_items = count($events);
            $total_pages = ceil($total_items / $per_page);
            
            // Validate current page
            $current_page = min($current_page, max(1, $total_pages));
            
            // Slice the events array for the current page
            $offset = ($current_page - 1) * $per_page;
            $events = array_slice($events, $offset, $per_page);
            
            // Set pagination data
            $pagination = [
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
            ];
        }
        
        return $this->template_manager->render_list_template($events, $display_options, $pagination);
    }

    /**
     * Events list shortcode callback
     *
     * @param array $atts Shortcode attributes
     * @return string The shortcode output
     */
    public function events_list_shortcode(array $atts = []): string {
        // Force list display type
        $atts['display'] = 'list';
        return $this->events_shortcode($atts);
    }

    /**
     * Events calendar shortcode callback
     *
     * @param array $atts Shortcode attributes
     * @return string The shortcode output
     */
    public function events_calendar_shortcode(array $atts = []): string {
        // Force calendar display type
        $atts['display'] = 'calendar';
        return $this->events_shortcode($atts);
    }
} 