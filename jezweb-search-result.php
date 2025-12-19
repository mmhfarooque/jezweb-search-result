<?php
/**
 * Plugin Name: Jezweb Search Result
 * Plugin URI: https://jezweb.com.au/plugins/jezweb-search-result
 * Description: Enhances WordPress search to respect active category and tag filters. Search results are scoped to currently selected categories/tags in product archives, working seamlessly with Elementor, JetSearch, JetSmartFilters, and default WordPress search.
 * Version: 1.0.14
 * Author: Jezweb
 * Author URI: https://jezweb.com.au
 * Developer: Mahmud Farooque
 * Developer URI: https://jezweb.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jezweb-search-result
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.4
 *
 * @package Jezweb_Search_Result
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'JEZWEB_SEARCH_RESULT_VERSION', '1.0.14' );
define( 'JEZWEB_SEARCH_RESULT_PLUGIN_FILE', __FILE__ );
define( 'JEZWEB_SEARCH_RESULT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEZWEB_SEARCH_RESULT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JEZWEB_SEARCH_RESULT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JEZWEB_SEARCH_RESULT_MIN_PHP_VERSION', '7.4' );
define( 'JEZWEB_SEARCH_RESULT_MIN_WP_VERSION', '5.8' );
define( 'JEZWEB_SEARCH_RESULT_GITHUB_USERNAME', 'mmhfarooque' );
define( 'JEZWEB_SEARCH_RESULT_GITHUB_REPO', 'jezweb-search-result' );

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
final class Jezweb_Search_Result {

    /**
     * Plugin instance.
     *
     * @var Jezweb_Search_Result
     */
    private static $instance = null;

    /**
     * Active filters storage.
     *
     * @var array
     */
    public $active_filters = array();

    /**
     * Search filter handler.
     *
     * @var Jezweb_Search_Filter
     */
    public $search_filter;

    /**
     * Get single instance of the plugin.
     *
     * @return Jezweb_Search_Result
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Check requirements before initializing.
        if ( ! $this->check_requirements() ) {
            return;
        }

        $this->includes();
        $this->init_hooks();
    }

    /**
     * Check plugin requirements.
     *
     * @return bool
     */
    private function check_requirements() {
        $errors = array();

        // Check PHP version.
        if ( version_compare( PHP_VERSION, JEZWEB_SEARCH_RESULT_MIN_PHP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                __( 'Jezweb Search Result requires PHP version %1$s or higher. You are running version %2$s.', 'jezweb-search-result' ),
                JEZWEB_SEARCH_RESULT_MIN_PHP_VERSION,
                PHP_VERSION
            );
        }

        // Check WordPress version.
        if ( version_compare( get_bloginfo( 'version' ), JEZWEB_SEARCH_RESULT_MIN_WP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                __( 'Jezweb Search Result requires WordPress version %1$s or higher. You are running version %2$s.', 'jezweb-search-result' ),
                JEZWEB_SEARCH_RESULT_MIN_WP_VERSION,
                get_bloginfo( 'version' )
            );
        }

        if ( ! empty( $errors ) ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                foreach ( $errors as $error ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
                }
            });
            return false;
        }

        return true;
    }

    /**
     * Include required files.
     */
    private function includes() {
        // Core classes.
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/class-jezweb-search-filter.php';
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/class-jezweb-filter-detector.php';
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/class-jezweb-ajax-handler.php';

        // Integration classes.
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/integrations/class-jezweb-elementor-integration.php';
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/integrations/class-jezweb-jetsearch-integration.php';
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/integrations/class-jezweb-jetsmartfilters-integration.php';
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/integrations/class-jezweb-woocommerce-integration.php';

        // Admin classes.
        if ( is_admin() ) {
            require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'admin/class-jezweb-admin-settings.php';
        }

        // GitHub updater for automatic updates.
        require_once JEZWEB_SEARCH_RESULT_PLUGIN_DIR . 'includes/class-jezweb-github-updater.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Load text domain.
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Initialize components.
        add_action( 'init', array( $this, 'init' ), 0 );

        // Enqueue scripts.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Admin scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        // Plugin activation/deactivation.
        register_activation_hook( JEZWEB_SEARCH_RESULT_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( JEZWEB_SEARCH_RESULT_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Add settings link.
        add_filter( 'plugin_action_links_' . JEZWEB_SEARCH_RESULT_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

        // Declare WooCommerce HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

        // Initialize GitHub updater for automatic updates.
        $this->init_updater();
    }

    /**
     * Initialize GitHub updater for automatic plugin updates.
     */
    private function init_updater() {
        if ( ! is_admin() ) {
            return;
        }

        $updater = new Jezweb_GitHub_Updater( JEZWEB_SEARCH_RESULT_PLUGIN_FILE );
        $updater->set_username( JEZWEB_SEARCH_RESULT_GITHUB_USERNAME );
        $updater->set_repository( JEZWEB_SEARCH_RESULT_GITHUB_REPO );
        $updater->initialize();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'jezweb-search-result',
            false,
            dirname( JEZWEB_SEARCH_RESULT_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Initialize plugin components.
     */
    public function init() {
        // Initialize main search filter.
        $this->search_filter = new Jezweb_Search_Filter();

        // Initialize filter detector.
        new Jezweb_Filter_Detector();

        // Initialize AJAX handler.
        new Jezweb_Ajax_Handler();

        // Initialize integrations.
        new Jezweb_Elementor_Integration();
        new Jezweb_JetSearch_Integration();
        new Jezweb_JetSmartFilters_Integration();
        new Jezweb_WooCommerce_Integration();

        // Admin settings.
        if ( is_admin() ) {
            new Jezweb_Admin_Settings();
        }

        /**
         * Fires after the plugin is fully initialized.
         *
         * @since 1.0.0
         */
        do_action( 'jezweb_search_result_init' );
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        // Only load on pages where it's needed.
        if ( ! $this->should_load_assets() ) {
            return;
        }

        // Main JavaScript file.
        wp_enqueue_script(
            'jezweb-search-result',
            JEZWEB_SEARCH_RESULT_PLUGIN_URL . 'assets/js/jezweb-search-result.js',
            array( 'jquery' ),
            JEZWEB_SEARCH_RESULT_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'jezweb-search-result',
            'jezwebSearchResult',
            array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'restUrl'         => rest_url( 'jezweb-search/v1/' ),
                'nonce'           => wp_create_nonce( 'jezweb_search_result_nonce' ),
                'restNonce'       => wp_create_nonce( 'wp_rest' ),
                'settings'        => $this->get_frontend_settings(),
                'activeFilters'   => $this->get_active_filters_from_url(),
                'debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'i18n'            => array(
                    'searching' => __( 'Searching...', 'jezweb-search-result' ),
                    'noResults' => __( 'No results found within selected filters.', 'jezweb-search-result' ),
                ),
            )
        );

        // Frontend CSS.
        wp_enqueue_style(
            'jezweb-search-result',
            JEZWEB_SEARCH_RESULT_PLUGIN_URL . 'assets/css/jezweb-search-result.css',
            array(),
            JEZWEB_SEARCH_RESULT_VERSION
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function admin_enqueue_scripts( $hook ) {
        // Only load on our settings page.
        if ( 'settings_page_jezweb-search-result' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'jezweb-search-result-admin',
            JEZWEB_SEARCH_RESULT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            JEZWEB_SEARCH_RESULT_VERSION
        );

        wp_enqueue_script(
            'jezweb-search-result-admin',
            JEZWEB_SEARCH_RESULT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            JEZWEB_SEARCH_RESULT_VERSION,
            true
        );
    }

    /**
     * Check if assets should be loaded on current page.
     *
     * @return bool
     */
    private function should_load_assets() {
        $settings = get_option( 'jezweb_search_result_settings', array() );
        $load_globally = isset( $settings['load_globally'] ) ? $settings['load_globally'] : true;

        if ( $load_globally ) {
            return true;
        }

        // Check if on product archive, shop page, or search page.
        if ( function_exists( 'is_shop' ) && is_shop() ) {
            return true;
        }

        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            return true;
        }

        if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
            return true;
        }

        if ( is_search() ) {
            return true;
        }

        // Check if on archive pages.
        if ( is_archive() || is_category() || is_tag() ) {
            return true;
        }

        return apply_filters( 'jezweb_search_result_should_load_assets', false );
    }

    /**
     * Get frontend settings.
     *
     * @return array
     */
    private function get_frontend_settings() {
        $settings = get_option( 'jezweb_search_result_settings', array() );

        return array(
            'enabledTaxonomies'  => isset( $settings['enabled_taxonomies'] ) ? $settings['enabled_taxonomies'] : array( 'product_cat', 'product_tag', 'category', 'post_tag' ),
            'detectFiltersFrom'  => isset( $settings['detect_filters_from'] ) ? $settings['detect_filters_from'] : array( 'url', 'checkbox', 'jetfilters' ),
            'enableElementor'    => isset( $settings['enable_elementor'] ) ? $settings['enable_elementor'] : true,
            'enableJetSearch'    => isset( $settings['enable_jetsearch'] ) ? $settings['enable_jetsearch'] : true,
            'enableJetFilters'   => isset( $settings['enable_jetfilters'] ) ? $settings['enable_jetfilters'] : true,
            'enableWoocommerce'  => isset( $settings['enable_woocommerce'] ) ? $settings['enable_woocommerce'] : true,
            'filterParamPrefix'  => isset( $settings['filter_param_prefix'] ) ? $settings['filter_param_prefix'] : 'jsf',
        );
    }

    /**
     * Get active filters from URL parameters.
     *
     * @return array
     */
    public function get_active_filters_from_url() {
        $filters = array(
            'categories' => array(),
            'tags'       => array(),
            'taxonomies' => array(),
        );

        // Check for product_cat in URL.
        if ( isset( $_GET['product_cat'] ) ) {
            $filters['categories'] = array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['product_cat'] ) );
        }

        // Check for product_tag in URL.
        if ( isset( $_GET['product_tag'] ) ) {
            $filters['tags'] = array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['product_tag'] ) );
        }

        // Check for JetSmartFilters parameters.
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'jsf' ) === 0 || strpos( $key, '_tax_query' ) !== false ) {
                $filters['taxonomies'][ sanitize_key( $key ) ] = is_array( $value )
                    ? array_map( 'sanitize_text_field', $value )
                    : sanitize_text_field( wp_unslash( $value ) );
            }
        }

        // Check for category archives (WooCommerce).
        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
                $filters['categories'][] = $term->slug;
            }
        }

        // Check for tag archives (WooCommerce).
        if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
                $filters['tags'][] = $term->slug;
            }
        }

        return apply_filters( 'jezweb_search_result_active_filters', $filters );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Set default options.
        $default_settings = array(
            'enabled'             => true,
            'load_globally'       => false,
            'search_scope'        => 'title_only',
            'enabled_taxonomies'  => array( 'product_cat', 'product_tag', 'category', 'post_tag' ),
            'detect_filters_from' => array( 'url', 'checkbox', 'jetfilters' ),
            'enable_elementor'    => true,
            'enable_jetsearch'    => true,
            'enable_jetfilters'   => true,
            'enable_woocommerce'  => true,
            'filter_param_prefix' => 'jsf',
        );

        if ( ! get_option( 'jezweb_search_result_settings' ) ) {
            add_option( 'jezweb_search_result_settings', $default_settings );
        }

        // Flush rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires on plugin activation.
         *
         * @since 1.0.0
         */
        do_action( 'jezweb_search_result_activated' );
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires on plugin deactivation.
         *
         * @since 1.0.0
         */
        do_action( 'jezweb_search_result_deactivated' );
    }

    /**
     * Add settings link to plugins page.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=jezweb-search-result' ),
            __( 'Settings', 'jezweb-search-result' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Declare WooCommerce HPOS compatibility.
     */
    public function declare_wc_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', JEZWEB_SEARCH_RESULT_PLUGIN_FILE, true );
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton.' );
    }
}

/**
 * Get the main plugin instance.
 *
 * @return Jezweb_Search_Result
 */
function jezweb_search_result() {
    return Jezweb_Search_Result::get_instance();
}

// Initialize the plugin.
jezweb_search_result();
