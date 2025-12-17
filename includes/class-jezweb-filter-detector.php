<?php
/**
 * Filter Detector Class
 *
 * Detects active filters from various sources (UI checkboxes, URL params, JetSmartFilters).
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_Filter_Detector class.
 */
class Jezweb_Filter_Detector {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_footer', array( $this, 'output_filter_detection_script' ), 100 );
        add_filter( 'jezweb_search_result_filter_selectors', array( $this, 'get_default_selectors' ) );
    }

    /**
     * Output JavaScript for detecting filter changes.
     */
    public function output_filter_detection_script() {
        if ( ! $this->should_output_script() ) {
            return;
        }

        $selectors = $this->get_filter_selectors();
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';

            // Filter selectors configuration
            window.jezwebFilterSelectors = <?php echo wp_json_encode( $selectors ); ?>;

            // Store for detected filters
            window.jezwebDetectedFilters = {
                categories: [],
                tags: [],
                taxonomies: {}
            };

            /**
             * Detect filters from various sources
             */
            window.jezwebDetectFilters = function() {
                var filters = {
                    categories: [],
                    tags: [],
                    taxonomies: {}
                };

                // Detect from URL parameters
                var urlFilters = jezwebDetectFromURL();
                filters = jezwebMergeFilters(filters, urlFilters);

                // Detect from checkboxes and selects
                var uiFilters = jezwebDetectFromUI();
                filters = jezwebMergeFilters(filters, uiFilters);

                // Detect from JetSmartFilters
                var jsfFilters = jezwebDetectFromJSF();
                filters = jezwebMergeFilters(filters, jsfFilters);

                // Detect from current archive context (data attribute)
                var archiveFilters = jezwebDetectFromArchive();
                filters = jezwebMergeFilters(filters, archiveFilters);

                // Store globally
                window.jezwebDetectedFilters = filters;

                // Trigger custom event
                document.dispatchEvent(new CustomEvent('jezweb-filters-detected', {
                    detail: filters
                }));

                return filters;
            };

            /**
             * Detect filters from URL parameters
             */
            function jezwebDetectFromURL() {
                var filters = { categories: [], tags: [], taxonomies: {} };
                var params = new URLSearchParams(window.location.search);

                // Category parameters
                var catParams = ['product_cat', 'category', 'category_name', 'cat'];
                catParams.forEach(function(param) {
                    if (params.has(param)) {
                        var value = params.get(param);
                        var values = value.includes(',') ? value.split(',') : [value];
                        filters.categories = filters.categories.concat(values.map(function(v) { return v.trim(); }));
                    }
                });

                // Tag parameters
                var tagParams = ['product_tag', 'tag', 'post_tag'];
                tagParams.forEach(function(param) {
                    if (params.has(param)) {
                        var value = params.get(param);
                        var values = value.includes(',') ? value.split(',') : [value];
                        filters.tags = filters.tags.concat(values.map(function(v) { return v.trim(); }));
                    }
                });

                // JetSmartFilters and custom taxonomy parameters
                params.forEach(function(value, key) {
                    if (key.indexOf('jsf_') === 0 || key.indexOf('_tax_query') !== -1 || key.indexOf('tax-') === 0) {
                        var values = value.includes(',') ? value.split(',') : [value];
                        filters.taxonomies[key] = values.map(function(v) { return v.trim(); });
                    }
                });

                return filters;
            }

            /**
             * Detect filters from UI elements
             */
            function jezwebDetectFromUI() {
                var filters = { categories: [], tags: [], taxonomies: {} };
                var selectors = window.jezwebFilterSelectors;

                // Detect from checkboxes
                if (selectors.checkboxes) {
                    selectors.checkboxes.forEach(function(config) {
                        var checkboxes = document.querySelectorAll(config.selector + ':checked');
                        checkboxes.forEach(function(checkbox) {
                            var value = checkbox.value;
                            var taxonomy = checkbox.getAttribute('data-taxonomy') ||
                                          checkbox.name.replace('[]', '').replace('tax-', '');

                            if (config.type === 'category' || taxonomy.indexOf('cat') !== -1) {
                                filters.categories.push(value);
                            } else if (config.type === 'tag' || taxonomy.indexOf('tag') !== -1) {
                                filters.tags.push(value);
                            } else {
                                if (!filters.taxonomies['jsf_' + taxonomy]) {
                                    filters.taxonomies['jsf_' + taxonomy] = [];
                                }
                                filters.taxonomies['jsf_' + taxonomy].push(value);
                            }
                        });
                    });
                }

                // Detect from select elements
                if (selectors.selects) {
                    selectors.selects.forEach(function(config) {
                        var selects = document.querySelectorAll(config.selector);
                        selects.forEach(function(select) {
                            var selectedOptions = select.selectedOptions || [select.options[select.selectedIndex]];
                            Array.from(selectedOptions).forEach(function(option) {
                                if (option && option.value && option.value !== '' && option.value !== '-1' && option.value !== '0') {
                                    var taxonomy = select.getAttribute('data-taxonomy') ||
                                                  select.name.replace('[]', '').replace('tax-', '');

                                    if (config.type === 'category' || taxonomy.indexOf('cat') !== -1) {
                                        filters.categories.push(option.value);
                                    } else if (config.type === 'tag' || taxonomy.indexOf('tag') !== -1) {
                                        filters.tags.push(option.value);
                                    } else if (taxonomy) {
                                        if (!filters.taxonomies['jsf_' + taxonomy]) {
                                            filters.taxonomies['jsf_' + taxonomy] = [];
                                        }
                                        filters.taxonomies['jsf_' + taxonomy].push(option.value);
                                    }
                                }
                            });
                        });
                    });
                }

                // Detect from radio buttons
                if (selectors.radios) {
                    selectors.radios.forEach(function(config) {
                        var radios = document.querySelectorAll(config.selector + ':checked');
                        radios.forEach(function(radio) {
                            var value = radio.value;
                            if (value && value !== '' && value !== '-1' && value !== '0') {
                                var taxonomy = radio.getAttribute('data-taxonomy') ||
                                              radio.name.replace('[]', '').replace('tax-', '');

                                if (config.type === 'category' || taxonomy.indexOf('cat') !== -1) {
                                    filters.categories.push(value);
                                } else if (config.type === 'tag' || taxonomy.indexOf('tag') !== -1) {
                                    filters.tags.push(value);
                                } else if (taxonomy) {
                                    if (!filters.taxonomies['jsf_' + taxonomy]) {
                                        filters.taxonomies['jsf_' + taxonomy] = [];
                                    }
                                    filters.taxonomies['jsf_' + taxonomy].push(value);
                                }
                            }
                        });
                    });
                }

                // Detect active filter items (links with active class)
                if (selectors.activeItems) {
                    selectors.activeItems.forEach(function(config) {
                        var items = document.querySelectorAll(config.selector);
                        items.forEach(function(item) {
                            var value = item.getAttribute('data-value') ||
                                       item.getAttribute('data-term-id') ||
                                       item.getAttribute('data-term-slug');
                            var taxonomy = item.getAttribute('data-taxonomy');

                            if (value) {
                                if (config.type === 'category' || (taxonomy && taxonomy.indexOf('cat') !== -1)) {
                                    filters.categories.push(value);
                                } else if (config.type === 'tag' || (taxonomy && taxonomy.indexOf('tag') !== -1)) {
                                    filters.tags.push(value);
                                } else if (taxonomy) {
                                    if (!filters.taxonomies['jsf_' + taxonomy]) {
                                        filters.taxonomies['jsf_' + taxonomy] = [];
                                    }
                                    filters.taxonomies['jsf_' + taxonomy].push(value);
                                }
                            }
                        });
                    });
                }

                return filters;
            }

            /**
             * Detect filters from JetSmartFilters
             */
            function jezwebDetectFromJSF() {
                var filters = { categories: [], tags: [], taxonomies: {} };

                // Check for JetSmartFilters global object
                if (typeof window.JetSmartFilters !== 'undefined' && window.JetSmartFilters.filters) {
                    try {
                        var jsfFilters = window.JetSmartFilters.filters;
                        for (var filterId in jsfFilters) {
                            if (jsfFilters.hasOwnProperty(filterId)) {
                                var filter = jsfFilters[filterId];
                                if (filter.queryArgs && filter.queryArgs.tax_query) {
                                    filter.queryArgs.tax_query.forEach(function(taxQuery) {
                                        if (taxQuery.taxonomy && taxQuery.terms) {
                                            var terms = Array.isArray(taxQuery.terms) ? taxQuery.terms : [taxQuery.terms];

                                            if (taxQuery.taxonomy.indexOf('cat') !== -1 || taxQuery.taxonomy === 'category') {
                                                filters.categories = filters.categories.concat(terms);
                                            } else if (taxQuery.taxonomy.indexOf('tag') !== -1) {
                                                filters.tags = filters.tags.concat(terms);
                                            } else {
                                                if (!filters.taxonomies['jsf_' + taxQuery.taxonomy]) {
                                                    filters.taxonomies['jsf_' + taxQuery.taxonomy] = [];
                                                }
                                                filters.taxonomies['jsf_' + taxQuery.taxonomy] =
                                                    filters.taxonomies['jsf_' + taxQuery.taxonomy].concat(terms);
                                            }
                                        }
                                    });
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Jezweb Search Result: Error reading JetSmartFilters data', e);
                    }
                }

                // Check for JSF filter elements
                var jsfCheckboxes = document.querySelectorAll('.jet-checkboxes-list__input:checked, .jet-radio-list__input:checked');
                jsfCheckboxes.forEach(function(input) {
                    var value = input.value;
                    var filterWrap = input.closest('[data-content-id]');
                    if (filterWrap) {
                        var taxonomy = filterWrap.getAttribute('data-query-var');
                        if (taxonomy) {
                            if (taxonomy.indexOf('cat') !== -1) {
                                filters.categories.push(value);
                            } else if (taxonomy.indexOf('tag') !== -1) {
                                filters.tags.push(value);
                            } else {
                                if (!filters.taxonomies['jsf_' + taxonomy]) {
                                    filters.taxonomies['jsf_' + taxonomy] = [];
                                }
                                filters.taxonomies['jsf_' + taxonomy].push(value);
                            }
                        }
                    }
                });

                // Check for JSF active tags
                var jsfActiveTags = document.querySelectorAll('.jet-active-tag[data-value]');
                jsfActiveTags.forEach(function(tag) {
                    var value = tag.getAttribute('data-value');
                    var taxonomy = tag.getAttribute('data-query-var');

                    if (value && taxonomy) {
                        if (taxonomy.indexOf('cat') !== -1) {
                            filters.categories.push(value);
                        } else if (taxonomy.indexOf('tag') !== -1) {
                            filters.tags.push(value);
                        } else {
                            if (!filters.taxonomies['jsf_' + taxonomy]) {
                                filters.taxonomies['jsf_' + taxonomy] = [];
                            }
                            filters.taxonomies['jsf_' + taxonomy].push(value);
                        }
                    }
                });

                return filters;
            }

            /**
             * Detect from archive context (body classes or data attributes)
             */
            function jezwebDetectFromArchive() {
                var filters = { categories: [], tags: [], taxonomies: {} };
                var body = document.body;

                // Check body classes for category/tag
                var bodyClasses = body.className.split(' ');
                bodyClasses.forEach(function(cls) {
                    // Product category archive
                    if (cls.indexOf('term-') === 0) {
                        var match = cls.match(/^term-(\d+)$/);
                        if (match) {
                            // We have a term ID, check context
                            if (body.classList.contains('tax-product_cat')) {
                                filters.categories.push(match[1]);
                            } else if (body.classList.contains('tax-product_tag')) {
                                filters.tags.push(match[1]);
                            }
                        }
                    }

                    // Slug-based detection
                    if (cls.indexOf('product_cat-') === 0) {
                        var slug = cls.replace('product_cat-', '');
                        filters.categories.push(slug);
                    }
                    if (cls.indexOf('product_tag-') === 0) {
                        var slug = cls.replace('product_tag-', '');
                        filters.tags.push(slug);
                    }
                });

                // Check for data attribute on archive wrapper
                var archiveWrapper = document.querySelector('[data-jezweb-current-term]');
                if (archiveWrapper) {
                    var termId = archiveWrapper.getAttribute('data-jezweb-term-id');
                    var taxonomy = archiveWrapper.getAttribute('data-jezweb-taxonomy');

                    if (termId && taxonomy) {
                        if (taxonomy.indexOf('cat') !== -1) {
                            filters.categories.push(termId);
                        } else if (taxonomy.indexOf('tag') !== -1) {
                            filters.tags.push(termId);
                        } else {
                            if (!filters.taxonomies['jsf_' + taxonomy]) {
                                filters.taxonomies['jsf_' + taxonomy] = [];
                            }
                            filters.taxonomies['jsf_' + taxonomy].push(termId);
                        }
                    }
                }

                return filters;
            }

            /**
             * Merge two filter objects
             */
            function jezwebMergeFilters(filters1, filters2) {
                return {
                    categories: jezwebUniqueArray(filters1.categories.concat(filters2.categories)),
                    tags: jezwebUniqueArray(filters1.tags.concat(filters2.tags)),
                    taxonomies: jezwebMergeTaxonomies(filters1.taxonomies, filters2.taxonomies)
                };
            }

            /**
             * Get unique array values
             */
            function jezwebUniqueArray(arr) {
                return arr.filter(function(value, index, self) {
                    return value && value !== '' && self.indexOf(value) === index;
                });
            }

            /**
             * Merge taxonomy objects
             */
            function jezwebMergeTaxonomies(tax1, tax2) {
                var merged = Object.assign({}, tax1);
                for (var key in tax2) {
                    if (tax2.hasOwnProperty(key)) {
                        if (merged[key]) {
                            merged[key] = jezwebUniqueArray(merged[key].concat(tax2[key]));
                        } else {
                            merged[key] = tax2[key];
                        }
                    }
                }
                return merged;
            }

            // Initial detection on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', window.jezwebDetectFilters);
            } else {
                window.jezwebDetectFilters();
            }

            // Re-detect on AJAX complete
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ajaxComplete(function() {
                    setTimeout(window.jezwebDetectFilters, 100);
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Check if script should be output.
     *
     * @return bool
     */
    private function should_output_script() {
        // Don't output in admin.
        if ( is_admin() ) {
            return false;
        }

        // Check settings.
        $settings = get_option( 'jezweb_search_result_settings', array() );
        $enabled  = isset( $settings['enabled'] ) ? $settings['enabled'] : true;

        return $enabled;
    }

    /**
     * Get filter selectors.
     *
     * @return array
     */
    private function get_filter_selectors() {
        $selectors = array(
            'checkboxes' => array(),
            'selects'    => array(),
            'radios'     => array(),
            'activeItems' => array(),
        );

        return apply_filters( 'jezweb_search_result_filter_selectors', $selectors );
    }

    /**
     * Get default selectors for common filter plugins.
     *
     * @param array $selectors Existing selectors.
     * @return array
     */
    public function get_default_selectors( $selectors ) {
        // WooCommerce default widgets.
        $selectors['checkboxes'][] = array(
            'selector' => '.woocommerce-widget-layered-nav-list input[type="checkbox"]',
            'type'     => 'auto',
        );

        // JetSmartFilters checkboxes.
        $selectors['checkboxes'][] = array(
            'selector' => '.jet-checkboxes-list__input',
            'type'     => 'auto',
        );

        // JetSmartFilters radios.
        $selectors['radios'][] = array(
            'selector' => '.jet-radio-list__input',
            'type'     => 'auto',
        );

        // YITH AJAX filters.
        $selectors['checkboxes'][] = array(
            'selector' => '.yith-wcan-filter input[type="checkbox"]',
            'type'     => 'auto',
        );

        // FacetWP.
        $selectors['checkboxes'][] = array(
            'selector' => '.facetwp-checkbox.checked input',
            'type'     => 'auto',
        );

        // WooCommerce category dropdown.
        $selectors['selects'][] = array(
            'selector' => '.dropdown_product_cat',
            'type'     => 'category',
        );

        // JetSmartFilters select.
        $selectors['selects'][] = array(
            'selector' => '.jet-select__control',
            'type'     => 'auto',
        );

        // Active filter items.
        $selectors['activeItems'][] = array(
            'selector' => '.jet-active-tag:not(.jet-active-tag--all)',
            'type'     => 'auto',
        );

        $selectors['activeItems'][] = array(
            'selector' => '.woocommerce-widget-layered-nav-list__item--chosen a',
            'type'     => 'auto',
        );

        // Elementor Pro filters (if available).
        $selectors['checkboxes'][] = array(
            'selector' => '.elementor-filter-checkbox:checked',
            'type'     => 'auto',
        );

        return $selectors;
    }
}
