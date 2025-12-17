<?php
/**
 * AJAX Handler Class
 *
 * Handles AJAX requests for filter synchronization and search modifications.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_Ajax_Handler class.
 */
class Jezweb_Ajax_Handler {

    /**
     * Constructor.
     */
    public function __construct() {
        // AJAX actions for logged-in users.
        add_action( 'wp_ajax_jezweb_set_filters', array( $this, 'ajax_set_filters' ) );
        add_action( 'wp_ajax_jezweb_get_filters', array( $this, 'ajax_get_filters' ) );
        add_action( 'wp_ajax_jezweb_clear_filters', array( $this, 'ajax_clear_filters' ) );
        add_action( 'wp_ajax_jezweb_search', array( $this, 'ajax_search' ) );

        // AJAX actions for non-logged-in users.
        add_action( 'wp_ajax_nopriv_jezweb_set_filters', array( $this, 'ajax_set_filters' ) );
        add_action( 'wp_ajax_nopriv_jezweb_get_filters', array( $this, 'ajax_get_filters' ) );
        add_action( 'wp_ajax_nopriv_jezweb_clear_filters', array( $this, 'ajax_clear_filters' ) );
        add_action( 'wp_ajax_nopriv_jezweb_search', array( $this, 'ajax_search' ) );
    }

    /**
     * AJAX handler to set active filters.
     */
    public function ajax_set_filters() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'jezweb_search_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'jezweb-search-result' ),
            ) );
        }

        $categories = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) $_POST['categories'] ) : array();
        $tags       = isset( $_POST['tags'] ) ? array_map( 'sanitize_text_field', (array) $_POST['tags'] ) : array();
        $taxonomies = array();

        if ( isset( $_POST['taxonomies'] ) && is_array( $_POST['taxonomies'] ) ) {
            foreach ( $_POST['taxonomies'] as $key => $value ) {
                $taxonomies[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', (array) $value );
            }
        }

        // Store in transient.
        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );

        $filters = array(
            'categories' => array_filter( $categories ),
            'tags'       => array_filter( $tags ),
            'taxonomies' => array_filter( $taxonomies ),
        );

        set_transient( $transient_key, $filters, HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'message' => __( 'Filters updated.', 'jezweb-search-result' ),
            'filters' => $filters,
        ) );
    }

    /**
     * AJAX handler to get active filters.
     */
    public function ajax_get_filters() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'jezweb_search_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'jezweb-search-result' ),
            ) );
        }

        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );
        $filters       = get_transient( $transient_key );

        if ( ! $filters ) {
            $filters = array(
                'categories' => array(),
                'tags'       => array(),
                'taxonomies' => array(),
            );
        }

        wp_send_json_success( array(
            'filters' => $filters,
        ) );
    }

    /**
     * AJAX handler to clear filters.
     */
    public function ajax_clear_filters() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'jezweb_search_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'jezweb-search-result' ),
            ) );
        }

        $user_id       = get_current_user_id();
        $transient_key = 'jezweb_search_filters_' . ( $user_id ? $user_id : md5( $this->get_client_ip() ) );

        delete_transient( $transient_key );

        wp_send_json_success( array(
            'message' => __( 'Filters cleared.', 'jezweb-search-result' ),
        ) );
    }

    /**
     * AJAX handler for performing filtered search.
     */
    public function ajax_search() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'jezweb_search_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'jezweb-search-result' ),
            ) );
        }

        $search_query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        $post_type    = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'any';
        $per_page     = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;

        // Get filters.
        $categories = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) $_POST['categories'] ) : array();
        $tags       = isset( $_POST['tags'] ) ? array_map( 'sanitize_text_field', (array) $_POST['tags'] ) : array();
        $taxonomies = array();

        if ( isset( $_POST['taxonomies'] ) && is_array( $_POST['taxonomies'] ) ) {
            foreach ( $_POST['taxonomies'] as $key => $value ) {
                $taxonomies[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', (array) $value );
            }
        }

        // Build query args.
        $args = array(
            's'              => $search_query,
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
        );

        // Build tax query.
        $tax_query = array();

        if ( ! empty( $categories ) ) {
            $taxonomy = ( 'product' === $post_type || class_exists( 'WooCommerce' ) ) ? 'product_cat' : 'category';
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $categories ) ? 'term_id' : 'slug',
                'terms'    => $categories,
                'operator' => 'IN',
            );
        }

        if ( ! empty( $tags ) ) {
            $taxonomy = ( 'product' === $post_type || class_exists( 'WooCommerce' ) ) ? 'product_tag' : 'post_tag';
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $this->is_numeric_array( $tags ) ? 'term_id' : 'slug',
                'terms'    => $tags,
                'operator' => 'IN',
            );
        }

        if ( ! empty( $taxonomies ) ) {
            foreach ( $taxonomies as $key => $terms ) {
                $taxonomy_name = preg_replace( '/^jsf_/', '', $key );
                if ( taxonomy_exists( $taxonomy_name ) && ! empty( $terms ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field'    => $this->is_numeric_array( $terms ) ? 'term_id' : 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }

        if ( ! empty( $tax_query ) ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        // Apply filters.
        $args = apply_filters( 'jezweb_search_result_ajax_query_args', $args );

        // Run query.
        $query = new WP_Query( $args );

        $results = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();

                $result = array(
                    'id'        => get_the_ID(),
                    'title'     => get_the_title(),
                    'permalink' => get_permalink(),
                    'excerpt'   => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
                );

                // Add WooCommerce product data if applicable.
                if ( 'product' === get_post_type() && function_exists( 'wc_get_product' ) ) {
                    $product = wc_get_product( get_the_ID() );
                    if ( $product ) {
                        $result['price']         = $product->get_price_html();
                        $result['regular_price'] = $product->get_regular_price();
                        $result['sale_price']    = $product->get_sale_price();
                        $result['on_sale']       = $product->is_on_sale();
                        $result['in_stock']      = $product->is_in_stock();
                    }
                }

                $results[] = apply_filters( 'jezweb_search_result_ajax_result_item', $result, get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( array(
            'results'     => $results,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'query'       => $search_query,
            'filters'     => array(
                'categories' => $categories,
                'tags'       => $tags,
                'taxonomies' => $taxonomies,
            ),
        ) );
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
}
