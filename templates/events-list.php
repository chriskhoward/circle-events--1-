<?php
/**
 * Template for displaying Circle.so events in a list
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
<?php if (!empty($events)) : ?>
    <div class="circle-events-list">
        <?php foreach ($events as $event) : ?>
            <div class="circle-events-item">
                <h3 class="circle-events-title">
                    <?php if (!empty($event['url'])) : ?>
                        <a href="<?php echo esc_url($event['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($event['title']); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html($event['title']); ?>
                    <?php endif; ?>
                </h3>
                
                <?php if ((!empty($display_options['show_date']) || !empty($display_options['show_time'])) && !empty($event['start_time'])) : ?>
                    <div class="circle-events-time">
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
                        
                        // Show end time if available
                        if (!empty($event['end_time']) && !empty($display_options['show_time'])) {
                            try {
                                // Assume end_time is UTC or includes timezone info
                                $end_dt = new DateTime($event['end_time']);
                                $end_dt->setTimezone($cst_tz); // Use the same timezone object
                                $end_timestamp = $end_dt->getTimestamp();
                            } catch (Exception $e) {
                                // Fallback if date parsing fails
                                $end_timestamp = strtotime($event['end_time']);
                                error_log('Circle Events: Failed to parse end_time: ' . $e->getMessage());
                            }
                            echo ' - ' . esc_html(date_i18n(get_option('time_format'), $end_timestamp));
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($display_options['show_location']) && !empty($event['location'])) : ?>
                    <div class="circle-events-location">
                        <?php if (!empty($event['is_online'])) : ?>
                            <span class="circle-events-online"><?php esc_html_e('Online', 'circle-events'); ?>:</span>
                        <?php endif; ?>
                        <?php echo esc_html($event['location']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($display_options['show_description']) && !empty($event['description'])) : ?>
                    <div class="circle-events-description">
                        <?php 
                        $description = wp_kses_post($event['description']);
                        // Limit description length
                        $max_length = 200;
                        if (strlen(strip_tags($description)) > $max_length) {
                            $description = wp_trim_words($description, 30, '... ');
                            if (!empty($event['url'])) {
                                $description .= ' <a href="' . esc_url($event['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Read more', 'circle-events') . '</a>';
                            }
                        }
                        echo $description;
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (isset($total_pages) && $total_pages > 1) : ?>
        <div class="circle-events-pagination">
            <?php
            $current_page = isset($_GET['event_page']) ? max(1, intval($_GET['event_page'])) : 1;
            
            // Previous button
            if ($current_page > 1) : ?>
                <a href="<?php echo esc_url(add_query_arg('event_page', $current_page - 1)); ?>" class="circle-events-pagination-button">
                    &larr;
                </a>
            <?php else : ?>
                <span class="circle-events-pagination-button disabled">&larr;</span>
            <?php endif;

            // Page numbers
            for ($i = 1; $i <= $total_pages; $i++) :
                if ($i === $current_page) : ?>
                    <span class="circle-events-pagination-button current"><?php echo esc_html($i); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url(add_query_arg('event_page', $i)); ?>" class="circle-events-pagination-button">
                        <?php echo esc_html($i); ?>
                    </a>
                <?php endif;
            endfor;

            // Next button
            if ($current_page < $total_pages) : ?>
                <a href="<?php echo esc_url(add_query_arg('event_page', $current_page + 1)); ?>" class="circle-events-pagination-button">
                    &rarr;
                </a>
            <?php else : ?>
                <span class="circle-events-pagination-button disabled">&rarr;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="circle-events-empty">
        <?php esc_html_e('No upcoming events found.', 'circle-events'); ?>
    </div>
<?php endif; ?>