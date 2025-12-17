/**
 * Jezweb Search Result - Main JavaScript
 *
 * Handles filter detection, search form enhancement, and AJAX communication.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

(function($, window, document) {
    'use strict';

    // Bail if jQuery is not available
    if (typeof $ === 'undefined') {
        console.warn('Jezweb Search Result: jQuery is required.');
        return;
    }

    /**
     * Main Jezweb Search Result object
     */
    var JezwebSearch = {
        /**
         * Plugin settings
         */
        settings: {},

        /**
         * Active filters storage
         */
        activeFilters: {
            categories: [],
            tags: [],
            taxonomies: {}
        },

        /**
         * Debounce timers
         */
        timers: {
            sync: null,
            detect: null
        },

        /**
         * Initialize the plugin
         */
        init: function() {
            // Get settings from localized data
            if (typeof jezwebSearchResult !== 'undefined') {
                this.settings = jezwebSearchResult.settings || {};
                this.activeFilters = jezwebSearchResult.activeFilters || this.activeFilters;
            }

            // Bind events
            this.bindEvents();

            // Initial filter detection
            this.detectFilters();

            // Enhance all search forms
            this.enhanceSearchForms();

            // Expose globally
            window.JezwebSearch = this;
            window.jezwebDetectedFilters = this.activeFilters;

            // Debug log
            if (this.isDebug()) {
                console.log('Jezweb Search Result initialized', this.settings);
            }
        },

        /**
         * Check if debug mode is enabled
         */
        isDebug: function() {
            return typeof jezwebSearchResult !== 'undefined' && jezwebSearchResult.debug;
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Listen for filter changes
            $(document).on('change', this.getFilterSelectors(), function() {
                self.onFilterChange();
            });

            // Listen for form submissions
            $(document).on('submit', 'form[role="search"], .search-form, .woocommerce-product-search, .jet-ajax-search__form, .elementor-search-form form', function(e) {
                self.onSearchSubmit($(this));
            });

            // Listen for AJAX complete
            $(document).ajaxComplete(function(event, xhr, settings) {
                self.onAjaxComplete(settings);
            });

            // Listen for custom events
            $(document).on('jezweb-filters-detected', function(e) {
                self.activeFilters = e.detail || self.activeFilters;
                window.jezwebDetectedFilters = self.activeFilters;
            });

            // Listen for JetSmartFilters events
            $(document).on('jet-smart-filters/inited jet-filter-data-updated', function() {
                self.onFilterChange();
            });

            // Listen for WooCommerce events
            $(document.body).on('updated_wc_div', function() {
                self.onFilterChange();
            });

            // Re-enhance after DOM changes
            this.observeDOMChanges();
        },

        /**
         * Get CSS selectors for filter elements
         */
        getFilterSelectors: function() {
            return [
                // Checkboxes
                '.woocommerce-widget-layered-nav-list input[type="checkbox"]',
                '.jet-checkboxes-list__input',
                '.yith-wcan-filter input[type="checkbox"]',
                '.facetwp-checkbox input',
                '.elementor-filter-checkbox',

                // Radio buttons
                '.jet-radio-list__input',
                '.yith-wcan-filter input[type="radio"]',

                // Select elements
                '.dropdown_product_cat',
                '.jet-select__control',
                '.woocommerce-ordering select',

                // Generic taxonomy filters
                'input[name*="product_cat"]',
                'input[name*="product_tag"]',
                'select[name*="product_cat"]',
                'select[name*="product_tag"]'
            ].join(', ');
        },

        /**
         * Handle filter change events
         */
        onFilterChange: function() {
            var self = this;

            // Debounce
            clearTimeout(this.timers.detect);
            this.timers.detect = setTimeout(function() {
                self.detectFilters();
                self.syncFiltersToServer();
            }, 150);
        },

        /**
         * Handle search form submission
         */
        onSearchSubmit: function($form) {
            // Detect current filters
            this.detectFilters();

            // Update form hidden fields
            this.updateFormFields($form);

            if (this.isDebug()) {
                console.log('Search submitted with filters:', this.activeFilters);
            }
        },

        /**
         * Handle AJAX complete
         */
        onAjaxComplete: function(settings) {
            // Re-detect filters after AJAX updates
            if (settings.url && (
                settings.url.indexOf('jet-smart-filters') !== -1 ||
                settings.url.indexOf('wc-ajax') !== -1 ||
                settings.url.indexOf('jet-search') !== -1 ||
                settings.url.indexOf('elementor') !== -1
            )) {
                this.onFilterChange();
                this.enhanceSearchForms();
            }
        },

        /**
         * Detect active filters from various sources
         */
        detectFilters: function() {
            var filters = {
                categories: [],
                tags: [],
                taxonomies: {}
            };

            // Detect from URL
            var urlFilters = this.detectFromURL();
            filters = this.mergeFilters(filters, urlFilters);

            // Detect from DOM elements
            var domFilters = this.detectFromDOM();
            filters = this.mergeFilters(filters, domFilters);

            // Detect from archive context
            var archiveFilters = this.detectFromArchive();
            filters = this.mergeFilters(filters, archiveFilters);

            // Use global detector if available
            if (typeof window.jezwebDetectFilters === 'function') {
                var detectorFilters = window.jezwebDetectFilters();
                filters = this.mergeFilters(filters, detectorFilters);
            }

            // Clean up
            filters.categories = this.uniqueArray(filters.categories);
            filters.tags = this.uniqueArray(filters.tags);

            // Store
            this.activeFilters = filters;
            window.jezwebDetectedFilters = filters;

            // Trigger event
            $(document).trigger('jezweb-search-filters-updated', [filters]);

            if (this.isDebug()) {
                console.log('Filters detected:', filters);
            }

            return filters;
        },

        /**
         * Detect filters from URL parameters
         */
        detectFromURL: function() {
            var filters = { categories: [], tags: [], taxonomies: {} };
            var params = new URLSearchParams(window.location.search);

            // Category parameters
            var catParams = ['product_cat', 'category', 'category_name', 'cat'];
            catParams.forEach(function(param) {
                if (params.has(param)) {
                    var value = params.get(param);
                    var values = value.indexOf(',') !== -1 ? value.split(',') : [value];
                    filters.categories = filters.categories.concat(values.map(function(v) {
                        return v.trim();
                    }));
                }
            });

            // Tag parameters
            var tagParams = ['product_tag', 'tag', 'post_tag'];
            tagParams.forEach(function(param) {
                if (params.has(param)) {
                    var value = params.get(param);
                    var values = value.indexOf(',') !== -1 ? value.split(',') : [value];
                    filters.tags = filters.tags.concat(values.map(function(v) {
                        return v.trim();
                    }));
                }
            });

            // JetSmartFilters and other taxonomy parameters
            var prefix = this.settings.filterParamPrefix || 'jsf';
            params.forEach(function(value, key) {
                if (key.indexOf(prefix + '_') === 0 ||
                    key.indexOf('_tax_query') !== -1 ||
                    key.indexOf('tax-') === 0 ||
                    key.indexOf('filter_') === 0) {
                    var values = value.indexOf(',') !== -1 ? value.split(',') : [value];
                    filters.taxonomies[key] = values.map(function(v) {
                        return v.trim();
                    });
                }
            });

            return filters;
        },

        /**
         * Detect filters from DOM elements
         */
        detectFromDOM: function() {
            var filters = { categories: [], tags: [], taxonomies: {} };
            var self = this;

            // Checked checkboxes
            $('input[type="checkbox"]:checked').each(function() {
                var $input = $(this);
                var value = $input.val();

                if (!value || value === '' || value === '0') return;

                var taxonomy = self.getTaxonomyFromElement($input);

                if (taxonomy.indexOf('cat') !== -1 || taxonomy === 'category') {
                    filters.categories.push(value);
                } else if (taxonomy.indexOf('tag') !== -1) {
                    filters.tags.push(value);
                } else if (taxonomy) {
                    var key = 'jsf_' + taxonomy;
                    if (!filters.taxonomies[key]) {
                        filters.taxonomies[key] = [];
                    }
                    filters.taxonomies[key].push(value);
                }
            });

            // Selected radio buttons
            $('input[type="radio"]:checked').each(function() {
                var $input = $(this);
                var value = $input.val();

                if (!value || value === '' || value === '0' || value === '-1') return;

                var taxonomy = self.getTaxonomyFromElement($input);

                if (taxonomy.indexOf('cat') !== -1) {
                    filters.categories.push(value);
                } else if (taxonomy.indexOf('tag') !== -1) {
                    filters.tags.push(value);
                } else if (taxonomy) {
                    var key = 'jsf_' + taxonomy;
                    if (!filters.taxonomies[key]) {
                        filters.taxonomies[key] = [];
                    }
                    filters.taxonomies[key].push(value);
                }
            });

            // Select elements
            $('select').each(function() {
                var $select = $(this);
                var value = $select.val();

                if (!value || value === '' || value === '0' || value === '-1') return;

                var taxonomy = self.getTaxonomyFromElement($select);

                if (!taxonomy) return;

                var values = Array.isArray(value) ? value : [value];
                values = values.filter(function(v) {
                    return v && v !== '' && v !== '0' && v !== '-1';
                });

                if (values.length === 0) return;

                if (taxonomy.indexOf('cat') !== -1) {
                    filters.categories = filters.categories.concat(values);
                } else if (taxonomy.indexOf('tag') !== -1) {
                    filters.tags = filters.tags.concat(values);
                } else {
                    var key = 'jsf_' + taxonomy;
                    if (!filters.taxonomies[key]) {
                        filters.taxonomies[key] = [];
                    }
                    filters.taxonomies[key] = filters.taxonomies[key].concat(values);
                }
            });

            // Active filter tags/pills
            $('.jet-active-tag:not(.jet-active-tag--all), .woocommerce-widget-layered-nav-list__item--chosen').each(function() {
                var $item = $(this);
                var value = $item.attr('data-value') ||
                           $item.attr('data-term-id') ||
                           $item.find('a').attr('data-jezweb-term-slug');
                var taxonomy = $item.attr('data-query-var') ||
                              $item.attr('data-taxonomy') ||
                              $item.find('a').attr('data-jezweb-taxonomy');

                if (!value || !taxonomy) return;

                if (taxonomy.indexOf('cat') !== -1) {
                    filters.categories.push(value);
                } else if (taxonomy.indexOf('tag') !== -1) {
                    filters.tags.push(value);
                } else {
                    var key = 'jsf_' + taxonomy;
                    if (!filters.taxonomies[key]) {
                        filters.taxonomies[key] = [];
                    }
                    filters.taxonomies[key].push(value);
                }
            });

            return filters;
        },

        /**
         * Get taxonomy name from form element
         */
        getTaxonomyFromElement: function($el) {
            // Check data attributes
            var taxonomy = $el.attr('data-taxonomy') ||
                          $el.attr('data-query-var');

            if (taxonomy) return taxonomy;

            // Check parent wrapper
            var $wrapper = $el.closest('[data-content-id], [data-taxonomy], .jet-filter');
            if ($wrapper.length) {
                taxonomy = $wrapper.attr('data-query-var') ||
                          $wrapper.attr('data-taxonomy');
                if (taxonomy) return taxonomy;
            }

            // Check name attribute
            var name = $el.attr('name') || '';
            name = name.replace('[]', '').replace('tax-', '').replace('filter_', '');

            return name;
        },

        /**
         * Detect filters from archive context
         */
        detectFromArchive: function() {
            var filters = { categories: [], tags: [], taxonomies: {} };
            var $body = $('body');

            // Check body classes
            var bodyClasses = $body.attr('class').split(' ');
            bodyClasses.forEach(function(cls) {
                if (cls.indexOf('product_cat-') === 0) {
                    filters.categories.push(cls.replace('product_cat-', ''));
                }
                if (cls.indexOf('product_tag-') === 0) {
                    filters.tags.push(cls.replace('product_tag-', ''));
                }
            });

            // Check for data attribute marker
            var $marker = $('[data-jezweb-current-term]');
            if ($marker.length) {
                var termId = $marker.attr('data-jezweb-term-id');
                var termSlug = $marker.attr('data-jezweb-term-slug');
                var taxonomy = $marker.attr('data-jezweb-taxonomy');
                var value = termSlug || termId;

                if (value && taxonomy) {
                    if (taxonomy.indexOf('cat') !== -1) {
                        filters.categories.push(value);
                    } else if (taxonomy.indexOf('tag') !== -1) {
                        filters.tags.push(value);
                    } else {
                        filters.taxonomies['jsf_' + taxonomy] = [value];
                    }
                }
            }

            return filters;
        },

        /**
         * Merge two filter objects
         */
        mergeFilters: function(f1, f2) {
            return {
                categories: this.uniqueArray((f1.categories || []).concat(f2.categories || [])),
                tags: this.uniqueArray((f1.tags || []).concat(f2.tags || [])),
                taxonomies: $.extend({}, f1.taxonomies || {}, f2.taxonomies || {})
            };
        },

        /**
         * Get unique array values, removing empty strings
         */
        uniqueArray: function(arr) {
            return arr.filter(function(value, index, self) {
                return value && value !== '' && self.indexOf(value) === index;
            });
        },

        /**
         * Enhance all search forms on the page
         */
        enhanceSearchForms: function() {
            var self = this;

            var searchForms = $(
                'form[role="search"], ' +
                '.search-form, ' +
                '.woocommerce-product-search, ' +
                '.jet-ajax-search__form, ' +
                '.jet-ajax-search form, ' +
                '.elementor-search-form form, ' +
                '.elementor-widget-search-form form'
            );

            searchForms.each(function() {
                var $form = $(this);

                // Skip if already enhanced
                if ($form.data('jezweb-enhanced')) {
                    return;
                }

                $form.data('jezweb-enhanced', true);
                $form.addClass('jezweb-enhanced-search');

                // Add hidden fields
                if (!$form.find('input[name="jezweb_filters"]').length) {
                    $form.append('<input type="hidden" name="jezweb_filters" value="">');
                }

                // Bind submit handler
                $form.on('submit.jezwebSearch', function(e) {
                    self.updateFormFields($form);
                });
            });
        },

        /**
         * Update hidden fields in search form
         */
        updateFormFields: function($form) {
            var filters = this.activeFilters;

            // Update main filters field
            $form.find('input[name="jezweb_filters"]').val(JSON.stringify(filters));

            // Add/update category field
            if (filters.categories.length > 0) {
                var $catInput = $form.find('input[name="product_cat"]');
                if ($catInput.length === 0) {
                    $form.append('<input type="hidden" name="product_cat" value="">');
                    $catInput = $form.find('input[name="product_cat"]');
                }
                $catInput.val(filters.categories.join(','));
            }

            // Add/update tag field
            if (filters.tags.length > 0) {
                var $tagInput = $form.find('input[name="product_tag"]');
                if ($tagInput.length === 0) {
                    $form.append('<input type="hidden" name="product_tag" value="">');
                    $tagInput = $form.find('input[name="product_tag"]');
                }
                $tagInput.val(filters.tags.join(','));
            }

            // Add taxonomy fields
            for (var taxonomy in filters.taxonomies) {
                if (filters.taxonomies.hasOwnProperty(taxonomy)) {
                    var $taxInput = $form.find('input[name="' + taxonomy + '"]');
                    if ($taxInput.length === 0) {
                        $form.append('<input type="hidden" name="' + taxonomy + '" value="">');
                        $taxInput = $form.find('input[name="' + taxonomy + '"]');
                    }
                    $taxInput.val(filters.taxonomies[taxonomy].join(','));
                }
            }
        },

        /**
         * Sync filters to server via AJAX
         */
        syncFiltersToServer: function() {
            var self = this;

            if (typeof jezwebSearchResult === 'undefined') {
                return;
            }

            // Debounce
            clearTimeout(this.timers.sync);
            this.timers.sync = setTimeout(function() {
                $.ajax({
                    url: jezwebSearchResult.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'jezweb_set_filters',
                        nonce: jezwebSearchResult.nonce,
                        categories: self.activeFilters.categories,
                        tags: self.activeFilters.tags,
                        taxonomies: self.activeFilters.taxonomies
                    },
                    success: function(response) {
                        if (self.isDebug()) {
                            console.log('Filters synced to server:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        if (self.isDebug()) {
                            console.warn('Failed to sync filters:', error);
                        }
                    }
                });
            }, 300);
        },

        /**
         * Observe DOM changes to enhance dynamically added forms
         */
        observeDOMChanges: function() {
            var self = this;

            if (typeof MutationObserver === 'undefined') {
                return;
            }

            var observer = new MutationObserver(function(mutations) {
                var shouldEnhance = false;

                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                var $node = $(node);
                                if ($node.is('form') || $node.find('form').length > 0) {
                                    shouldEnhance = true;
                                }
                            }
                        });
                    }
                });

                if (shouldEnhance) {
                    self.enhanceSearchForms();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Get current active filters
         */
        getFilters: function() {
            return this.activeFilters;
        },

        /**
         * Set filters programmatically
         */
        setFilters: function(filters) {
            this.activeFilters = this.mergeFilters(this.activeFilters, filters);
            window.jezwebDetectedFilters = this.activeFilters;
            this.syncFiltersToServer();
            return this.activeFilters;
        },

        /**
         * Clear all filters
         */
        clearFilters: function() {
            this.activeFilters = {
                categories: [],
                tags: [],
                taxonomies: {}
            };
            window.jezwebDetectedFilters = this.activeFilters;
            this.syncFiltersToServer();
            $(document).trigger('jezweb-search-filters-cleared');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        JezwebSearch.init();
    });

    // Also expose the detectFilters function globally
    window.jezwebDetectFilters = function() {
        return JezwebSearch.detectFilters();
    };

})(jQuery, window, document);
