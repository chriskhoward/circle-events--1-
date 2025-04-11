/**
 * Circle.so Events Frontend Scripts
 */
(function($) {
    'use strict';

    /**
     * Circle Events main object
     */
    const CircleEvents = {
        /**
         * Initialize the events functionality
         */
        init: function() {
            // Initialize refresh buttons
            this.initRefreshButtons();
        },

        /**
         * Initialize the refresh buttons
         */
        initRefreshButtons: function() {
            $('.circle-events-refresh-button').on('click', function(e) {
                e.preventDefault();
                
                const container = $(this).closest('.circle-events-container');
                const limit = container.data('limit') || 5;
                const category = container.data('category') || '';
                const days = container.data('days') || 0;
                const pastEvents = container.data('past-events') || 0;
                const status = container.data('status') || 'upcoming';
                const order = container.data('order') || 'asc';
                
                CircleEvents.refreshEvents(container, {
                    limit: limit,
                    category: category,
                    days: days,
                    past_events: pastEvents,
                    status: status,
                    order: order
                });
            });
        },

        /**
         * Refresh events data via REST API
         * 
         * @param {jQuery} container The container element
         * @param {Object} options   The options for fetching events
         */
        refreshEvents: function(container, options) {
            // Add loading indicator
            container.addClass('circle-events-loading');
            
            // Prepare request data
            const requestData = {
                refresh: true
            };
            
            // Add options to request data
            if (options.limit) requestData.limit = options.limit;
            if (options.category) requestData.category = options.category;
            if (options.days) requestData.days = options.days;
            if (options.past_events) requestData.past_events = options.past_events;
            if (options.status) requestData.status = options.status;
            if (options.order) requestData.order = options.order;
            
            // Make REST API request
            $.ajax({
                url: circle_events_params.ajax_url,
                method: 'GET',
                data: requestData,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', circle_events_params.nonce);
                },
                success: function(response) {
                    container.removeClass('circle-events-loading');
                    
                    if (response.success) {
                        // Replace content with new events
                        if (container.hasClass('circle-events-widget-list')) {
                            CircleEvents.refreshWidgetEvents(container, response.events, options);
                        } else if (container.hasClass('circle-events-calendar')) {
                            CircleEvents.refreshCalendarEvents(container, response.events, options);
                        } else {
                            CircleEvents.refreshListEvents(container, response.events, options);
                        }
                    } else {
                        // Show error
                        container.html('<div class="circle-events-error">' + 
                            (response.message || 'Error refreshing events.') + '</div>');
                    }
                },
                error: function() {
                    container.removeClass('circle-events-loading');
                    container.html('<div class="circle-events-error">Error connecting to server.</div>');
                }
            });
        },

        /**
         * Refresh events in widget format
         * 
         * @param {jQuery} container The container element
         * @param {Array}  events    The events data
         * @param {Object} options   The display options
         */
        refreshWidgetEvents: function(container, events, options) {
            if (events.length === 0) {
                container.html('<p class="circle-events-empty">No upcoming events found.</p>');
                return;
            }
            
            // Request the widget template via admin-ajax.php
            $.ajax({
                url: circle_events_params.ajaxurl,
                method: 'POST',
                data: {
                    action: 'circle_events_get_widget_template',
                    events: events,
                    options: options,
                    nonce: circle_events_params.ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        container.html(response.data);
                    } else {
                        container.html('<div class="circle-events-error">' + 
                            (response.data && response.data.message ? response.data.message : 'Error refreshing events.') + 
                            '</div>');
                    }
                },
                error: function() {
                    container.html('<div class="circle-events-error">Error connecting to server.</div>');
                }
            });
        },

        /**
         * Refresh events in list format
         * 
         * @param {jQuery} container The container element
         * @param {Array}  events    The events data
         * @param {Object} options   The display options
         */
        refreshListEvents: function(container, events, options) {
            if (events.length === 0) {
                container.html('<p class="circle-events-empty">No upcoming events found.</p>');
                return;
            }
            
            // Request the list template via admin-ajax.php
            $.ajax({
                url: circle_events_params.ajaxurl,
                method: 'POST',
                data: {
                    action: 'circle_events_get_list_template',
                    events: events,
                    options: options,
                    nonce: circle_events_params.ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        container.html(response.data);
                    } else {
                        container.html('<div class="circle-events-error">' + 
                            (response.data && response.data.message ? response.data.message : 'Error refreshing events.') + 
                            '</div>');
                    }
                },
                error: function() {
                    container.html('<div class="circle-events-error">Error connecting to server.</div>');
                }
            });
        },

        /**
         * Refresh events in calendar format
         * 
         * @param {jQuery} container The container element
         * @param {Array}  events    The events data
         * @param {Object} options   The display options
         */
        refreshCalendarEvents: function(container, events, options) {
            if (events.length === 0) {
                container.html('<p class="circle-events-empty">No upcoming events found.</p>');
                return;
            }
            
            // Request the calendar template via admin-ajax.php
            $.ajax({
                url: circle_events_params.ajaxurl,
                method: 'POST',
                data: {
                    action: 'circle_events_get_calendar_template',
                    events: events,
                    options: options,
                    nonce: circle_events_params.ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        container.html(response.data);
                    } else {
                        container.html('<div class="circle-events-error">' + 
                            (response.data && response.data.message ? response.data.message : 'Error refreshing events.') + 
                            '</div>');
                    }
                },
                error: function() {
                    container.html('<div class="circle-events-error">Error connecting to server.</div>');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CircleEvents.init();
    });

})(jQuery);