<?php
/**
 * Circle.so Events Output Escaper
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Output_Escaper
 * 
 * Handles proper escaping of all output data
 */
class Circle_Events_Output_Escaper {

    /**
     * Escape an event for HTML output
     *
     * @param array $event The event data to escape
     * @return array The escaped event data
     */
    public function escape_event(array $event): array {
        $escaped = [];
        
        // Simple text fields - use esc_html
        foreach (['id', 'title', 'status'] as $field) {
            $escaped[$field] = isset($event[$field]) ? esc_html($event[$field]) : '';
        }
        
        // Date fields - format and escape
        foreach (['start_time', 'end_time'] as $field) {
            if (!empty($event[$field])) {
                $timestamp = strtotime($event[$field]);
                if ($timestamp) {
                    $date_format = get_option('date_format');
                    $time_format = get_option('time_format');
                    $escaped[$field] = esc_html(date_i18n("{$date_format} {$time_format}", $timestamp));
                    $escaped[$field . '_raw'] = esc_attr($event[$field]); // Keep raw value for data attributes
                } else {
                    $escaped[$field] = esc_html($event[$field]);
                }
            } else {
                $escaped[$field] = '';
            }
        }
        
        // HTML content field - allow limited HTML tags
        if (isset($event['description'])) {
            $allowed_html = [
                'a' => [
                    'href' => true,
                    'title' => true,
                    'target' => true,
                    'rel' => true,
                ],
                'br' => [],
                'em' => [],
                'strong' => [],
                'p' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'h3' => [],
                'h4' => [],
                'h5' => [],
                'h6' => [],
            ];
            $escaped['description'] = wp_kses($event['description'], $allowed_html);
        } else {
            $escaped['description'] = '';
        }
        
        // URL fields - use esc_url
        foreach (['url', 'thumbnail_url'] as $field) {
            $escaped[$field] = isset($event[$field]) ? esc_url($event[$field]) : '';
        }
        
        // Location field - special handling
        if (isset($event['location'])) {
            $escaped['location'] = esc_html($event['location']);
            
            // Map URL if it looks like an address
            if (strpos($event['location'], ',') !== false || 
                preg_match('/\d+\s+[a-zA-Z0-9\s]+/', $event['location'])) {
                $map_query = urlencode($event['location']);
                $escaped['map_url'] = esc_url("https://maps.google.com/maps?q={$map_query}");
            } else {
                $escaped['map_url'] = '';
            }
        } else {
            $escaped['location'] = '';
            $escaped['map_url'] = '';
        }
        
        // Boolean fields - convert to actual booleans
        $escaped['is_online'] = isset($event['is_online']) && $event['is_online'];
        
        // Data attributes - escape for HTML attributes
        $escaped['data_attributes'] = $this->generate_data_attributes($event);
        
        return $escaped;
    }
    
    /**
     * Escape an array of events for HTML output
     *
     * @param array $events The events data to escape
     * @return array The escaped events data
     */
    public function escape_events(array $events): array {
        $escaped_events = [];
        
        foreach ($events as $event) {
            if (is_array($event)) {
                $escaped_events[] = $this->escape_event($event);
            }
        }
        
        return $escaped_events;
    }
    
    /**
     * Generate HTML data attributes for an event
     *
     * @param array $event The event data
     * @return string The escaped data attributes string
     */
    private function generate_data_attributes(array $event): string {
        $data_attrs = [];
        
        // Common data attributes
        $data_mappings = [
            'id' => 'id',
            'start_time' => 'start',
            'end_time' => 'end',
            'is_online' => 'online',
            'status' => 'status',
        ];
        
        foreach ($data_mappings as $field => $attr_name) {
            if (isset($event[$field])) {
                $value = is_bool($event[$field]) ? 
                    ($event[$field] ? 'true' : 'false') : 
                    (string) $event[$field];
                $data_attrs[] = 'data-event-' . $attr_name . '="' . esc_attr($value) . '"';
            }
        }
        
        return implode(' ', $data_attrs);
    }
    
