<?php
/**
 * Circle.so Events API Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_API
 * 
 * Handles communication with the Circle.so API
 */
class Circle_Events_API {
    /**
     * API base URL for the Circle.so Admin API V2
     * 
     * @var string
     */
    private string $api_base_url = 'https://app.circle.so/api/admin/v2/';

    /**
     * API token
     * 
     * @var string
     */
    private string $api_token;

    /**
     * Community ID
     * 
     * @var string
     */
    private string $community_id;

    /**
     * Constructor
     * 
     * @param string $api_token    The API token.
     * @param string $community_id The community ID.
     */
    public function __construct(string $api_token, string $community_id) {
        $this->api_token = $api_token;
        $this->community_id = $community_id;
    }

    /**
     * Make a request to the Circle.so API
     * 
     * @param string $endpoint The API endpoint.
     * @param array  $params   The request parameters.
     * @param string $method   The HTTP method.
     * 
     * @return array|WP_Error The response or error.
     */
    private function make_request(string $endpoint, array $params = [], string $method = 'GET') {
        // Define the API URL
        $url = $this->api_base_url . $endpoint;

        // Set up the request args
        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ],
        ];

        // Add method-specific handling
        if ('GET' === $method) {
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
        } else {
            $args['method'] = $method;
            if (!empty($params)) {
                $args['body'] = wp_json_encode($params);
            }
        }

        // Log API request for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Circle Events API Request: %s %s',
                $method,
                $url
            ));
        }

        // Make the request
        $response = wp_remote_request($url, $args);

        // Check for WP_Error
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Circle Events API Error: %s',
                    $response->get_error_message()
                ));
            }
            return $response;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug the raw API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Circle API Raw Response: ' . print_r($response_body, true));
        }
        
        $response_data = json_decode($response_body, true);

        // Check for successful response
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($response_data['error']) 
                ? $response_data['error'] 
                : sprintf(__('API request failed with status code: %d', 'circle-events'), $response_code);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Circle Events API Error: %s (Status: %d)',
                    $error_message,
                    $response_code
                ));
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                ['status' => $response_code]
            );
        }

        return $response_data;
    }

    /**
     * Test API connection
     * 
     * @return array The test result.
     */
    public function test_connection(): array {
        try {
            // Try to get community info as a simple connection test
            $response = $this->make_request('community');

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message(),
                ];
            }

            return [
                'success' => true,
                'message' => __('Connection successful', 'circle-events'),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format event data to standardized structure
     * 
     * @param array $event The raw event data from API.
     * @return array The formatted event data.
     */
    private function format_event(array $event): array {
        // Default values
        $formatted = [
            'id' => '',
            'title' => '',
            'description' => '',
            'start_time' => '',
            'end_time' => '',
            'location' => '',
            'url' => '',
            'is_online' => false,
            'thumbnail_url' => '',
            'status' => '',
            'raw_data' => $event, // Store original data for debugging
        ];
        
        // Log the event data for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Formatting event: ' . print_r($event, true));
        }
        
        // Map the API fields to our standardized format
        if (isset($event['id'])) {
            $formatted['id'] = sanitize_text_field($event['id']);
        }
        
        if (isset($event['name'])) {
            $formatted['title'] = sanitize_text_field($event['name']);
        } elseif (isset($event['title'])) {
            $formatted['title'] = sanitize_text_field($event['title']);
        }
        
        // Handle description - in V2 API it might be in body.body
        if (isset($event['description'])) {
            $formatted['description'] = wp_kses_post($event['description']);
        } elseif (isset($event['body']) && is_array($event['body']) && isset($event['body']['body'])) {
            $formatted['description'] = wp_kses_post($event['body']['body']);
        } elseif (isset($event['body']) && !is_array($event['body'])) {
            $formatted['description'] = wp_kses_post($event['body']);
        }
        
        // Handle dates and times
        if (isset($event['starts_at'])) {
            $formatted['start_time'] = sanitize_text_field($event['starts_at']);
        } elseif (isset($event['start_time'])) {
            $formatted['start_time'] = sanitize_text_field($event['start_time']);
        } elseif (isset($event['published_at'])) {
            // Fallback to published_at if no start time is available
            $formatted['start_time'] = sanitize_text_field($event['published_at']);
        }
        
        if (isset($event['ends_at'])) {
            $formatted['end_time'] = sanitize_text_field($event['ends_at']);
        } elseif (isset($event['end_time'])) {
            $formatted['end_time'] = sanitize_text_field($event['end_time']);
        }
        
        // Handle location
        if (isset($event['location'])) {
            $formatted['location'] = sanitize_text_field($event['location']);
        } elseif (isset($event['space_name'])) {
            // Use space name as location if no specific location is provided
            $formatted['location'] = sanitize_text_field($event['space_name']);
        }
        
        // Handle URL
        if (isset($event['url'])) {
            $formatted['url'] = esc_url_raw($event['url']);
        } elseif (isset($event['permalink'])) {
            $formatted['url'] = esc_url_raw($event['permalink']);
        }
        
        // Handle online status
        if (isset($event['is_online'])) {
            $formatted['is_online'] = (bool) $event['is_online'];
        } elseif (isset($event['location_type']) && $event['location_type'] === 'virtual') {
            $formatted['is_online'] = true;
        }
        
        // Handle thumbnail
        if (isset($event['thumbnail_url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['thumbnail_url']);
        } elseif (isset($event['image_url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['image_url']);
        } elseif (isset($event['cover_image_url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['cover_image_url']);
        } elseif (isset($event['cover_url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['cover_url']);
        } elseif (isset($event['image']) && is_array($event['image']) && isset($event['image']['url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['image']['url']);
        } elseif (isset($event['cover_image']) && is_array($event['cover_image']) && isset($event['cover_image']['url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['cover_image']['url']);
        } elseif (isset($event['space_image_url'])) {
            // Fallback to space image if event has no image
            $formatted['thumbnail_url'] = esc_url_raw($event['space_image_url']);
        }
        
        // Handle status
        if (isset($event['status'])) {
            $formatted['status'] = sanitize_text_field($event['status']);
        }
        
        return $formatted;
    }

    /**
     * Format multiple events
     * 
     * @param array $events The raw events data from API.
     * @return array The formatted events data.
     */
    private function format_events(array $events): array {
        $formatted_events = [];
        foreach ($events as $event) {
            $formatted_events[] = $this->format_event($event);
        }
        
        // Sort events by start time
        usort($formatted_events, function($a, $b) {
            if (empty($a['start_time']) || empty($b['start_time'])) {
                return 0;
            }
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        return $formatted_events;
    }

    /**
     * Get events from Circle.so
     * 
     * @param int $limit The maximum number of events to retrieve.
     * @param array $filters Optional filters for events (category, date range, etc.)
     * 
     * @return array|WP_Error The events or error.
     */
    public function get_events(int $limit = 10, array $filters = []): array|WP_Error {
        try {
            $all_events = [];
            $event_ids = []; // Track event IDs to prevent duplicates
            
            // First try the space events endpoint
            $space_events = $this->get_space_events($limit, $filters);
            if (!is_wp_error($space_events)) {
                foreach ($space_events as $event) {
                    if (!empty($event['id']) && !in_array($event['id'], $event_ids)) {
                        $all_events[] = $event;
                        $event_ids[] = $event['id'];
                    }
                }
            }
            
            // Then try community events endpoint
            $community_events = $this->get_community_events($limit, $filters);
            if (!is_wp_error($community_events)) {
                foreach ($community_events as $event) {
                    if (!empty($event['id']) && !in_array($event['id'], $event_ids)) {
                        $all_events[] = $event;
                        $event_ids[] = $event['id'];
                    }
                }
            }
            
            // Finally try the direct events endpoint
            $direct_events = $this->get_direct_events($limit, $filters);
            if (!is_wp_error($direct_events)) {
                foreach ($direct_events as $event) {
                    if (!empty($event['id']) && !in_array($event['id'], $event_ids)) {
                        $all_events[] = $event;
                        $event_ids[] = $event['id'];
                    }
                }
            }
            
            // Sort all events by start time
            usort($all_events, function($a, $b) {
                if (empty($a['start_time']) || empty($b['start_time'])) {
                    return 0;
                }
                return strtotime($a['start_time']) - strtotime($b['start_time']);
            });
            
            // Apply limit if needed
            if ($limit > 0 && count($all_events) > $limit) {
                $all_events = array_slice($all_events, 0, $limit);
            }
            
            return $all_events;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Circle Events Exception: %s',
                    $e->getMessage()
                ));
            }
            
            return new WP_Error(
                'events_exception',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Get events from Circle.so space events endpoint
     * 
     * @param int $limit The maximum number of events to retrieve.
     * @param array $filters Optional filters for events (category, date range, etc.)
     * 
     * @return array|WP_Error The events or error.
     */
    private function get_space_events(int $limit = 10, array $filters = []) {
        // First try to get spaces in the community
        $spaces_response = $this->make_request('spaces');
        
        if (is_wp_error($spaces_response)) {
            return $spaces_response;
        }
        
        $spaces = isset($spaces_response['records']) ? $spaces_response['records'] : [];
        
        if (empty($spaces)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('No spaces found in community');
            }
            return [];
        }
        
        // Filter spaces by category if specified
        if (!empty($filters['category'])) {
            $category = strtolower($filters['category']);
            $spaces = array_filter($spaces, function($space) use ($category) {
                return isset($space['name']) && 
                       (stripos(strtolower($space['name']), $category) !== false || 
                        (isset($space['slug']) && stripos(strtolower($space['slug']), $category) !== false));
            });
        }
        
        // Collect events from all spaces
        $all_events = [];
        
        foreach ($spaces as $space) {
            if (!isset($space['id'])) {
                continue;
            }
            
            $space_id = $space['id'];
            
            $params = [
                'per_page' => $limit * 2, // Request more to account for filtering
                'status' => !empty($filters['status']) ? $filters['status'] : 'upcoming',
            ];
            
            $response = $this->make_request('events', $params);
            
            if (is_wp_error($response)) {
                continue; // Skip this space and try the next one
            }
            
            $events = isset($response['records']) ? $response['records'] : [];
            
            foreach ($events as $event) {
                // Add space information to the event
                if (isset($space['name'])) {
                    $event['space_name'] = $space['name'];
                }
                if (isset($space['id'])) {
                    $event['space_id'] = $space['id'];
                }
                if (isset($space['image_url'])) {
                    $event['space_image_url'] = $space['image_url'];
                }
                
                $all_events[] = $this->format_event($event);
            }
        }
        
        // Sort events by start time
        usort($all_events, function($a, $b) {
            if (empty($a['start_time']) || empty($b['start_time'])) {
                return 0;
            }
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        // Limit results if needed
        if ($limit > 0 && count($all_events) > $limit) {
            $all_events = array_slice($all_events, 0, $limit);
        }
        
        return $all_events;
    }
    
    /**
     * Get events from Circle.so community events endpoint
     * 
     * @param int $limit The maximum number of events to retrieve.
     * @param array $filters Optional filters for events (category, date range, etc.)
     * 
     * @return array|WP_Error The events or error.
     */
    private function get_community_events(int $limit = 10, array $filters = []) {
        $params = [
            'per_page' => $limit * 2, // Request more to account for filtering
            'status' => !empty($filters['status']) ? $filters['status'] : 'upcoming',
        ];
        
        // Add category filter if specified
        if (!empty($filters['category'])) {
            $params['category'] = $filters['category'];
        }
        
        $response = $this->make_request('events', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $events = isset($response['records']) ? $response['records'] : [];
        
        return $this->format_events($events);
    }
    
    /**
     * Get events from Circle.so direct events endpoint
     * 
     * @param int $limit The maximum number of events to retrieve.
     * @param array $filters Optional filters for events (category, date range, etc.)
     * 
     * @return array|WP_Error The events or error.
     */
    private function get_direct_events(int $limit = 10, array $filters = []) {
        $params = [
            'per_page' => $limit * 2, // Request more to account for filtering
            'status' => !empty($filters['status']) ? $filters['status'] : 'upcoming',
        ];
        
        // Add category filter if specified
        if (!empty($filters['category'])) {
            $params['category'] = $filters['category'];
        }
        
        $response = $this->make_request('events', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $events = isset($response['records']) ? $response['records'] : [];
        
        return $this->format_events($events);
    }
}