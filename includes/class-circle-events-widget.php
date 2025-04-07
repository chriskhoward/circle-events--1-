<?php
/**
 * Circle.so Events Widget
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Widget
 */
class Circle_Events_Widget extends WP_Widget {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'circle_events_widget',
            __('Circle.so Events', 'circle-events'),
            [
                'description' => __('Display upcoming events from your Circle.so community', 'circle-events'),
                'classname' => 'circle-events-widget',
            ]
        );
    }

    /**
     * Widget frontend display
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget($args, $instance): void {
        $title = !empty($instance['title']) ? $instance['title'] : __('Upcoming Events', 'circle-events');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 5;
        
        // Get display options from settings
        $options = get_option('circle_events_settings');
        $display_options = $options['display_options'] ?? [
            'show_date' => true,
            'show_time' => true,
            'show_description' => true,
            'show_location' => true,
        ];
        
        // Override with widget-specific options if set
        if (isset($instance['show_date'])) {
            $display_options['show_date'] = (bool) $instance['show_date'];
        }
        
        if (isset($instance['show_time'])) {
            $display_options['show_time'] = (bool) $instance['show_time'];
        }
        
        if (isset($instance['show_description'])) {
            $display_options['show_description'] = (bool) $instance['show_description'];
        }
        
        if (isset($instance['show_location'])) {
            $display_options['show_location'] = (bool) $instance['show_location'];
        }
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        // Get events data
        $circle_events = Circle_Events::instance();
        $events = $circle_events->get_events_data();
        
        if (is_wp_error($events)) {
            if (current_user_can('manage_options')) {
                echo '<p class="circle-events-error">' . esc_html($events->get_error_message()) . '</p>';
            } else {
                echo '<p class="circle-events-error">' . esc_html__('Unable to load events at this time.', 'circle-events') . '</p>';
            }
            echo $args['after_widget'];
            return;
        }
        
        // Limit the number of events
        $events = array_slice($events, 0, $limit);
        
        // If no events
        if (empty($events)) {
            echo '<p class="circle-events-empty">' . esc_html__('No upcoming events found.', 'circle-events') . '</p>';
            echo $args['after_widget'];
            return;
        }
        
        // Add a container with refresh capability
        echo '<div class="circle-events-container" data-limit="' . esc_attr((string) $limit) . '">';
        
        // Display events
        include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-widget.php';
        
        // Add refresh button if enabled
        if (!empty($instance['show_refresh'])) {
            echo '<p class="circle-events-refresh"><a href="#" class="circle-events-refresh-button">' . esc_html__('Refresh Events', 'circle-events') . '</a></p>';
        }
        
        echo '</div>'; // .circle-events-container
        
        echo $args['after_widget'];
    }

    /**
     * Widget backend form
     *
     * @param array $instance Previously saved values from database.
     * @return void
     */
    public function form($instance): void {
        $title = !empty($instance['title']) ? $instance['title'] : __('Upcoming Events', 'circle-events');
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 5;
        $show_date = isset($instance['show_date']) ? (bool) $instance['show_date'] : true;
        $show_time = isset($instance['show_time']) ? (bool) $instance['show_time'] : true;
        $show_description = isset($instance['show_description']) ? (bool) $instance['show_description'] : true;
        $show_location = isset($instance['show_location']) ? (bool) $instance['show_location'] : true;
        $show_refresh = isset($instance['show_refresh']) ? (bool) $instance['show_refresh'] : false;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'circle-events'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">
                <?php esc_html_e('Number of events to show:', 'circle-events'); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" 
                step="1" min="1" max="20" value="<?php echo esc_attr((string) $limit); ?>">
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_date); ?> 
                id="<?php echo esc_attr($this->get_field_id('show_date')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_date')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_date')); ?>">
                <?php esc_html_e('Show event date', 'circle-events'); ?>
            </label>
            <br>
            
            <input class="checkbox" type="checkbox" <?php checked($show_time); ?> 
                id="<?php echo esc_attr($this->get_field_id('show_time')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_time')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_time')); ?>">
                <?php esc_html_e('Show event time', 'circle-events'); ?>
            </label>
            <br>
            
            <input class="checkbox" type="checkbox" <?php checked($show_description); ?> 
                id="<?php echo esc_attr($this->get_field_id('show_description')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_description')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_description')); ?>">
                <?php esc_html_e('Show event description', 'circle-events'); ?>
            </label>
            <br>
            
            <input class="checkbox" type="checkbox" <?php checked($show_location); ?> 
                id="<?php echo esc_attr($this->get_field_id('show_location')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_location')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_location')); ?>">
                <?php esc_html_e('Show event location', 'circle-events'); ?>
            </label>
            <br>
            
            <input class="checkbox" type="checkbox" <?php checked($show_refresh); ?> 
                id="<?php echo esc_attr($this->get_field_id('show_refresh')); ?>" 
                name="<?php echo esc_attr($this->get_field_name('show_refresh')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_refresh')); ?>">
                <?php esc_html_e('Show refresh button', 'circle-events'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Widget update method
     *
     * @param array $new_instance New settings for this instance.
     * @param array $old_instance Old settings for this instance.
     * @return array Settings to save.
     */
    public function update($new_instance, $old_instance): array {
        $instance = [];
        
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = !empty($new_instance['limit']) ? absint($new_instance['limit']) : 5;
        $instance['show_date'] = isset($new_instance['show_date']) ? (bool) $new_instance['show_date'] : false;
        $instance['show_time'] = isset($new_instance['show_time']) ? (bool) $new_instance['show_time'] : false;
        $instance['show_description'] = isset($new_instance['show_description']) ? (bool) $new_instance['show_description'] : false;
        $instance['show_location'] = isset($new_instance['show_location']) ? (bool) $new_instance['show_location'] : false;
        $instance['show_refresh'] = isset($new_instance['show_refresh']) ? (bool) $new_instance['show_refresh'] : false;
        
        return $instance;
    }
}