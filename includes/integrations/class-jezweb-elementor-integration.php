<?php
/**
 * Elementor Integration Class
 *
 * Handles integration with Elementor search widget and forms.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_Elementor_Integration class.
 */
class Jezweb_Elementor_Integration {

    /**
     * Constructor.
     */
    public function __construct() {
        // Check if Elementor is active.
        if ( ! $this->is_elementor_active() ) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if Elementor is active.
     *
     * @return bool
     */
    private function is_elementor_active() {
        return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
    }

    /**
     * Check if integration is enabled.
     *
     * @return bool
     */
    private function is_enabled() {
        $settings = get_option( 'jezweb_search_result_settings', array() );
        return isset( $settings['enable_elementor'] ) ? $settings['enable_elementor'] : true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Modify Elementor search form output.
        add_filter( 'elementor/widget/render_content', array( $this, 'modify_search_widget' ), 10, 2 );

        // Hook into Elementor AJAX search if available.
        add_action( 'elementor/ajax/register_actions', array( $this, 'register_ajax_actions' ) );

        // Add hidden fields to search forms.
        add_action( 'wp_footer', array( $this, 'add_elementor_search_enhancements' ), 20 );

        // Modify search results in Elementor Pro Archive widget.
        add_action( 'elementor/query/jezweb_filtered_search', array( $this, 'modify_elementor_query' ) );
    }

    /**
     * Modify Elementor search widget output.
     *
     * @param string $content Widget content.
     * @param object $widget  Widget instance.
     * @return string
     */
    public function modify_search_widget( $content, $widget ) {
        if ( ! $widget ) {
            return $content;
        }

        $widget_name = $widget->get_name();

        // Check if this is a search widget.
        $search_widgets = array( 'search-form', 'search', 'woocommerce-product-search' );

        if ( ! in_array( $widget_name, $search_widgets, true ) ) {
            return $content;
        }

        // Add data attribute to identify our enhanced forms.
        $content = str_replace(
            'class="elementor-search-form',
            'class="elementor-search-form jezweb-enhanced-search',
            $content
        );

        return $content;
    }

    /**
     * Register Elementor AJAX actions.
     *
     * @param \Elementor\Core\Common\Modules\Ajax\Module $ajax_manager Ajax manager.
     */
    public function register_ajax_actions( $ajax_manager ) {
        $ajax_manager->register_ajax_action( 'jezweb_elementor_search', array( $this, 'handle_ajax_search' ) );
    }

    /**
     * Handle Elementor AJAX search.
     *
     * @param array $data Request data.
     * @return array
     */
    public function handle_ajax_search( $data ) {
        $search_query = isset( $data['query'] ) ? sanitize_text_field( $data['query'] ) : '';
        $filters      = isset( $data['filters'] ) ? $data['filters'] : array();

        // Build search args.
        $args = array(
            's'              => $search_query,
            'post_type'      => isset( $data['post_type'] ) ? sanitize_text_field( $data['post_type'] ) : 'any',
            'posts_per_page' => isset( $data['limit'] ) ? absint( $data['limit'] ) : 10,
            'post_status'    => 'publish',
        );

        // Apply filters.
        if ( ! empty( $filters ) ) {
            $tax_query = $this->build_tax_query_from_filters( $filters );
            if ( ! empty( $tax_query ) ) {
                $args['tax_query'] = $tax_query;
            }
        }

        $query   = new WP_Query( $args );
        $results = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = array(
                    'id'        => get_the_ID(),
                    'title'     => get_the_title(),
                    'permalink' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
                );
            }
            wp_reset_postdata();
        }

