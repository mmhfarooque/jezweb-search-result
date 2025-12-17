/**
 * Jezweb Search Result - Admin JavaScript
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Settings Handler
     */
    var JezwebAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkDependencies();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Toggle dependent fields
            $('input[name$="[enabled]"]').on('change', function() {
                self.toggleDependentFields($(this).is(':checked'));
            });

            // Show confirmation for disabling
            $('form').on('submit', function(e) {
                var enabled = $('input[name$="[enabled]"]').is(':checked');
                if (!enabled) {
                    if (!confirm('Are you sure you want to disable Jezweb Search Result? Search results will no longer be filtered by active categories/tags.')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });

            // Select all / Deselect all for taxonomies
            this.addBulkSelectButtons();
        },

        /**
         * Toggle dependent fields based on enabled state
         */
        toggleDependentFields: function(enabled) {
            var $dependentRows = $('.form-table tr').not(':has(input[name$="[enabled]"])');

            if (enabled) {
                $dependentRows.find('input, select').prop('disabled', false);
                $dependentRows.css('opacity', '1');
            } else {
                $dependentRows.find('input, select').prop('disabled', true);
                $dependentRows.css('opacity', '0.5');
            }
        },

        /**
         * Check plugin dependencies and show notices
         */
        checkDependencies: function() {
            // Check each integration's plugin status
            $('.jezweb-plugin-inactive').each(function() {
                var $row = $(this).closest('tr');
                $row.find('input[type="checkbox"]').prop('disabled', true);
            });
        },

        /**
         * Add bulk select buttons for taxonomy checkboxes
         */
        addBulkSelectButtons: function() {
            var $taxonomyFieldset = $('input[name$="[enabled_taxonomies][]"]').first().closest('fieldset');

            if ($taxonomyFieldset.length) {
                var $buttons = $('<div class="jezweb-bulk-actions" style="margin-bottom: 10px;"></div>');
                $buttons.append('<button type="button" class="button button-small jezweb-select-all">Select All</button> ');
                $buttons.append('<button type="button" class="button button-small jezweb-deselect-all">Deselect All</button>');

                $taxonomyFieldset.prepend($buttons);

                // Bind button events
                $buttons.on('click', '.jezweb-select-all', function(e) {
                    e.preventDefault();
                    $taxonomyFieldset.find('input[type="checkbox"]').prop('checked', true);
                });

                $buttons.on('click', '.jezweb-deselect-all', function(e) {
                    e.preventDefault();
                    $taxonomyFieldset.find('input[type="checkbox"]').prop('checked', false);
                });
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        JezwebAdmin.init();
    });

})(jQuery);
