<?php
/**
 * WooCommerce Integration Class
 *
 * Handles integration with WooCommerce product search and filters.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_WooCommerce_Integration class.
 */
class Jezweb_WooCommerce_Integration {

    /**
     * Constructor.
     */
    public function __construct() {
        // Check if WooCommerce is active.
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Check if integration is enabled.
     *
     * @return bool
     */
    private function is_enabled() {
        $settings = get_option( 'jezweb_search_result_settings', array() );
        return isset( $settings['enable_woocommerce'] ) ? $settings['enable_woocommerce'] : true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Modify WooCommerce product search query.
        add_action( 'woocommerce_product_query', array( $this, 'modify_product_query' ), 99 );

        // Modify the WooCommerce shortcode products query.
        add_filter( 'woocommerce_shortcode_products_query', array( $this, 'modify_shortcode_query' ), 99, 2 );

        // Hook into layered nav widget.
        add_filter( 'woocommerce_layered_nav_term_html', array( $this, 'add_term_data_attributes' ), 10, 4 );

        // Add archive context data.
        add_action( 'woocommerce_before_shop_loop', array( $this, 'add_archive_context' ), 5 );

        // Modify search widget output.
        add_filter( 'get_product_search_form', array( $this, 'modify_search_form' ) );

        // Hook into AJAX product search.
        add_action( 'wp_ajax_woocommerce_json_search_products', array( $this, 'before_ajax_search' ), 1 );
        add_action( 'wp_ajax_nopriv_woocommerce_json_search_products', array( $this, 'before_ajax_search' ), 1 );

        // Add JavaScript enhancements.
        add_action( 'wp_footer', array( $this, 'add_wc_enhancements' ), 20 );

        // Store category/tag from layered nav.
        add_action( 'woocommerce_layered_nav_count', array( $this, 'capture_layered_nav' ), 10, 3 );
    }

    /**
     * Check if current request is a JetSmartFilters AJAX request.
     *
     * @return bool
     */
    private function is_jsf_ajax_request() {
        if ( ! wp_doing_ajax() ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

        return 'jet_smart_filters' === $action;
    }

    /**
     * Modify WooCommerce product query.
     *
     * @param WP_Query $query Query object.
     */
    public function modify_product_query( $query ) {
        // Check if query object has is_search method.
        $is_search = false;
        if ( is_object( $query ) && method_exists( $query, 'is_search' ) ) {
            $is_search = $query->is_search();
        } elseif ( function_exists( 'is_search' ) && is_search() ) {
            $is_search = true;
        }

        // Don't modify if not a search query.
        if ( ! $is_search ) {
            return;
        }

        $filters = $this->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return;
        }

        $tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $tax_query ) ) {
            $existing = $query->get( 'tax_query' );

            if ( ! empty( $existing ) && is_array( $existing ) ) {
                $tax_query = array_merge( array( 'relation' => 'AND' ), $existing, $tax_query );
            }

            $query->set( 'tax_query', $tax_query );
        }
    }

    /**
     * Modify WooCommerce shortcode query.
     *
     * @param array $args     Query arguments.
     * @param array $atts     Shortcode attributes.
     * @return array
     */
    public function modify_shortcode_query( $args, $atts ) {
        // Only modify if this is a search context.
        if ( ! is_search() ) {
            return $args;
        }

        $filters = $this->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return $args;
        }

        $tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $tax_query ) ) {
            if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
                $args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $args['tax_query'], $tax_query );
            } else {
                $args['tax_query'] = $tax_query;
            }
        }

        return $args;
    }

    /**
     * Get active filters.
     *
     * @return array
     */
    private function get_active_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Get from URL parameters.
        if ( isset( $_GET['product_cat'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_GET['product_cat'] ) );
            $filters['categories'] = strpos( $value, ',' ) !== false
                ? array_map( 'trim', explode( ',', $value ) )
                : array( $value );
        }

        if ( isset( $_GET['product_tag'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_GET['product_tag'] ) );
            $filters['tags'] = strpos( $value, ',' ) !== false
                ? array_map( 'trim', explode( ',', $value ) )
                : array( $value );
        }

        // Get from POST data (AJAX).
        if ( isset( $_POST['jezweb_filters'] ) ) {
            $posted = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_POST['jezweb_filters'] ) ) ), true );
            if ( is_array( $posted ) ) {
                if ( isset( $posted['categories'] ) ) {
                    $filters['categories'] = array_merge(
                        $filters['categories'],
                        array_map( 'sanitize_text_field', (array) $posted['categories'] )
                    );
                }
                if ( isset( $posted['tags'] ) ) {
                    $filters['tags'] = array_merge(
                        $filters['tags'],
                        array_map( 'sanitize_text_field', (array) $posted['tags'] )
                    );
                }
            }
        }

        // Get from transient.
        $search_filter = jezweb_search_result()->search_filter;
        if ( $search_filter ) {
            $stored = $search_filter->get_active_filters();
            $filters['categories'] = array_unique( array_merge( $filters['categories'], $stored['categories'] ) );
            $filters['tags']       = array_unique( array_merge( $filters['tags'], $stored['tags'] ) );
            $filters['taxonomies'] = array_merge( $filters['taxonomies'], $stored['taxonomies'] );
        }

        // Check current archive context.
        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $term = get_queried_object();
            if ( $term && isset( $term->slug ) ) {
                $filters['categories'][] = $term->slug;
            }
        }

        if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
            $term = get_queried_object();
            if ( $term && isset( $term->slug ) ) {
                $filters['tags'][] = $term->slug;
            }
        }

        // Check for filter widgets.
        $filters = $this->get_layered_nav_filters( $filters );

        // Clean up.
        $filters['categories'] = array_unique( array_filter( $filters['categories'] ) );
        $filters['tags']       = array_unique( array_filter( $filters['tags'] ) );

        return $filters;
    }

    /**
     * Get filters from WooCommerce layered nav.
     *
     * @param array $filters Existing filters.
     * @return array
     */
    private function get_layered_nav_filters( $filters ) {
        // Check if WC_Query class exists.
        if ( ! class_exists( 'WC_Query' ) || ! method_exists( 'WC_Query', 'get_layered_nav_chosen_attributes' ) ) {
            return $filters;
        }

        // Check for WooCommerce filter widgets.
        $filter_terms = WC_Query::get_layered_nav_chosen_attributes();

        foreach ( $filter_terms as $taxonomy => $data ) {
            if ( empty( $data['terms'] ) ) {
                continue;
            }

            if ( 'product_cat' === $taxonomy ) {
                $filters['categories'] = array_merge( $filters['categories'], $data['terms'] );
            } elseif ( 'product_tag' === $taxonomy ) {
                $filters['tags'] = array_merge( $filters['tags'], $data['terms'] );
            } else {
                $key = 'jsf_' . $taxonomy;
                if ( ! isset( $filters['taxonomies'][ $key ] ) ) {
                    $filters['taxonomies'][ $key ] = array();
                }
                $filters['taxonomies'][ $key ] = array_merge( $filters['taxonomies'][ $key ], $data['terms'] );
            }
        }

        return $filters;
    }

    /**
     * Build tax query from filters.
     *
     * @param array $filters Filter data.
     * @return array
     */
    private function build_tax_query( $filters ) {
        $tax_query = array();

        if ( ! empty( $filters['categories'] ) ) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => $this->is_numeric_array( $filters['categories'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['categories'],
                'operator' => 'IN',
            );
        }

        if ( ! empty( $filters['tags'] ) ) {
            $tax_query[] = array(
                'taxonomy' => 'product_tag',
                'field'    => $this->is_numeric_array( $filters['tags'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['tags'],
                'operator' => 'IN',
            );
        }

        if ( ! empty( $filters['taxonomies'] ) ) {
            foreach ( $filters['taxonomies'] as $key => $terms ) {
                $taxonomy = preg_replace( '/^jsf_/', '', $key );
                if ( taxonomy_exists( $taxonomy ) && ! empty( $terms ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => $this->is_numeric_array( $terms ) ? 'term_id' : 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }

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
     * Add data attributes to layered nav terms.
     *
     * @param string  $term_html HTML output.
     * @param object  $term      Term object.
     * @param string  $link      Term link.
     * @param int     $count     Product count.
     * @return string
     */
    public function add_term_data_attributes( $term_html, $term, $link, $count ) {
        // Add data attributes for JavaScript detection.
        $data_attrs = sprintf(
            'data-jezweb-term-id="%d" data-jezweb-term-slug="%s" data-jezweb-taxonomy="%s"',
            esc_attr( $term->term_id ),
            esc_attr( $term->slug ),
            esc_attr( $term->taxonomy )
        );

        // Insert data attributes into the link.
        $term_html = str_replace( '<a ', '<a ' . $data_attrs . ' ', $term_html );

        return $term_html;
    }

    /**
     * Add archive context data.
     */
    public function add_archive_context() {
        $term = get_queried_object();

        if ( ! $term || ! isset( $term->term_id ) ) {
            return;
        }

        printf(
            '<div data-jezweb-current-term="1" data-jezweb-term-id="%d" data-jezweb-term-slug="%s" data-jezweb-taxonomy="%s" style="display:none;"></div>',
            esc_attr( $term->term_id ),
            esc_attr( $term->slug ),
            esc_attr( $term->taxonomy )
        );
    }

    /**
     * Modify WooCommerce search form.
     *
     * @param string $form Form HTML.
     * @return string
     */
    public function modify_search_form( $form ) {
        // Add class for identification.
        $form = str_replace(
            'class="woocommerce-product-search"',
            'class="woocommerce-product-search jezweb-enhanced-search"',
            $form
        );

        // Add hidden fields placeholder (will be populated by JavaScript).
        $hidden_fields = '<input type="hidden" name="jezweb_filters" value="">';

        // Insert before closing form tag.
        $form = str_replace( '</form>', $hidden_fields . '</form>', $form );

        return $form;
    }

    /**
     * Hook before AJAX search to apply filters.
     */
    public function before_ajax_search() {
        // Add filter to modify the search query.
        add_filter( 'posts_where', array( $this, 'modify_ajax_search_where' ), 99, 2 );
    }

    /**
     * Modify AJAX search WHERE clause.
     *
     * @param string   $where WHERE clause.
     * @param WP_Query $query Query object.
     * @return string
     */
    public function modify_ajax_search_where( $where, $query ) {
        global $wpdb;

        $filters = $this->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) ) {
            return $where;
        }

        // Build additional WHERE conditions.
        $term_ids = array();

        if ( ! empty( $filters['categories'] ) ) {
            foreach ( $filters['categories'] as $cat ) {
                $term = get_term_by( is_numeric( $cat ) ? 'id' : 'slug', $cat, 'product_cat' );
                if ( $term ) {
                    $term_ids[] = $term->term_id;
                }
            }
        }

        if ( ! empty( $filters['tags'] ) ) {
            foreach ( $filters['tags'] as $tag ) {
                $term = get_term_by( is_numeric( $tag ) ? 'id' : 'slug', $tag, 'product_tag' );
                if ( $term ) {
                    $term_ids[] = $term->term_id;
                }
            }
        }

        if ( ! empty( $term_ids ) ) {
            // Sanitize term IDs as integers for safe SQL.
            $term_ids_string = implode( ',', array_map( 'intval', $term_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $term_ids_string is safely sanitized with intval above.
            $where .= " AND {$wpdb->posts}.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships}
                WHERE term_taxonomy_id IN (
                    SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                    WHERE term_id IN ({$term_ids_string})
                )
            )";
        }

        return $where;
    }

    /**
     * Capture layered nav filter state.
     *
     * @param string  $count     Count HTML.
     * @param WP_Term $term      Term object.
     * @param string  $taxonomy  Taxonomy name.
     * @return string
     */
    public function capture_layered_nav( $count, $term, $taxonomy ) {
        // This is used to track which filters are displayed.
        return $count;
    }

    /**
     * Add JavaScript enhancements for WooCommerce.
     */
    public function add_wc_enhancements() {
        if ( ! $this->is_woocommerce_active() ) {
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
             * Enhance WooCommerce search forms
             */
            function enhanceWCSearch() {
                var searchForms = $(
                    '.woocommerce-product-search, ' +
                    'form[role="search"], ' +
                    '.search-form'
                );

                searchForms.each(function() {
                    var $form = $(this);

                    // Skip if already enhanced
                    if ($form.data('jezweb-wc-enhanced')) {
                        return;
                    }

                    $form.data('jezweb-wc-enhanced', true);

                    // Ensure hidden fields exist
                    if (!$form.find('input[name="jezweb_filters"]').length) {
                        $form.append('<input type="hidden" name="jezweb_filters" value="">');
                    }

                    // Update on submit
                    $form.on('submit', function() {
                        updateWCSearchFilters($form);
                    });
                });
            }

            /**
             * Update filter fields before form submission
             */
            function updateWCSearchFilters($form) {
                var filters = window.jezwebDetectedFilters || {
                    categories: [],
                    tags: [],
                    taxonomies: {}
                };

                // Re-detect filters
                if (typeof window.jezwebDetectFilters === 'function') {
                    filters = window.jezwebDetectFilters();
                }

                // Also detect from WC layered nav
                var wcFilters = detectWCLayeredNav();
                filters = mergeFilters(filters, wcFilters);

                // Update hidden field
                $form.find('input[name="jezweb_filters"]').val(JSON.stringify(filters));

                // Add individual parameters
                if (filters.categories.length > 0) {
                    var catInput = $form.find('input[name="product_cat"]');
                    if (catInput.length === 0) {
                        $form.append('<input type="hidden" name="product_cat" value="' + filters.categories.join(',') + '">');
                    } else {
                        catInput.val(filters.categories.join(','));
                    }
                }

                if (filters.tags.length > 0) {
                    var tagInput = $form.find('input[name="product_tag"]');
                    if (tagInput.length === 0) {
                        $form.append('<input type="hidden" name="product_tag" value="' + filters.tags.join(',') + '">');
                    } else {
                        tagInput.val(filters.tags.join(','));
                    }
                }
            }

            /**
             * Detect filters from WooCommerce layered nav
             */
            function detectWCLayeredNav() {
                var filters = { categories: [], tags: [], taxonomies: {} };

                // Check chosen items
                $('.woocommerce-widget-layered-nav-list__item--chosen').each(function() {
                    var $item = $(this);
                    var $link = $item.find('a');

                    if ($link.length) {
                        var termId = $link.attr('data-jezweb-term-id');
                        var termSlug = $link.attr('data-jezweb-term-slug');
                        var taxonomy = $link.attr('data-jezweb-taxonomy');

                        var value = termSlug || termId;

                        if (value && taxonomy) {
                            if (taxonomy === 'product_cat') {
                                filters.categories.push(value);
                            } else if (taxonomy === 'product_tag') {
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

                // Check active filters from URL
                var params = new URLSearchParams(window.location.search);

                // WooCommerce layered nav uses filter_ prefix
                params.forEach(function(value, key) {
                    if (key.indexOf('filter_') === 0) {
                        var taxonomy = key.replace('filter_', '');
                        var values = value.split(',');

                        if (taxonomy === 'product_cat' || taxonomy === 'cat') {
                            filters.categories = filters.categories.concat(values);
                        } else if (taxonomy === 'product_tag' || taxonomy === 'tag') {
                            filters.tags = filters.tags.concat(values);
                        } else {
                            if (!filters.taxonomies['jsf_pa_' + taxonomy]) {
                                filters.taxonomies['jsf_pa_' + taxonomy] = [];
                            }
                            filters.taxonomies['jsf_pa_' + taxonomy] = filters.taxonomies['jsf_pa_' + taxonomy].concat(values);
                        }
                    }
                });

                return filters;
            }

            /**
             * Merge filter objects
             */
            function mergeFilters(f1, f2) {
                return {
                    categories: [...new Set([...f1.categories, ...f2.categories])],
                    tags: [...new Set([...f1.tags, ...f2.tags])],
                    taxonomies: Object.assign({}, f1.taxonomies, f2.taxonomies)
                };
            }

            // Initialize
            $(document).ready(enhanceWCSearch);
            $(document).ajaxComplete(function() {
                setTimeout(enhanceWCSearch, 100);
            });

            // Listen for WC events
            $(document.body).on('updated_wc_div', enhanceWCSearch);
            $(document.body).on('updated_checkout', enhanceWCSearch);

            // Expose for external use
            window.jezwebDetectWCFilters = detectWCLayeredNav;

        })(jQuery);
        </script>
        <?php
    }
}
