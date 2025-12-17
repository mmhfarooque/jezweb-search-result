<?php
/**
 * JetSearch Integration Class
 *
 * Handles integration with Crocoblock JetSearch plugin.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_JetSearch_Integration class.
 */
class Jezweb_JetSearch_Integration {

    /**
     * Constructor.
     */
    public function __construct() {
        // Check if JetSearch is active.
        if ( ! $this->is_jetsearch_active() ) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if JetSearch is active.
     *
     * @return bool
     */
    private function is_jetsearch_active() {
        return defined( 'JET_SEARCH_VERSION' ) || class_exists( 'Jet_Search' );
    }

    /**
     * Check if integration is enabled.
     *
     * @return bool
     */
    private function is_enabled() {
        $settings = get_option( 'jezweb_search_result_settings', array() );
        return isset( $settings['enable_jetsearch'] ) ? $settings['enable_jetsearch'] : true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Hook into JetSearch query args.
        add_filter( 'jet-search/ajax-search/query-args', array( $this, 'modify_search_query_args' ), 99, 2 );

        // Hook into JetSearch REST API request.
        add_filter( 'jet-search/rest-api/search-args', array( $this, 'modify_rest_search_args' ), 99 );

        // Modify search results before output.
        add_filter( 'jet-search/ajax-search/results', array( $this, 'filter_search_results' ), 99, 2 );

        // Add custom data to AJAX request.
        add_action( 'wp_footer', array( $this, 'add_jetsearch_enhancements' ), 20 );

        // Hook into JetSearch widget render.
        add_filter( 'jet-search/ajax-search/localize-data', array( $this, 'add_localize_data' ) );

        // Handle custom results page query.
        add_action( 'pre_get_posts', array( $this, 'modify_results_page_query' ), 99 );
    }

    /**
     * Modify JetSearch AJAX query arguments.
     *
     * @param array $args   Query arguments.
     * @param mixed $widget Widget instance (optional).
     * @return array
     */
    public function modify_search_query_args( $args, $widget = null ) {
        // Get filters from various sources.
        $filters = $this->get_all_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return $args;
        }

        // Build tax query.
        $tax_query = $this->build_tax_query( $filters, $args );

        if ( ! empty( $tax_query ) ) {
            if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
                // Merge with existing tax query.
                $args['tax_query'] = array_merge(
                    array( 'relation' => 'AND' ),
                    $args['tax_query'],
                    $tax_query
                );
            } else {
                $args['tax_query'] = $tax_query;
            }
        }

