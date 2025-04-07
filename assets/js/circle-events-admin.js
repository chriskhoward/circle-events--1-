/**
 * Circle.so Events Admin Scripts
 */
(function($) {
    'use strict';

    /**
     * Circle Events Admin object
     */
    const CircleEventsAdmin = {
        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.initSettings();
            this.initTestButton();
        },

        /**
         * Initialize settings page functionality
         */
        initSettings: function() {
            // Show/hide API token (toggle visibility)
            $('#circle_api_token_toggle').on('click', function(e) {
                e.preventDefault();
                
                const $input = $('#circle_api_token');
                const inputType = $input.attr('type');
                
                if (inputType === 'password') {
                    $input.attr('type', 'text');
                    $(this).text('Hide');
                } else {
                    $input.attr('type', 'password');
                    $(this).text('Show');
                }
            });
            
            // Copy shortcode to clipboard
            $('.circle-events-copy-shortcode').on('click', function(e) {
                e.preventDefault();
                
                const shortcode = $(this).data('shortcode');
                const tempInput = $('<input>');
                
                $('body').append(tempInput);
                tempInput.val(shortcode).select();
                document.execCommand('copy');
                tempInput.remove();
                
                const $button = $(this);
                const originalText = $button.text();
                
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            });
        },

        /**
         * Initialize API test button functionality
         */
        initTestButton: function() {
            $('#circle_test_connection_ajax').on('click', function(e) {
                e.preventDefault();
                
                const $resultContainer = $('#circle_test_result');
                const apiToken = $('#circle_api_token').val();
                const communityId = $('#circle_community_id').val();
                
                if (!apiToken || !communityId) {
                    $resultContainer.html(
                        '<div class="circle-events-error">' +
                        'API token and community ID are required.' +
                        '</div>'
                    );
                    return;
                }
                
                // Show loading indicator
                $resultContainer.html(
                    '<div class="circle-events-loading">' +
                    'Testing connection...' +
                    '</div>'
                );
                
                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'circle_events_test_connection',
                        api_token: apiToken,
                        community_id: communityId,
                        nonce: circle_events_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $resultContainer.html(
                                '<div class="circle-events-success">' +
                                'Connection successful! Your API credentials are working.' +
                                '</div>'
                            );
                        } else {
                            $resultContainer.html(
                                '<div class="circle-events-error">' +
                                'Connection failed: ' + response.data.message +
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $resultContainer.html(
                            '<div class="circle-events-error">' +
                            'Error connecting to server. Please try again.' +
                            '</div>'
                        );
                    }
                });
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CircleEventsAdmin.init();
    });

})(jQuery);