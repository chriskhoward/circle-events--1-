<?php
/**
 * Template for displaying Circle.so events in a widget
 *
 * @package Circle_Events
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Variables expected to be available:
// $events - Array of events to display
// $display_options - Array of display options
?>
<ul class="circle-events-widget-list">
    <?php foreach ($events as $event) : ?>
        <li class="circle-events-widget-item">
            <div class="circle-events-widget-title">
                <?php if (!empty($event['url'])) : ?>
                    <a href="<?php echo esc_url($event['url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html($event['title']); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html($event['title']); ?>
                <?php endif; ?>
            </div>
            
            <?php if ((!empty($display_options['show_date']) || !empty($display_options['show_time'])) && !empty($event['start_time'])) : ?>
                <div class="circle-events-widget-time">
                    <?php 
                    try {
                        // Assume start_time is UTC or includes timezone info
                        $start_dt = new DateTime($event['start_time']); 
                        // Set the target timezone
                        $cst_tz = new DateTimeZone('America/Chicago');
                        $start_dt->setTimezone($cst_tz);
                        $start_timestamp = $start_dt->getTimestamp();
                    } catch (Exception $e) {
                        // Fallback if date parsing fails
                        $start_timestamp = strtotime($event['start_time']);
                        error_log('Circle Events: Failed to parse start_time: ' . $e->getMessage());
                    }

                    $format = '';
                    
                    if (!empty($display_options['show_date'])) {
                        $format .= get_option('date_format');
                    }
                    
                    if (!empty($display_options['show_date']) && !empty($display_options['show_time'])) {
                        $format .= ' ';
                    }
                    
                    if (!empty($display_options['show_time'])) {
                        $format .= get_option('time_format');
                    }
                    
                    echo esc_html(date_i18n($format, $start_timestamp));
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($display_options['show_location']) && !empty($event['location'])) : ?>
                <div class="circle-events-widget-location">
                    <?php echo esc_html($event['location']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($display_options['show_description']) && !empty($event['description'])) : ?>
                <div class="circle-events-widget-description">
                    <?php 
                    // Limit description length for widgets
                    echo wp_trim_words(wp_kses_post($event['description']), 10, '...');
                    ?>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>