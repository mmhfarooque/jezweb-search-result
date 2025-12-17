<?php
/**
 * Core Search Filter Class
 *
 * Handles modification of WordPress search queries to respect active category/tag filters.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_Search_Filter class.
 */
class Jezweb_Search_Filter {

    /**
     * Active category filters.
     *
     * @var array
     */
    private $active_categories = array();

    /**
     * Active tag filters.
     *
     * @var array
     */
    private $active_tags = array();

    /**
     * Active taxonomy filters.
     *
     * @var array
     */
    private $active_taxonomies = array();

    /**
     * Plugin settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = get_option( 'jezweb_search_result_settings', array() );

        // Only run if enabled.
        if ( ! $this->is_enabled() ) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if plugin is enabled.
     *
     * @return bool
     */
    private function is_enabled() {
        return isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Hook into WordPress main query.
        add_action( 'pre_get_posts', array( $this, 'modify_search_query' ), 99 );

        // Hook into WooCommerce product query.
        add_filter( 'woocommerce_product_query_tax_query', array( $this, 'modify_wc_tax_query' ), 99, 2 );

        // REST API filter for JetSearch.
        add_filter( 'jet-search/ajax-search/query-args', array( $this, 'modify_jet_search_query' ), 99, 2 );

        // Register REST API endpoint for filter synchronization.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Modify the main search query to include taxonomy filters.
     *
     * @param WP_Query $query The query object.
     */
    public function modify_search_query( $query ) {
        // Don't modify admin queries.
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Only modify search queries.
        if ( ! $query->is_search() ) {
            return;
        }

        // Get active filters.
        $filters = $this->get_active_filters();

        // If no filters active, don't modify.
        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return;
        }

        // Build tax query.
        $tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $tax_query ) ) {
            $existing_tax_query = $query->get( 'tax_query' );

            if ( ! empty( $existing_tax_query ) ) {
                $tax_query = array_merge( array( 'relation' => 'AND' ), $existing_tax_query, $tax_query );
            }

            $query->set( 'tax_query', $tax_query );

            /**
             * Fires after search query is modified.
             *
             * @since 1.0.0
             * @param WP_Query $query   The modified query object.
             * @param array    $filters The active filters applied.
             */
            do_action( 'jezweb_search_result_query_modified', $query, $filters );
        }
    }

    /**
     * Modify WooCommerce tax query for product searches.
     *
     * @param array    $tax_query Existing tax query.
     * @param WP_Query $query     The query object.
     * @return array
     */
    public function modify_wc_tax_query( $tax_query, $query ) {
        // Check if query object has is_search method and if it's a search query.
        $is_search = false;
        if ( is_object( $query ) && method_exists( $query, 'is_search' ) ) {
            $is_search = $query->is_search();
        } elseif ( is_search() ) {
            // Fallback to global is_search() function.
            $is_search = true;
        }

        // Only modify search queries.
        if ( ! $is_search ) {
            return $tax_query;
        }

        $filters = $this->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return $tax_query;
        }

        $additional_tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $additional_tax_query ) ) {
            $tax_query = array_merge( $tax_query, $additional_tax_query );
        }

        return $tax_query;
    }

    /**
     * Modify JetSearch query arguments.
     *
     * @param array $args   Query arguments.
     * @param mixed $widget Widget instance.
     * @return array
     */
    public function modify_jet_search_query( $args, $widget = null ) {
        $filters = $this->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return $args;
        }

        $tax_query = $this->build_tax_query( $filters );

        if ( ! empty( $tax_query ) ) {
            if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
                $args['tax_query'] = array_merge(
                    array( 'relation' => 'AND' ),
                    $args['tax_query'],
                    $tax_query
                );
            } else {
                $args['tax_query'] = $tax_query;
            }
        }

        return $args;
    }

    /**
     * Build taxonomy query from active filters.
     *
     * @param array $filters Active filters.
     * @return array
     */
    private function build_tax_query( $filters ) {
        $tax_query = array();

        // Add category filter.
        if ( ! empty( $filters['categories'] ) ) {
            $taxonomy = $this->determine_category_taxonomy();

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $filters['categories'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['categories'],
                'operator' => 'IN',
            );
        }

        // Add tag filter.
        if ( ! empty( $filters['tags'] ) ) {
            $taxonomy = $this->determine_tag_taxonomy();

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $filters['tags'] ) ? 'term_id' : 'slug',
                'terms'    => $filters['tags'],
                'operator' => 'IN',
            );
        }

        // Add custom taxonomy filters.
        if ( ! empty( $filters['taxonomies'] ) ) {
            foreach ( $filters['taxonomies'] as $taxonomy => $terms ) {
                if ( ! empty( $terms ) ) {
                    // Handle JetSmartFilters format.
                    $parsed = $this->parse_jsf_taxonomy( $taxonomy, $terms );

                    if ( $parsed ) {
                        $tax_query[] = array(
                            'taxonomy' => $parsed['taxonomy'],
                            'field'    => $this->is_numeric_array( $parsed['terms'] ) ? 'term_id' : 'slug',
                            'terms'    => $parsed['terms'],
                            'operator' => 'IN',
                        );
                    }
                }
            }
        }

        // Set relation if multiple tax queries.
        if ( count( $tax_query ) > 1 ) {
            array_unshift( $tax_query, array( 'relation' => 'AND' ) );
        }

        return apply_filters( 'jezweb_search_result_tax_query', $tax_query, $filters );
    }

    /**
     * Parse JetSmartFilters taxonomy parameter.
     *
     * @param string $key   Parameter key.
     * @param mixed  $value Parameter value.
     * @return array|false
     */
    private function parse_jsf_taxonomy( $key, $value ) {
        // Handle jsf_product_cat format.
        if ( preg_match( '/^jsf_(.+)$/', $key, $matches ) ) {
            $taxonomy = $matches[1];

            // Check if taxonomy exists.
            if ( taxonomy_exists( $taxonomy ) ) {
                return array(
                    'taxonomy' => $taxonomy,
                    'terms'    => is_array( $value ) ? $value : array( $value ),
                );
            }
        }

        // Handle _tax_query format.
        if ( strpos( $key, '_tax_query' ) !== false ) {
            if ( is_array( $value ) && isset( $value['taxonomy'] ) ) {
                return array(
                    'taxonomy' => $value['taxonomy'],
                    'terms'    => isset( $value['terms'] ) ? (array) $value['terms'] : array(),
                );
            }
        }

        return false;
    }

    /**
     * Determine which category taxonomy to use.
     *
     * @return string
     */
    private function determine_category_taxonomy() {
        // Check if we're in WooCommerce context.
        if ( class_exists( 'WooCommerce' ) ) {
            $is_wc_context = false;

            if ( function_exists( 'is_shop' ) && is_shop() ) {
                $is_wc_context = true;
            } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
                $is_wc_context = true;
            } elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
                $is_wc_context = true;
            } elseif ( $this->is_product_search() ) {
                $is_wc_context = true;
            }

            if ( $is_wc_context ) {
                return 'product_cat';
            }
        }

        return 'category';
    }

    /**
     * Determine which tag taxonomy to use.
     *
     * @return string
     */
    private function determine_tag_taxonomy() {
        // Check if we're in WooCommerce context.
        if ( class_exists( 'WooCommerce' ) ) {
            $is_wc_context = false;

            if ( function_exists( 'is_shop' ) && is_shop() ) {
                $is_wc_context = true;
            } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
                $is_wc_context = true;
            } elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
                $is_wc_context = true;
            } elseif ( $this->is_product_search() ) {
                $is_wc_context = true;
            }

            if ( $is_wc_context ) {
                return 'product_tag';
            }
        }

        return 'post_tag';
    }

    /**
     * Check if current search is for products.
     *
     * @return bool
     */
    private function is_product_search() {
        return isset( $_GET['post_type'] ) && 'product' === sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
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
     * Get active filters from various sources.
     *
     * @return array
     */
    public function get_active_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Get from session (set via JavaScript).
        $session_filters = $this->get_session_filters();
        if ( ! empty( $session_filters ) ) {
            $filters = array_merge_recursive( $filters, $session_filters );
        }

        // Get from URL parameters.
        $url_filters = $this->get_url_filters();
        if ( ! empty( $url_filters ) ) {
            $filters = $this->merge_filters( $filters, $url_filters );
        }

        // Get from POST data (AJAX requests).
        $post_filters = $this->get_post_filters();
        if ( ! empty( $post_filters ) ) {
            $filters = $this->merge_filters( $filters, $post_filters );
        }

        // Get from archive context.
        $archive_filters = $this->get_archive_filters();
        if ( ! empty( $archive_filters ) ) {
            $filters = $this->merge_filters( $filters, $archive_filters );
        }

        // Clean up arrays.
        $filters['categories'] = array_unique( array_filter( $filters['categories'] ) );
        $filters['tags']       = array_unique( array_filter( $filters['tags'] ) );

        return apply_filters( 'jezweb_search_result_get_active_filters', $filters );
    }

    /**
     * Merge filter arrays without duplicates.
     *
     * @param array $filters1 First filter array.
     * @param array $filters2 Second filter array.
     * @return array
     */
    private function merge_filters( $filters1, $filters2 ) {
        $merged = array(
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

        return $merged;
    }

    /**
     * Get filters from session/transient.
     *
     * @return array
     */
    private function get_session_filters() {
        $filters = array();

        // Check for transient (set via AJAX).
        $user_id    = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );
        $stored     = get_transient( $transient_key );

        if ( ! empty( $stored ) && is_array( $stored ) ) {
            $filters = $stored;
        }

        return $filters;
    }

    /**
     * Get filters from URL parameters.
     *
     * @return array
     */
    private function get_url_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Standard WooCommerce/WordPress parameters.
        $category_params = array( 'product_cat', 'category', 'category_name', 'cat' );
        $tag_params      = array( 'product_tag', 'tag', 'post_tag' );

        foreach ( $category_params as $param ) {
            if ( isset( $_GET[ $param ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                $values = strpos( $value, ',' ) !== false ? explode( ',', $value ) : array( $value );
                $filters['categories'] = array_merge( $filters['categories'], array_map( 'trim', $values ) );
            }
        }

        foreach ( $tag_params as $param ) {
            if ( isset( $_GET[ $param ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                $values = strpos( $value, ',' ) !== false ? explode( ',', $value ) : array( $value );
                $filters['tags'] = array_merge( $filters['tags'], array_map( 'trim', $values ) );
            }
        }

        // JetSmartFilters parameters.
        $jsf_prefix = isset( $this->settings['filter_param_prefix'] ) ? $this->settings['filter_param_prefix'] : 'jsf';

        foreach ( $_GET as $key => $value ) {
            $key = sanitize_key( $key );

            // Match JSF format: jsf_taxonomy or _tax_query.
            if ( strpos( $key, $jsf_prefix . '_' ) === 0 || strpos( $key, '_tax_query' ) !== false ) {
                $sanitized_value = is_array( $value )
                    ? array_map( 'sanitize_text_field', $value )
                    : sanitize_text_field( wp_unslash( $value ) );

                $filters['taxonomies'][ $key ] = $sanitized_value;
            }

            // Handle tax-{taxonomy} format.
            if ( strpos( $key, 'tax-' ) === 0 ) {
                $taxonomy = str_replace( 'tax-', '', $key );
                if ( taxonomy_exists( $taxonomy ) ) {
                    $sanitized_value = is_array( $value )
                        ? array_map( 'sanitize_text_field', $value )
                        : sanitize_text_field( wp_unslash( $value ) );

                    $filters['taxonomies'][ $jsf_prefix . '_' . $taxonomy ] = $sanitized_value;
                }
            }
        }

        return $filters;
    }

    /**
     * Get filters from POST data.
     *
     * @return array
     */
    private function get_post_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Check for our custom POST parameters.
        if ( isset( $_POST['jezweb_filters'] ) ) {
            // Verify nonce if present.
            if ( isset( $_POST['jezweb_nonce'] ) ) {
                if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jezweb_nonce'] ) ), 'jezweb_search_result_nonce' ) ) {
                    return $filters;
                }
            }

            $posted_filters = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_POST['jezweb_filters'] ) ) ), true );

            if ( is_array( $posted_filters ) ) {
                if ( isset( $posted_filters['categories'] ) ) {
                    $filters['categories'] = array_map( 'sanitize_text_field', (array) $posted_filters['categories'] );
                }
                if ( isset( $posted_filters['tags'] ) ) {
                    $filters['tags'] = array_map( 'sanitize_text_field', (array) $posted_filters['tags'] );
                }
                if ( isset( $posted_filters['taxonomies'] ) ) {
                    foreach ( (array) $posted_filters['taxonomies'] as $tax => $terms ) {
                        $filters['taxonomies'][ sanitize_key( $tax ) ] = array_map( 'sanitize_text_field', (array) $terms );
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * Get filters from current archive context.
     *
     * @return array
     */
    private function get_archive_filters() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Check if on category archive.
        $is_category_archive = is_category() || ( function_exists( 'is_product_category' ) && is_product_category() );
        if ( $is_category_archive ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
                $filters['categories'][] = $term->slug;
            }
        }

        // Check if on tag archive.
        $is_tag_archive = is_tag() || ( function_exists( 'is_product_tag' ) && is_product_tag() );
        if ( $is_tag_archive ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
                $filters['tags'][] = $term->slug;
            }
        }

        // Check for any taxonomy archive.
        if ( is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) && isset( $term->taxonomy ) && isset( $term->slug ) ) {
                $jsf_prefix = isset( $this->settings['filter_param_prefix'] ) ? $this->settings['filter_param_prefix'] : 'jsf';
                $filters['taxonomies'][ $jsf_prefix . '_' . $term->taxonomy ] = array( $term->slug );
            }
        }

        return $filters;
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
     * Set active filters programmatically.
     *
     * @param array $categories Category slugs or IDs.
     * @param array $tags       Tag slugs or IDs.
     * @param array $taxonomies Additional taxonomy filters.
     */
    public function set_active_filters( $categories = array(), $tags = array(), $taxonomies = array() ) {
        $this->active_categories = $categories;
        $this->active_tags       = $tags;
        $this->active_taxonomies = $taxonomies;

        // Store in transient for persistence.
        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );

        set_transient( $transient_key, array(
            'categories' => $categories,
            'tags'       => $tags,
            'taxonomies' => $taxonomies,
        ), HOUR_IN_SECONDS );
    }

    /**
     * Clear active filters.
     */
    public function clear_active_filters() {
        $this->active_categories = array();
        $this->active_tags       = array();
        $this->active_taxonomies = array();

        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );

        delete_transient( $transient_key );
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route( 'jezweb-search/v1', '/filters', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_set_filters' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'categories' => array(
                        'type'              => 'array',
                        'default'           => array(),
                        'sanitize_callback' => function( $param ) {
                            return array_map( 'sanitize_text_field', (array) $param );
                        },
                    ),
                    'tags' => array(
                        'type'              => 'array',
                        'default'           => array(),
                        'sanitize_callback' => function( $param ) {
                            return array_map( 'sanitize_text_field', (array) $param );
                        },
                    ),
                    'taxonomies' => array(
                        'type'              => 'object',
                        'default'           => array(),
                    ),
                ),
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_filters' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'rest_clear_filters' ),
                'permission_callback' => '__return_true',
            ),
        ) );
    }

    /**
     * REST API callback to set filters.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_set_filters( $request ) {
        $categories = $request->get_param( 'categories' );
        $tags       = $request->get_param( 'tags' );
        $taxonomies = $request->get_param( 'taxonomies' );

        // Sanitize taxonomies.
        $sanitized_taxonomies = array();
        if ( is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $key => $value ) {
                $sanitized_taxonomies[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', (array) $value );
            }
        }

        $this->set_active_filters( $categories, $tags, $sanitized_taxonomies );

        return new WP_REST_Response( array(
            'success' => true,
            'filters' => array(
                'categories' => $categories,
                'tags'       => $tags,
                'taxonomies' => $sanitized_taxonomies,
            ),
        ), 200 );
    }

    /**
     * REST API callback to get filters.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_filters( $request ) {
        return new WP_REST_Response( array(
            'success' => true,
            'filters' => $this->get_active_filters(),
        ), 200 );
    }

    /**
     * REST API callback to clear filters.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_clear_filters( $request ) {
        $this->clear_active_filters();

        return new WP_REST_Response( array(
            'success' => true,
        ), 200 );
    }
}