        return array(
            'results' => $results,
            'total'   => $query->found_posts,
        );
    }

    /**
     * Build tax query from filters.
     *
     * @param array $filters Filter data.
     * @return array
     */
    private function build_tax_query_from_filters( $filters ) {
        $tax_query = array();

        if ( ! empty( $filters['categories'] ) ) {
            $categories = array_map( 'sanitize_text_field', (array) $filters['categories'] );
            $tax_query[] = array(
                'taxonomy' => class_exists( 'WooCommerce' ) ? 'product_cat' : 'category',
                'field'    => 'slug',
                'terms'    => $categories,
                'operator' => 'IN',
            );
        }

        if ( ! empty( $filters['tags'] ) ) {
            $tags = array_map( 'sanitize_text_field', (array) $filters['tags'] );
            $tax_query[] = array(
                'taxonomy' => class_exists( 'WooCommerce' ) ? 'product_tag' : 'post_tag',
                'field'    => 'slug',
                'terms'    => $tags,
                'operator' => 'IN',
            );
        }

        if ( ! empty( $filters['taxonomies'] ) ) {
            foreach ( (array) $filters['taxonomies'] as $taxonomy => $terms ) {
                $clean_taxonomy = preg_replace( '/^jsf_/', '', sanitize_key( $taxonomy ) );
                if ( taxonomy_exists( $clean_taxonomy ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $clean_taxonomy,
                        'field'    => 'slug',
                        'terms'    => array_map( 'sanitize_text_field', (array) $terms ),
                        'operator' => 'IN',
                    );
                }
            }
        }

        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        return $tax_query;
    }

    /**
     * Add JavaScript enhancements for Elementor search forms.
     */
    public function add_elementor_search_enhancements() {
        if ( ! $this->is_elementor_active() ) {
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
             * Enhance Elementor search forms
             */
            function enhanceElementorSearch() {
                // Target Elementor search forms
                var searchForms = $(
                    '.elementor-search-form form, ' +
                    '.elementor-widget-search-form form, ' +
                    '.elementor-widget-woocommerce-product-search form'
                );

                searchForms.each(function() {
                    var $form = $(this);

                    // Skip if already enhanced
                    if ($form.data('jezweb-enhanced')) {
                        return;
                    }

                    $form.data('jezweb-enhanced', true);

                    // Add hidden fields for filters
                    addFilterFields($form);

                    // Intercept form submission
                    $form.on('submit', function(e) {
                        updateFilterFields($form);
                    });
                });
            }

            /**
             * Add hidden filter fields to form
             */
            function addFilterFields($form) {
                // Remove existing fields first
                $form.find('input[name="jezweb_filters"]').remove();

                // Add hidden field for filters
                $form.append('<input type="hidden" name="jezweb_filters" value="">');
            }

            /**
             * Update filter fields before submission
             */
            function updateFilterFields($form) {
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

                // Update hidden field
                $form.find('input[name="jezweb_filters"]').val(JSON.stringify(filters));

                // Also add individual parameters for standard WordPress handling
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

                // Add taxonomy parameters
                for (var taxonomy in filters.taxonomies) {
                    if (filters.taxonomies.hasOwnProperty(taxonomy)) {
                        var taxInput = $form.find('input[name="' + taxonomy + '"]');
                        if (taxInput.length === 0) {
                            $form.append('<input type="hidden" name="' + taxonomy + '" value="' + filters.taxonomies[taxonomy].join(',') + '">');
                        } else {
                            taxInput.val(filters.taxonomies[taxonomy].join(','));
                        }
                    }
                }
            }

            // Initialize on document ready
            $(document).ready(enhanceElementorSearch);

            // Re-initialize after AJAX (for dynamically loaded content)
            $(document).ajaxComplete(function() {
                setTimeout(enhanceElementorSearch, 100);
            });

            // Re-initialize when Elementor frontend is ready
            $(window).on('elementor/frontend/init', function() {
                if (typeof elementorFrontend !== 'undefined') {
                    elementorFrontend.hooks.addAction('frontend/element_ready/search-form.default', enhanceElementorSearch);
                    elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-product-search.default', enhanceElementorSearch);
                }
            });

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Modify Elementor archive query.
     *
     * @param WP_Query $query Query object.
     */
    public function modify_elementor_query( $query ) {
        // Get active filters.
        $search_filter = jezweb_search_result()->search_filter;

        if ( ! $search_filter ) {
            return;
        }

        $filters = $search_filter->get_active_filters();

        if ( empty( $filters['categories'] ) && empty( $filters['tags'] ) && empty( $filters['taxonomies'] ) ) {
            return;
        }

        // Apply filters to query.
        $tax_query = $this->build_tax_query_from_filters( $filters );

        if ( ! empty( $tax_query ) ) {
            $existing_tax_query = $query->get( 'tax_query' );

            if ( ! empty( $existing_tax_query ) ) {
                $tax_query = array_merge( array( 'relation' => 'AND' ), $existing_tax_query, $tax_query );
            }

            $query->set( 'tax_query', $tax_query );
        }
    }
}