    /**
     * Generate event calendar HTML
     *
     * @param array $event The escaped event data
     * @param array $display_options Display options
     * @return string The event HTML
     */
    public function render_calendar_event(array $event, array $display_options): string {
        $html = '<div class="circle-events-event" ' . $event['data_attributes'] . '>';
        
        // Thumbnail
        if (!empty($event['thumbnail_url'])) {
            $html .= '<div class="circle-events-thumbnail">';
            $html .= '<a href="' . $event['url'] . '" target="_blank">';
            $html .= '<img src="' . $event['thumbnail_url'] . '" alt="' . $event['title'] . '" />';
            $html .= '</a>';
            $html .= '</div>';
        }
        
        $html .= '<div class="circle-events-content">';
        
        // Title
        $html .= '<h3 class="circle-events-title">';
        $html .= '<a href="' . $event['url'] . '" target="_blank">' . $event['title'] . '</a>';
        $html .= '</h3>';
        
        // Date and Time
        if (!empty($display_options['show_date']) && !empty($event['start_time'])) {
            $html .= '<div class="circle-events-date">';
            $html .= '<span class="dashicons dashicons-calendar-alt"></span> ';
            $html .= $event['start_time'];
            $html .= '</div>';
        }
        
        // Location
        if (!empty($display_options['show_location']) && !empty($event['location'])) {
            $html .= '<div class="circle-events-location">';
            $html .= '<span class="dashicons dashicons-location"></span> ';
            
            if (!empty($event['map_url'])) {
                $html .= '<a href="' . $event['map_url'] . '" target="_blank">' . $event['location'] . '</a>';
            } else {
                $html .= $event['location'];
            }
            
            $html .= '</div>';
        }
        
        // Description
        if (!empty($display_options['show_description']) && !empty($event['description'])) {
            $html .= '<div class="circle-events-description">';
            $html .= $event['description'];
            $html .= '</div>';
        }
        
        $html .= '</div>'; // close content
        $html .= '</div>'; // close event
        
        return $html;
    }
    
    /**
     * Generate event list HTML
     *
     * @param array $event The escaped event data
     * @param array $display_options Display options
     * @return string The event HTML
     */
    public function render_list_event(array $event, array $display_options): string {
        $html = '<div class="circle-events-event" ' . $event['data_attributes'] . '>';
        
        // Date
        if (!empty($display_options['show_date']) && !empty($event['start_time'])) {
            $html .= '<div class="circle-events-date">';
            $html .= '<span class="dashicons dashicons-calendar-alt"></span> ';
            $html .= $event['start_time'];
            $html .= '</div>';
        }
        
        // Title
        $html .= '<h3 class="circle-events-title">';
        $html .= '<a href="' . $event['url'] . '" target="_blank">' . $event['title'] . '</a>';
        $html .= '</h3>';
        
        // Location
        if (!empty($display_options['show_location']) && !empty($event['location'])) {
            $html .= '<div class="circle-events-location">';
            $html .= '<span class="dashicons dashicons-location"></span> ';
            
            if (!empty($event['map_url'])) {
                $html .= '<a href="' . $event['map_url'] . '" target="_blank">' . $event['location'] . '</a>';
            } else {
                $html .= $event['location'];
            }
            
            $html .= '</div>';
        }
        
        // Description
        if (!empty($display_options['show_description']) && !empty($event['description'])) {
            $html .= '<div class="circle-events-description">';
            $html .= $event['description'];
            $html .= '</div>';
        }
        
        $html .= '</div>'; // close event
        
        return $html;
    }
    
    /**
     * Generate pagination HTML
     *
     * @param int $current_page Current page number
     * @param int $total_pages Total number of pages
     * @param string $status Current event status
     * @return string The pagination HTML
     */
    public function render_pagination(int $current_page, int $total_pages, string $status): string {
        if ($total_pages <= 1) {
            return '';
        }
        
        $html = '<div class="circle-events-pagination">';
        
        $current_url = remove_query_arg('event_page');
        
        // Previous page
        if ($current_page > 1) {
            $prev_url = add_query_arg([
                'event_page' => $current_page - 1,
                'event_status' => $status
            ], $current_url);
            $html .= '<a href="' . esc_url($prev_url) . '" class="circle-events-page-link">&laquo; ' . 
                esc_html__('Previous', 'circle-events') . '</a>';
        }
        
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $page_url = add_query_arg([
                'event_page' => $i,
                'event_status' => $status
            ], $current_url);
            $html .= '<a href="' . esc_url($page_url) . '" class="circle-events-page-link' . 
                ($i === $current_page ? ' current' : '') . '">' . $i . '</a>';
        }
        
        // Next page
        if ($current_page < $total_pages) {
            $next_url = add_query_arg([
                'event_page' => $current_page + 1,
                'event_status' => $status
            ], $current_url);
            $html .= '<a href="' . esc_url($next_url) . '" class="circle-events-page-link">' . 
                esc_html__('Next', 'circle-events') . ' &raquo;</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
} 