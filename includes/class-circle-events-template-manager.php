<?php
/**
 * Circle.so Events Template Manager
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Template_Manager
 * 
 * Manages template rendering for Circle.so events
 */
class Circle_Events_Template_Manager {
    /**
     * Settings manager instance
     * 
     * @var Circle_Events_Settings_Manager
     */
    private Circle_Events_Settings_Manager $settings_manager;

    /**
     * Output escaper instance
     * 
     * @var Circle_Events_Output_Escaper
     */
    private Circle_Events_Output_Escaper $output_escaper;

    /**
     * Constructor
     *
     * @param Circle_Events_Settings_Manager $settings_manager Settings manager instance
     * @param Circle_Events_Output_Escaper   $output_escaper   Output escaper instance
     */
    public function __construct(
        Circle_Events_Settings_Manager $settings_manager,
        Circle_Events_Output_Escaper $output_escaper
    ) {
        $this->settings_manager = $settings_manager;
        $this->output_escaper = $output_escaper;
    }

    /**
     * Render events in widget format
     *
     * @param array $events         Events data to render
     * @param array $display_options Display options
     * @return string The rendered HTML
     */
    public function render_widget_template(array $events, array $display_options = []): string {
        if (empty($events)) {
            return '<div class="circle-events-empty">' . 
                esc_html__('No upcoming events found.', 'circle-events') . 
                '</div>';
        }
        
        // If display options not provided, use defaults from settings
        if (empty($display_options)) {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = $this->get_template_path('events-widget.php');
        include $template_path;
        
        // Get the output
        $output = ob_get_clean();
        
        return $output ?: '';
    }

    /**
     * Render events in list format
     *
     * @param array $events         Events data to render
     * @param array $display_options Display options
     * @param array $pagination     Pagination data
     * @return string The rendered HTML
     */
    public function render_list_template(
        array $events, 
        array $display_options = [], 
        array $pagination = []
    ): string {
        if (empty($events)) {
            return '<div class="circle-events-empty">' . 
                esc_html__('No upcoming events found.', 'circle-events') . 
                '</div>';
        }
        
        // If display options not provided, use defaults from settings
        if (empty($display_options)) {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = $this->get_template_path('events-list.php');
        
        // Extract pagination variables if provided
        if (!empty($pagination)) {
            extract($pagination);
        }
        
        include $template_path;
        
        // Get the output
        $output = ob_get_clean();
        
        return $output ?: '';
    }

    /**
     * Render events in calendar format
     *
     * @param array $events         Events data to render
     * @param array $display_options Display options
     * @return string The rendered HTML
     */
    public function render_calendar_template(array $events, array $display_options = []): string {
        if (empty($events)) {
            return '<div class="circle-events-empty">' . 
                esc_html__('No upcoming events found.', 'circle-events') . 
                '</div>';
        }
        
        // If display options not provided, use defaults from settings
        if (empty($display_options)) {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = $this->get_template_path('events-calendar.php');
        include $template_path;
        
        // Get the output
        $output = ob_get_clean();
        
        return $output ?: '';
    }

    /**
     * Get the template path
     *
     * @param string $template The template file name
     * @return string The full path to the template
     */
    private function get_template_path(string $template): string {
        // Check if template exists in theme
        $theme_template = locate_template('circle-events/' . $template);
        
        // Use theme template if it exists, otherwise use plugin template
        if ($theme_template) {
            return $theme_template;
        }
        
        return CIRCLE_EVENTS_PLUGIN_DIR . 'templates/' . $template;
    }

    /**
     * Register template hooks
     */
    public function register_template_hooks(): void {
        // Add filter for template include
        add_filter('template_include', [$this, 'template_include']);
    }

    /**
     * Filter template include
     *
     * @param string $template The path of the template to include
     * @return string The filtered template path
     */
    public function template_include(string $template): string {
        // Custom template handling could be added here
        return $template;
    }
} 