        /**
         * Filter the modified JetSearch query args.
         *
         * @since 1.0.0
         * @param array $args    Modified query arguments.
         * @param array $filters Active filters.
         */
        return apply_filters( 'jezweb_search_result_jetsearch_query_args', $args, $filters );
    }

    /**
     * Modify REST API search arguments.
     *
     * @param array $args Search arguments.
     * @return array
     */
    public function modify_rest_search_args( $args ) {
        return $this->modify_search_query_args( $args );
    }

    /**
     * Get all active filters from various sources.
     *
     * @return array
     */
    private function get_all_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Get from POST/GET data sent with AJAX request.
        if ( isset( $_REQUEST['jezweb_filters'] ) ) {
            $posted = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_REQUEST['jezweb_filters'] ) ) ), true );
            if ( is_array( $posted ) ) {
                $filters = $this->merge_filters( $filters, $posted );
            }
        }

        // Get from individual parameters.
        $category_params = array( 'product_cat', 'category', 'jezweb_categories' );
        foreach ( $category_params as $param ) {
            if ( isset( $_REQUEST[ $param ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) );
                $values = strpos( $value, ',' ) !== false ? explode( ',', $value ) : array( $value );
                $filters['categories'] = array_merge( $filters['categories'], array_map( 'trim', $values ) );
            }
        }

        $tag_params = array( 'product_tag', 'tag', 'jezweb_tags' );
        foreach ( $tag_params as $param ) {
            if ( isset( $_REQUEST[ $param ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) );
                $values = strpos( $value, ',' ) !== false ? explode( ',', $value ) : array( $value );
                $filters['tags'] = array_merge( $filters['tags'], array_map( 'trim', $values ) );
            }
        }

        // Get JetSmartFilters parameters.
        foreach ( $_REQUEST as $key => $value ) {
            if ( strpos( $key, 'jsf_' ) === 0 || strpos( $key, 'tax-' ) === 0 ) {
                $sanitized_value = is_array( $value )
                    ? array_map( 'sanitize_text_field', $value )
                    : explode( ',', sanitize_text_field( wp_unslash( $value ) ) );

                $filters['taxonomies'][ sanitize_key( $key ) ] = $sanitized_value;
            }
        }

        // Get from transient/session.
        $search_filter = jezweb_search_result()->search_filter;
        if ( $search_filter ) {
            $stored_filters = $search_filter->get_active_filters();
            $filters = $this->merge_filters( $filters, $stored_filters );
        }

        // Clean up arrays.
        $filters['categories'] = array_unique( array_filter( $filters['categories'] ) );
        $filters['tags']       = array_unique( array_filter( $filters['tags'] ) );

        return $filters;
    }

    /**
     * Merge filter arrays.
     *
     * @param array $filters1 First filter array.
     * @param array $filters2 Second filter array.
     * @return array
     */
    private function merge_filters( $filters1, $filters2 ) {
        return array(
            'categories' => array_unique( array_merge(
                isset( $filters1['categories'] ) ? (array) $filters1['categories'] : array(),
                isset( $filters2['categories'] ) ? (array) $filters2['categories'] : array()
            ) ),
            'tags' => array_unique( array_merge(
                isset( $filters1['tags'] ) ? (array) $filters1['tags'] : array(),
                isset( $filters2['tags'] ) ? (array) $filters2['tags'] : array()
            ) ),
            'taxonomies' => array_merge(
                isset( $filters1['taxonomies'] ) ? (array) $filters1['taxonomies'] : array(),
                isset( $filters2['taxonomies'] ) ? (array) $filters2['taxonomies'] : array()
            ),
        );
    }

    /**
     * Build tax query from filters.
     *
     * @param array $filters Active filters.
     * @param array $args    Existing query args.
     * @return array
     */
    private function build_tax_query( $filters, $args = array() ) {
        $tax_query = array();

        // Determine post type context.
        $post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'any';
        $is_product = ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) );

        // Category filter.
        if ( ! empty( $filters['categories'] ) ) {
            $taxonomy = $is_product ? 'product_cat' : 'category';

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $filters['categories'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['categories'],
                'operator' => 'IN',
            );
        }

        // Tag filter.
        if ( ! empty( $filters['tags'] ) ) {
            $taxonomy = $is_product ? 'product_tag' : 'post_tag';

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $filters['tags'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['tags'],
                'operator' => 'IN',
            );
        }

        // Custom taxonomy filters.
        if ( ! empty( $filters['taxonomies'] ) ) {
            foreach ( $filters['taxonomies'] as $key => $terms ) {
                if ( empty( $terms ) ) {
                    continue;
                }

                // Extract taxonomy name from key.
                $taxonomy = preg_replace( '/^(jsf_|tax-)/', '', $key );

                if ( taxonomy_exists( $taxonomy ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => $this->is_numeric_array( $terms ) ? 'term_id' : 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }

        // Set relation if multiple queries.
        if ( count( $tax_query ) > 1 ) {
            array_unshift( $tax_query, array( 'relation' => 'AND' ) );
        }

        return $tax_query;
    }

    /**
     * Check if array contains only numeric values.
     *
     * @param array $array Array to check.
     * @return bool
     */
    private function is_numeric_array( $array ) {
        if ( ! is_array( $array ) || empty( $array ) ) {
            return false;
        }

        foreach ( $array as $value ) {
            if ( ! is_numeric( $value ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter search results.
     *
     * @param array $results Search results.
     * @param array $args    Query arguments.
     * @return array
     */
    public function filter_search_results( $results, $args ) {
        // Results are already filtered by the modified query.
        // This hook is available for additional processing if needed.
        return apply_filters( 'jezweb_search_result_jetsearch_results', $results, $args );
    }

    /**
     * Add localization data for JetSearch.
     *
     * @param array $data Localization data.
     * @return array
     */
    public function add_localize_data( $data ) {
        $data['jezwebSearchIntegration'] = true;

        return $data;
    }

    /**
     * Add JavaScript enhancements for JetSearch.
     */
    public function add_jetsearch_enhancements() {
        if ( ! $this->is_jetsearch_active() ) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';

            if (typeof $ === 'undefined') {
                return;
            }

            /**
             * Enhance JetSearch AJAX requests
             */
            function enhanceJetSearch() {
                // Hook into JetSearch AJAX send data
                if (typeof window.JetAjaxSearchSettings !== 'undefined') {
                    var originalSendData = window.JetAjaxSearchSettings.ajaxSendData || {};

                    // Add filter data to AJAX requests
                    window.JetAjaxSearchSettings.ajaxSendData = Object.assign({}, originalSendData, {
                        jezweb_filters: JSON.stringify(window.jezwebDetectedFilters || {})
                    });
                }

                // Intercept jQuery AJAX requests to JetSearch
                if ($.ajaxPrefilter) {
                    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                        // Check if this is a JetSearch request
                        if (options.url && (
                            options.url.indexOf('jet-search') !== -1 ||
                            options.url.indexOf('jet_ajax_search') !== -1
                        )) {
                            // Get current filters
                            var filters = window.jezwebDetectedFilters || {
                                categories: [],
                                tags: [],
                                taxonomies: {}
                            };

                            // Re-detect filters
                            if (typeof window.jezwebDetectFilters === 'function') {
                                filters = window.jezwebDetectFilters();
                            }

                            // Add filters to request data
                            if (options.data) {
                                if (typeof options.data === 'string') {
                                    options.data += '&jezweb_filters=' + encodeURIComponent(JSON.stringify(filters));

                                    // Add individual filter parameters
                                    if (filters.categories.length > 0) {
                                        options.data += '&jezweb_categories=' + encodeURIComponent(filters.categories.join(','));
                                    }
                                    if (filters.tags.length > 0) {
                                        options.data += '&jezweb_tags=' + encodeURIComponent(filters.tags.join(','));
                                    }
                                } else if (typeof options.data === 'object') {
                                    options.data.jezweb_filters = JSON.stringify(filters);
                                    options.data.jezweb_categories = filters.categories.join(',');
                                    options.data.jezweb_tags = filters.tags.join(',');
                                }
                            }
                        }
                    });
                }

                // Enhance JetSearch form submissions
                $('.jet-ajax-search__form, .jet-ajax-search form').each(function() {
                    var $form = $(this);

                    if ($form.data('jezweb-jetsearch-enhanced')) {
                        return;
                    }

                    $form.data('jezweb-jetsearch-enhanced', true);

                    // Add hidden fields
                    if (!$form.find('input[name="jezweb_filters"]').length) {
                        $form.append('<input type="hidden" name="jezweb_filters" value="">');
                        $form.append('<input type="hidden" name="jezweb_categories" value="">');
                        $form.append('<input type="hidden" name="jezweb_tags" value="">');
                    }

                    // Update fields before submission
                    $form.on('submit', function() {
                        var filters = window.jezwebDetectedFilters || {};

                        if (typeof window.jezwebDetectFilters === 'function') {
                            filters = window.jezwebDetectFilters();
                        }

                        $form.find('input[name="jezweb_filters"]').val(JSON.stringify(filters));
                        $form.find('input[name="jezweb_categories"]').val((filters.categories || []).join(','));
                        $form.find('input[name="jezweb_tags"]').val((filters.tags || []).join(','));
                    });
                });
            }

            // Listen for JetSearch events
            $(document).on('jet-ajax-search/start-loading', function() {
                // Update filters data before search
                if (typeof window.jezwebDetectFilters === 'function') {
                    window.jezwebDetectFilters();
                }
            });

            // Initialize
            $(document).ready(enhanceJetSearch);
            $(document).ajaxComplete(function() {
                setTimeout(enhanceJetSearch, 100);
            });

            // Re-enhance after JetSmartFilters updates
            $(document).on('jet-smart-filters/inited', enhanceJetSearch);
            $(document).on('jet-filter-data-updated', enhanceJetSearch);

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Modify custom results page query.
     *
     * @param WP_Query $query Query object.
     */
    public function modify_results_page_query( $query ) {
        // Only modify main query on search results.
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        // Check if this is a JetSearch results page.
        $settings = get_option( 'jet_search_ajax_search', array() );
        $results_page = isset( $settings['results_page'] ) ? $settings['results_page'] : '';

        if ( empty( $results_page ) ) {
            return;
        }

        // Apply our filters.
        $filters = $this->get_all_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return;
        }

        $tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $tax_query ) ) {
            $existing = $query->get( 'tax_query' );

            if ( ! empty( $existing ) ) {
                $tax_query = array_merge( array( 'relation' => 'AND' ), $existing, $tax_query );
            }

            $query->set( 'tax_query', $tax_query );
        }
    }
}
