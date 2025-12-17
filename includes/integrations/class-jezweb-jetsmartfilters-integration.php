<?php
/**
 * JetSmartFilters Integration Class
 *
 * Handles integration with Crocoblock JetSmartFilters plugin.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_JetSmartFilters_Integration class.
 */
class Jezweb_JetSmartFilters_Integration {

    /**
     * Active taxonomy filters from current request.
     *
     * @var array
     */
    private $current_tax_filters = array();

    /**
     * Search term from current AJAX request.
     *
     * @var string
     */
    private $current_search_term = '';

    /**
     * Constructor.
     */
    public function __construct() {
        // Check if JetSmartFilters is active.
        if ( ! $this->is_jsf_active() ) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if JetSmartFilters is active.
     *
     * @return bool
     */
    private function is_jsf_active() {
        return defined( 'JET_SMART_FILTERS_VERSION' ) || class_exists( 'Jet_Smart_Filters' );
    }

    /**
     * Check if integration is enabled.
     *
     * @return bool
     */
    private function is_enabled() {
        $settings = get_option( 'jezweb_search_result_settings', array() );
        return isset( $settings['enable_jetfilters'] ) ? $settings['enable_jetfilters'] : true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Hook into JetSmartFilters query - high priority to capture and modify.
        add_filter( 'jet-smart-filters/query/final-query', array( $this, 'modify_jsf_query' ), 5 );

        // Hook into filter state changes.
        add_action( 'jet-smart-filters/filter-instance/update', array( $this, 'on_filter_update' ) );

        // Add JavaScript for detecting JSF filter changes.
        add_action( 'wp_footer', array( $this, 'add_jsf_enhancements' ), 25 );

        // Store active filters for search integration.
        add_action( 'wp_ajax_jezweb_sync_jsf_filters', array( $this, 'sync_jsf_filters' ) );
        add_action( 'wp_ajax_nopriv_jezweb_sync_jsf_filters', array( $this, 'sync_jsf_filters' ) );

        // Add data attribute to archive wrappers.
        add_action( 'jet-smart-filters/render/provider-wrapper-attributes', array( $this, 'add_provider_data' ), 10, 2 );

        // Hook into pre_get_posts for JSF AJAX requests.
        add_action( 'pre_get_posts', array( $this, 'handle_jsf_search_query' ), 5 );

        // Intercept JetSmartFilters AJAX request early.
        add_action( 'wp_ajax_jet_smart_filters', array( $this, 'intercept_jsf_ajax' ), 1 );
        add_action( 'wp_ajax_nopriv_jet_smart_filters', array( $this, 'intercept_jsf_ajax' ), 1 );

        // Add posts_where filter to enforce search at SQL level.
        add_filter( 'posts_where', array( $this, 'filter_posts_where_for_search' ), 999, 2 );
    }

    /**
     * Intercept JSF AJAX request early to capture filter data.
     */
    public function intercept_jsf_ajax() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- JSF handles its own nonce.

        // Capture filter data from the AJAX request for use in query modification.
        if ( isset( $_POST['query'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $query_data = $_POST['query'];
            if ( is_string( $query_data ) ) {
                $query_data = json_decode( stripslashes( $query_data ), true );
            }

            if ( is_array( $query_data ) ) {
                if ( isset( $query_data['tax_query'] ) ) {
                    $this->current_tax_filters = $query_data['tax_query'];
                }
                // Capture search term from query.
                if ( isset( $query_data['s'] ) ) {
                    $this->current_search_term = sanitize_text_field( $query_data['s'] );
                }
                if ( isset( $query_data['_s'] ) ) {
                    $this->current_search_term = sanitize_text_field( $query_data['_s'] );
                }
            }
        }

        // Also check for search in filters array.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['filters'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $filters_data = $_POST['filters'];
            if ( is_string( $filters_data ) ) {
                $filters_data = json_decode( stripslashes( $filters_data ), true );
            }

            if ( is_array( $filters_data ) ) {
                foreach ( $filters_data as $filter ) {
                    if ( is_array( $filter ) ) {
                        // Check for search filter type.
                        if ( isset( $filter['query_type'] ) && '_s' === $filter['query_type'] && ! empty( $filter['query_val'] ) ) {
                            $this->current_search_term = sanitize_text_field( $filter['query_val'] );
                        }
                        // Also check query_var.
                        if ( isset( $filter['query_var'] ) && in_array( $filter['query_var'], array( 's', '_s', 'search', 'query' ), true ) && ! empty( $filter['query_val'] ) ) {
                            $this->current_search_term = sanitize_text_field( $filter['query_val'] );
                        }
                    }
                }
            }
        }

        // Check direct POST parameters for search.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( empty( $this->current_search_term ) && isset( $_POST['search'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->current_search_term = sanitize_text_field( wp_unslash( $_POST['search'] ) );
        }

        // Debug log.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $this->current_search_term ) ) {
            error_log( 'Jezweb Search Result: Captured search term from JSF AJAX: ' . $this->current_search_term );
        }
    }

    /**
     * Handle JetSmartFilters search query in pre_get_posts.
     *
     * @param WP_Query $query Query object.
     */
    public function handle_jsf_search_query( $query ) {
        // Only handle JSF AJAX requests.
        if ( ! wp_doing_ajax() ) {
            return;
        }

        // Check if this is a JSF request.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
        if ( 'jet_smart_filters' !== $action ) {
            return;
        }

        // Don't modify admin queries unless AJAX.
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }
    }

    /**
     * Filter posts_where to enforce search term at SQL level.
     * This ensures the search is applied even if JSF ignores the 's' parameter.
     *
     * @param string   $where    The WHERE clause of the query.
     * @param WP_Query $query    The WP_Query instance.
     * @return string
     */
    public function filter_posts_where_for_search( $where, $query ) {
        global $wpdb;

        // Only apply during JSF AJAX requests.
        if ( ! wp_doing_ajax() ) {
            return $where;
        }

        // Check if this is a JSF request.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
        if ( 'jet_smart_filters' !== $action ) {
            return $where;
        }

        // Get search term from our captured data or query.
        $search_term = '';
        if ( ! empty( $this->current_search_term ) ) {
            $search_term = $this->current_search_term;
        } elseif ( ! empty( $query->get( 's' ) ) ) {
            $search_term = $query->get( 's' );
        }

        // If no search term, return original where clause.
        if ( empty( $search_term ) ) {
            return $where;
        }

        // Check if search is already applied in WHERE clause.
        if ( stripos( $where, 'post_title LIKE' ) !== false || stripos( $where, 'post_content LIKE' ) !== false ) {
            // Search already applied, skip.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Jezweb Search Result: Search already in WHERE clause, skipping SQL injection' );
            }
            return $where;
        }

        // Build search condition - search in title, content, and excerpt.
        $search_term_escaped = '%' . $wpdb->esc_like( $search_term ) . '%';

        $search_where = $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)",
            $search_term_escaped,
            $search_term_escaped,
            $search_term_escaped
        );

        $where .= $search_where;

        // Debug logging.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Jezweb Search Result: Added SQL search condition for term: ' . $search_term );
            error_log( 'Jezweb Search Result: New WHERE clause: ' . substr( $where, -200 ) );
        }

        return $where;
    }

    /**
     * Modify JetSmartFilters query to ensure search respects active taxonomy filters.
     *
     * @param array $query Query arguments.
     * @return array
     */
    public function modify_jsf_query( $query ) {
        // Get search term from query or from captured AJAX data.
        $search_term = '';
        if ( ! empty( $query['s'] ) ) {
            $search_term = $query['s'];
        } elseif ( ! empty( $query['_s'] ) ) {
            $search_term = $query['_s'];
        } elseif ( ! empty( $this->current_search_term ) ) {
            $search_term = $this->current_search_term;
        }

        $is_search = ! empty( $search_term );

        // If we have a search term but it's not in the query, add it.
        if ( $is_search && empty( $query['s'] ) ) {
            $query['s'] = $search_term;
        }

        // Get stored filters from our transient (set by JavaScript when checkboxes are clicked).
        $stored_filters = $this->get_stored_filters();

        // Also extract any taxonomy filters already in the query.
        $query_filters = $this->extract_filters_from_query( $query );

        // Merge stored filters with query filters.
        $all_categories = array_unique( array_merge(
            $stored_filters['categories'],
            $query_filters['categories']
        ) );
        $all_tags = array_unique( array_merge(
            $stored_filters['tags'],
            $query_filters['tags']
        ) );

        // If this is a search AND we have category/tag filters, ensure they're applied.
        if ( $is_search && ( ! empty( $all_categories ) || ! empty( $all_tags ) ) ) {
            // Build or update tax_query.
            if ( ! isset( $query['tax_query'] ) || ! is_array( $query['tax_query'] ) ) {
                $query['tax_query'] = array();
            }

            // Check if category filter already exists in tax_query.
            $has_cat_filter = false;
            $has_tag_filter = false;

            foreach ( $query['tax_query'] as $tq ) {
                if ( is_array( $tq ) && isset( $tq['taxonomy'] ) ) {
                    if ( in_array( $tq['taxonomy'], array( 'product_cat', 'category' ), true ) ) {
                        $has_cat_filter = true;
                    }
                    if ( in_array( $tq['taxonomy'], array( 'product_tag', 'post_tag' ), true ) ) {
                        $has_tag_filter = true;
                    }
                }
            }

            // Add category filter if not present.
            if ( ! $has_cat_filter && ! empty( $all_categories ) ) {
                $query['tax_query'][] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => is_numeric( $all_categories[0] ) ? 'term_id' : 'slug',
                    'terms'    => $all_categories,
                    'operator' => 'IN',
                );
            }

            // Add tag filter if not present.
            if ( ! $has_tag_filter && ! empty( $all_tags ) ) {
                $query['tax_query'][] = array(
                    'taxonomy' => 'product_tag',
                    'field'    => is_numeric( $all_tags[0] ) ? 'term_id' : 'slug',
                    'terms'    => $all_tags,
                    'operator' => 'IN',
                );
            }

            // Set relation to AND if multiple tax queries.
            if ( count( $query['tax_query'] ) > 1 ) {
                $query['tax_query']['relation'] = 'AND';
            }

            // Debug logging.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Jezweb Search Result: Modified JSF search query - search: ' . ( $query['s'] ?? 'none' ) . ', categories: ' . implode( ',', $all_categories ) . ', tags: ' . implode( ',', $all_tags ) );
            }
        }

        // Debug: Log the final query state.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Jezweb Search Result: Final JSF query - s: ' . ( $query['s'] ?? 'none' ) . ', has_tax_query: ' . ( isset( $query['tax_query'] ) ? 'yes' : 'no' ) );
        }

        // Store current filters for other integrations.
        $this->store_active_filters( array(
            'categories' => $all_categories,
            'tags'       => $all_tags,
            'taxonomies' => array(),
        ) );

        return $query;
    }

    /**
     * Get stored filters from transient.
     *
     * @return array
     */
    private function get_stored_filters() {
        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );
        $filters       = get_transient( $transient_key );

        if ( ! is_array( $filters ) ) {
            $filters = array(
                'categories' => array(),
                'tags'       => array(),
                'taxonomies' => array(),
            );
        }

        return $filters;
    }

    /**
     * Extract taxonomy filters from query.
     *
     * @param array $query Query arguments.
     * @return array
     */
    private function extract_filters_from_query( $query ) {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
        );

        if ( ! isset( $query['tax_query'] ) || ! is_array( $query['tax_query'] ) ) {
            return $filters;
        }

        foreach ( $query['tax_query'] as $tax_query ) {
            if ( ! is_array( $tax_query ) || ! isset( $tax_query['taxonomy'] ) ) {
                continue;
            }

            $taxonomy = $tax_query['taxonomy'];
            $terms    = isset( $tax_query['terms'] ) ? (array) $tax_query['terms'] : array();

            if ( empty( $terms ) ) {
                continue;
            }

            if ( in_array( $taxonomy, array( 'product_cat', 'category' ), true ) ) {
                $filters['categories'] = array_merge( $filters['categories'], $terms );
            } elseif ( in_array( $taxonomy, array( 'product_tag', 'post_tag' ), true ) ) {
                $filters['tags'] = array_merge( $filters['tags'], $terms );
            }
        }

        return $filters;
    }

    /**
     * Handle filter update event.
     *
     * @param object $filter_instance Filter instance.
     */
    public function on_filter_update( $filter_instance ) {
        // This is called when a filter is updated.
        // We'll rely on JavaScript to detect changes and sync.
    }

    /**
     * Store active filters in transient.
     *
     * @param array $filters Filter data.
     */
    private function store_active_filters( $filters ) {
        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );

        // Merge with existing filters.
        $existing = get_transient( $transient_key );
        if ( is_array( $existing ) ) {
            $filters['categories'] = array_unique( array_merge(
                isset( $existing['categories'] ) ? (array) $existing['categories'] : array(),
                $filters['categories']
            ) );
            $filters['tags'] = array_unique( array_merge(
                isset( $existing['tags'] ) ? (array) $existing['tags'] : array(),
                $filters['tags']
            ) );
            $filters['taxonomies'] = array_merge(
                isset( $existing['taxonomies'] ) ? (array) $existing['taxonomies'] : array(),
                $filters['taxonomies']
            );
        }

        set_transient( $transient_key, $filters, HOUR_IN_SECONDS );
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return $ip;
    }

    /**
     * AJAX handler to sync JSF filters.
     */
    public function sync_jsf_filters() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'jezweb_search_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Get filters from POST data.
        if ( isset( $_POST['filters'] ) ) {
            $posted = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_POST['filters'] ) ) ), true );
            if ( is_array( $posted ) ) {
                if ( isset( $posted['categories'] ) ) {
                    $filters['categories'] = array_map( 'sanitize_text_field', (array) $posted['categories'] );
                }
                if ( isset( $posted['tags'] ) ) {
                    $filters['tags'] = array_map( 'sanitize_text_field', (array) $posted['tags'] );
                }
                if ( isset( $posted['taxonomies'] ) ) {
                    foreach ( (array) $posted['taxonomies'] as $tax => $terms ) {
                        $filters['taxonomies'][ sanitize_key( $tax ) ] = array_map( 'sanitize_text_field', (array) $terms );
                    }
                }
            }
        }

        // Store filters.
        $this->store_active_filters( $filters );

        wp_send_json_success( array(
            'message' => 'Filters synced.',
            'filters' => $filters,
        ) );
    }

    /**
     * Add data attributes to provider wrapper.
     *
     * @param array  $attributes Existing attributes.
     * @param string $provider   Provider name.
     * @return array
     */
    public function add_provider_data( $attributes, $provider ) {
        $attributes['data-jezweb-jsf-provider'] = $provider;
        return $attributes;
    }

    /**
     * Add JavaScript for JetSmartFilters integration.
     */
    public function add_jsf_enhancements() {
        if ( ! $this->is_jsf_active() ) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';

            if (typeof $ === 'undefined') {
                return;
            }

            var syncDebounceTimer = null;

            /**
             * Extract filters from JetSmartFilters state
             */
            function extractJSFFilters() {
                var filters = {
                    categories: [],
                    tags: [],
                    taxonomies: {}
                };

                // Method 1: Check JetSmartFilters global object
                if (typeof window.JetSmartFilters !== 'undefined') {
                    try {
                        var filterGroups = window.JetSmartFilters.filterGroups || {};

                        for (var groupId in filterGroups) {
                            if (!filterGroups.hasOwnProperty(groupId)) continue;

                            var group = filterGroups[groupId];
                            var activeFilters = group.activeFilters || group.filters || {};

                            for (var filterId in activeFilters) {
                                if (!activeFilters.hasOwnProperty(filterId)) continue;

                                var filter = activeFilters[filterId];
                                var queryVar = filter.queryVar || filter.query_var || '';
                                var value = filter.value || filter.current_value || '';

                                if (!queryVar || !value) continue;

                                var values = Array.isArray(value) ? value : [value];
                                values = values.filter(function(v) { return v !== '' && v !== '0'; });

                                if (values.length === 0) continue;

                                if (queryVar.indexOf('cat') !== -1 || queryVar === 'category') {
                                    filters.categories = filters.categories.concat(values);
                                } else if (queryVar.indexOf('tag') !== -1) {
                                    filters.tags = filters.tags.concat(values);
                                } else {
                                    var key = 'jsf_' + queryVar;
                                    if (!filters.taxonomies[key]) {
                                        filters.taxonomies[key] = [];
                                    }
                                    filters.taxonomies[key] = filters.taxonomies[key].concat(values);
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Jezweb Search Result: Error reading JetSmartFilters state', e);
                    }
                }

                // Method 2: Read from DOM elements
                var jsfCheckboxes = document.querySelectorAll('.jet-checkboxes-list__input:checked, .jet-radio-list__input:checked');
                jsfCheckboxes.forEach(function(input) {
                    var value = input.value;
                    if (!value || value === '' || value === '0') return;

                    var filterWrap = input.closest('.jet-filter, [data-content-id], .jet-smart-filters-checkbox, .jet-smart-filters-radio');
                    var taxonomy = '';

                    if (filterWrap) {
                        taxonomy = filterWrap.getAttribute('data-query-var') ||
                                  filterWrap.getAttribute('data-taxonomy') ||
                                  input.name.replace('[]', '');
                    }

                    if (!taxonomy) {
                        taxonomy = input.name.replace('[]', '');
                    }

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

                // Method 3: Read from select elements
                var jsfSelects = document.querySelectorAll('.jet-select__control, .jet-smart-filters-select select');
                jsfSelects.forEach(function(select) {
                    var value = select.value;
                    if (!value || value === '' || value === '0' || value === '-1') return;

                    var filterWrap = select.closest('.jet-filter, [data-content-id]');
                    var taxonomy = '';

                    if (filterWrap) {
                        taxonomy = filterWrap.getAttribute('data-query-var') ||
                                  filterWrap.getAttribute('data-taxonomy') ||
                                  select.name;
                    }

                    if (!taxonomy) {
                        taxonomy = select.name;
                    }

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

                // Method 4: Read active tags
                var activeTags = document.querySelectorAll('.jet-active-tag:not(.jet-active-tag--all)');
                activeTags.forEach(function(tag) {
                    var value = tag.getAttribute('data-value');
                    var taxonomy = tag.getAttribute('data-query-var') || tag.getAttribute('data-taxonomy');

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

                // Remove duplicates
                filters.categories = [...new Set(filters.categories)];
                filters.tags = [...new Set(filters.tags)];

                for (var taxKey in filters.taxonomies) {
                    filters.taxonomies[taxKey] = [...new Set(filters.taxonomies[taxKey])];
                }

                return filters;
            }

            /**
             * Sync filters to server
             */
            function syncFiltersToServer(filters) {
                if (typeof jezwebSearchResult === 'undefined') {
                    return;
                }

                $.ajax({
                    url: jezwebSearchResult.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'jezweb_sync_jsf_filters',
                        nonce: jezwebSearchResult.nonce,
                        filters: JSON.stringify(filters)
                    },
                    success: function(response) {
                        if (jezwebSearchResult.debug) {
                            console.log('Jezweb Search Result: Filters synced', response);
                        }
                    }
                });
            }

            /**
             * Handle filter changes
             */
            function onFilterChange() {
                // Debounce to prevent excessive calls
                clearTimeout(syncDebounceTimer);
                syncDebounceTimer = setTimeout(function() {
                    var filters = extractJSFFilters();

                    // Update global detected filters
                    if (typeof window.jezwebDetectedFilters !== 'undefined') {
                        window.jezwebDetectedFilters.categories = [
                            ...new Set([...window.jezwebDetectedFilters.categories, ...filters.categories])
                        ];
                        window.jezwebDetectedFilters.tags = [
                            ...new Set([...window.jezwebDetectedFilters.tags, ...filters.tags])
                        ];
                        window.jezwebDetectedFilters.taxonomies = Object.assign(
                            {},
                            window.jezwebDetectedFilters.taxonomies,
                            filters.taxonomies
                        );
                    } else {
                        window.jezwebDetectedFilters = filters;
                    }

                    // Sync to server
                    syncFiltersToServer(filters);

                    // Trigger custom event
                    document.dispatchEvent(new CustomEvent('jezweb-jsf-filters-updated', {
                        detail: filters
                    }));

                }, 150);
            }

            // Listen for JetSmartFilters events
            $(document).on('jet-smart-filters/inited', function() {
                onFilterChange();
            });

            $(document).on('jet-filter-data-updated', function() {
                onFilterChange();
            });

            $(document).on('jet-smart-filters/before-ajax-request', function() {
                onFilterChange();
            });

            // Listen for checkbox/radio/select changes
            $(document).on('change', '.jet-checkboxes-list__input, .jet-radio-list__input, .jet-select__control', function() {
                onFilterChange();
            });

            // Listen for active tag removal
            $(document).on('click', '.jet-active-tag__remove, .jet-active-tags__clear', function() {
                setTimeout(onFilterChange, 100);
            });

            // Initial extraction
            $(document).ready(function() {
                setTimeout(onFilterChange, 500);
            });

            // Re-extract after AJAX
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url && settings.url.indexOf('jet-smart-filters') !== -1) {
                    setTimeout(onFilterChange, 100);
                }
            });

            // Expose extraction function globally
            window.jezwebExtractJSFFilters = extractJSFFilters;

        })(jQuery);
        </script>
        <?php
    }
}
