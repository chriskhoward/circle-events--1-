<?php
/**
 * Circle.so Events Cache Manager
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Cache_Manager
 * 
 * Manages caching of Circle.so events data
 */
class Circle_Events_Cache_Manager {
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
     * Cache key for events data
     * 
     * @var string
     */
    private string $cache_key = 'circle_events_data';

    /**
     * Constructor
     *
     * @param Circle_Events_Settings_Manager $settings_manager Settings manager instance
     * @param Circle_Events_API              $api              API instance
     */
    public function __construct(
        Circle_Events_Settings_Manager $settings_manager,
        Circle_Events_API $api
    ) {
        $this->settings_manager = $settings_manager;
        $this->api = $api;
    }

    /**
     * Initialize cache manager
     */
    public function init(): void {
        // Schedule cache refresh event if not already scheduled
        if (!wp_next_scheduled('circle_events_cache_refresh')) {
            wp_schedule_event(time(), 'hourly', 'circle_events_cache_refresh');
        }
        
        // Add action for scheduled refresh
        add_action('circle_events_cache_refresh', [$this, 'refresh_cache']);
    }

    /**
     * Get cached events data
     *
     * @param bool  $force_refresh Whether to force a refresh
     * @param array $filters       Filters to apply to events data
     * @return array The events data
     */
    public function get_events_data(bool $force_refresh = false, array $filters = []): array {
        // Check for cached data
        $events = [];
        $cached_data = get_transient($this->cache_key);
        
        // Refresh cache if needed
        if ($force_refresh || $cached_data === false) {
            $events = $this->refresh_cache();
        } else {
            $events = $cached_data;
        }
        
        // Apply filters
        if (!empty($filters)) {
            $events = $this->filter_events($events, $filters);
        }
        
        return $events;
    }

    /**
     * Refresh events cache
     *
     * @return array The refreshed events data
     */
    public function refresh_cache(): array {
        // Get API settings
        $events_count = $this->settings_manager->get_events_count();
        
        // Fetch events from API
        $events = $this->api->get_events($events_count);
        
        // Handle error
        if (is_wp_error($events)) {
            // Log error
            error_log('Circle Events: Failed to refresh cache - ' . $events->get_error_message());
            
            // Return empty array on error
            return [];
        }
        
        // Store in cache
        $cache_duration = $this->settings_manager->get_cache_duration();
        set_transient($this->cache_key, $events, $cache_duration);
        
        return $events;
    }

    /**
     * Clear events cache
     *
     * @return bool True if cache was cleared
     */
    public function clear_cache(): bool {
        return delete_transient($this->cache_key);
    }

    /**
     * Filter events by various criteria
     *
     * @param array $events  The events to filter
     * @param array $filters The filters to apply
     * @return array The filtered events
     */
    private function filter_events(array $events, array $filters): array {
        $filtered_events = $events;
        
        // Filter by status (upcoming, past, all)
        if (isset($filters['status'])) {
            $filtered_events = $this->filter_events_by_date($filtered_events, $filters['status']);
        }
        
        // Filter by limit
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $filtered_events = array_slice($filtered_events, 0, (int) $filters['limit']);
        }
        
        return $filtered_events;
    }

    /**
     * Filter events by date
     *
     * @param array  $events The events to filter
     * @param string $status The status filter (upcoming, past, all)
     * @return array The filtered events
     */
    private function filter_events_by_date(array $events, string $status = 'upcoming'): array {
        if (empty($events)) {
            return [];
        }
        
        $filtered_events = [];
        $now = current_time('timestamp');
        
        foreach ($events as $event) {
            $event_time = isset($event['start_time']) ? strtotime($event['start_time']) : 0;
            
            // Skip events with invalid timestamps
            if ($event_time === false || $event_time === 0) {
                continue;
            }
            
            switch ($status) {
                case 'upcoming':
                    if ($event_time >= $now) {
                        $filtered_events[] = $event;
                    }
                    break;
                    
                case 'past':
                    if ($event_time < $now) {
                        $filtered_events[] = $event;
                    }
                    break;
                    
                case 'all':
                default:
                    $filtered_events[] = $event;
                    break;
            }
        }
        
        return $filtered_events;
    }
} 