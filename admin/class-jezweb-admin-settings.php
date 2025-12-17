<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_Admin_Settings class.
 */
class Jezweb_Admin_Settings {

    /**
     * Settings option name.
     *
     * @var string
     */
    private $option_name = 'jezweb_search_result_settings';

    /**
     * Settings page slug.
     *
     * @var string
     */
    private $page_slug = 'jezweb-search-result';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings page to admin menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Jezweb Search Result Settings', 'jezweb-search-result' ),
            __( 'Jezweb Search Result', 'jezweb-search-result' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'jezweb_search_result_settings_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        // General Settings Section
        add_settings_section(
            'jezweb_general_section',
            __( 'General Settings', 'jezweb-search-result' ),
            array( $this, 'render_general_section' ),
            $this->page_slug
        );

        add_settings_field(
            'enabled',
            __( 'Enable Plugin', 'jezweb-search-result' ),
            array( $this, 'render_checkbox_field' ),
            $this->page_slug,
            'jezweb_general_section',
            array(
                'id'          => 'enabled',
                'description' => __( 'Enable or disable the search filter functionality.', 'jezweb-search-result' ),
            )
        );

        add_settings_field(
            'load_globally',
            __( 'Load Assets Globally', 'jezweb-search-result' ),
            array( $this, 'render_checkbox_field' ),
            $this->page_slug,
            'jezweb_general_section',
            array(
                'id'          => 'load_globally',
                'description' => __( 'Load JavaScript and CSS on all pages. If disabled, assets only load on shop, archive, and search pages.', 'jezweb-search-result' ),
            )
        );

        // Taxonomies Section
        add_settings_section(
            'jezweb_taxonomies_section',
            __( 'Taxonomy Settings', 'jezweb-search-result' ),
            array( $this, 'render_taxonomies_section' ),
            $this->page_slug
        );

        add_settings_field(
            'enabled_taxonomies',
            __( 'Enabled Taxonomies', 'jezweb-search-result' ),
            array( $this, 'render_taxonomies_field' ),
            $this->page_slug,
            'jezweb_taxonomies_section'
        );

        add_settings_field(
            'filter_param_prefix',
            __( 'Filter Parameter Prefix', 'jezweb-search-result' ),
            array( $this, 'render_text_field' ),
            $this->page_slug,
            'jezweb_taxonomies_section',
            array(
                'id'          => 'filter_param_prefix',
                'description' => __( 'URL parameter prefix used by JetSmartFilters (default: jsf).', 'jezweb-search-result' ),
                'default'     => 'jsf',
            )
        );

        // Integrations Section
        add_settings_section(
            'jezweb_integrations_section',
            __( 'Integrations', 'jezweb-search-result' ),
            array( $this, 'render_integrations_section' ),
            $this->page_slug
        );

        add_settings_field(
            'enable_elementor',
            __( 'Elementor Integration', 'jezweb-search-result' ),
            array( $this, 'render_integration_checkbox' ),
            $this->page_slug,
            'jezweb_integrations_section',
            array(
                'id'          => 'enable_elementor',
                'description' => __( 'Enable integration with Elementor search widgets.', 'jezweb-search-result' ),
                'plugin'      => 'Elementor',
                'active'      => defined( 'ELEMENTOR_VERSION' ),
            )
        );

        add_settings_field(
            'enable_jetsearch',
            __( 'JetSearch Integration', 'jezweb-search-result' ),
            array( $this, 'render_integration_checkbox' ),
            $this->page_slug,
            'jezweb_integrations_section',
            array(
                'id'          => 'enable_jetsearch',
                'description' => __( 'Enable integration with Crocoblock JetSearch.', 'jezweb-search-result' ),
                'plugin'      => 'JetSearch',
                'active'      => defined( 'JET_SEARCH_VERSION' ),
            )
        );

        add_settings_field(
            'enable_jetfilters',
            __( 'JetSmartFilters Integration', 'jezweb-search-result' ),
            array( $this, 'render_integration_checkbox' ),
            $this->page_slug,
            'jezweb_integrations_section',
            array(
                'id'          => 'enable_jetfilters',
                'description' => __( 'Enable integration with Crocoblock JetSmartFilters.', 'jezweb-search-result' ),
                'plugin'      => 'JetSmartFilters',
                'active'      => defined( 'JET_SMART_FILTERS_VERSION' ),
            )
        );

        add_settings_field(
            'enable_woocommerce',
            __( 'WooCommerce Integration', 'jezweb-search-result' ),
            array( $this, 'render_integration_checkbox' ),
            $this->page_slug,
            'jezweb_integrations_section',
            array(
                'id'          => 'enable_woocommerce',
                'description' => __( 'Enable integration with WooCommerce product search.', 'jezweb-search-result' ),
                'plugin'      => 'WooCommerce',
                'active'      => class_exists( 'WooCommerce' ),
            )
        );

        // Detection Settings Section
        add_settings_section(
            'jezweb_detection_section',
            __( 'Filter Detection', 'jezweb-search-result' ),
            array( $this, 'render_detection_section' ),
            $this->page_slug
        );

        add_settings_field(
            'detect_filters_from',
            __( 'Detect Filters From', 'jezweb-search-result' ),
            array( $this, 'render_detection_sources_field' ),
            $this->page_slug,
            'jezweb_detection_section'
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input data.
     * @return array Sanitized data.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Boolean fields
        $sanitized['enabled']            = isset( $input['enabled'] ) ? true : false;
        $sanitized['load_globally']      = isset( $input['load_globally'] ) ? true : false;
        $sanitized['enable_elementor']   = isset( $input['enable_elementor'] ) ? true : false;
        $sanitized['enable_jetsearch']   = isset( $input['enable_jetsearch'] ) ? true : false;
        $sanitized['enable_jetfilters']  = isset( $input['enable_jetfilters'] ) ? true : false;
        $sanitized['enable_woocommerce'] = isset( $input['enable_woocommerce'] ) ? true : false;

        // Text fields
        $sanitized['filter_param_prefix'] = isset( $input['filter_param_prefix'] )
            ? sanitize_key( $input['filter_param_prefix'] )
            : 'jsf';

        // Array fields
        $sanitized['enabled_taxonomies'] = isset( $input['enabled_taxonomies'] ) && is_array( $input['enabled_taxonomies'] )
            ? array_map( 'sanitize_key', $input['enabled_taxonomies'] )
            : array( 'product_cat', 'product_tag', 'category', 'post_tag' );

        $sanitized['detect_filters_from'] = isset( $input['detect_filters_from'] ) && is_array( $input['detect_filters_from'] )
            ? array_map( 'sanitize_key', $input['detect_filters_from'] )
            : array( 'url', 'checkbox', 'jetfilters' );

        return $sanitized;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Show success message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                'jezweb_search_result_messages',
                'jezweb_search_result_message',
                __( 'Settings saved successfully.', 'jezweb-search-result' ),
                'updated'
            );
        }
        ?>
        <div class="wrap jezweb-search-result-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="jezweb-settings-header">
                <div class="jezweb-logo">
                    <h2><?php esc_html_e( 'Jezweb Search Result', 'jezweb-search-result' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Enhance WordPress search to respect active category and tag filters on your product archives.', 'jezweb-search-result' ); ?>
                    </p>
                </div>
                <div class="jezweb-info">
                    <p>
                        <strong><?php esc_html_e( 'Version:', 'jezweb-search-result' ); ?></strong>
                        <?php echo esc_html( JEZWEB_SEARCH_RESULT_VERSION ); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Developer:', 'jezweb-search-result' ); ?></strong>
                        Mahmud Farooque
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Author:', 'jezweb-search-result' ); ?></strong>
                        <a href="https://jezweb.com.au" target="_blank">Jezweb</a>
                    </p>
                </div>
            </div>

            <?php settings_errors( 'jezweb_search_result_messages' ); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'jezweb_search_result_settings_group' );
                do_settings_sections( $this->page_slug );
                submit_button( __( 'Save Settings', 'jezweb-search-result' ) );
                ?>
            </form>

            <div class="jezweb-settings-footer">
                <h3><?php esc_html_e( 'How It Works', 'jezweb-search-result' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'When a user selects a category or tag filter on your product archive (via checkboxes, dropdowns, or JetSmartFilters), the plugin detects this selection.', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'When the user performs a search, the plugin automatically limits search results to products within the selected categories/tags.', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'If multiple categories/tags are selected, results will include products from any of those selections (OR logic within same taxonomy, AND logic between different taxonomies).', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'If no filters are selected, search works normally across all products.', 'jezweb-search-result' ); ?></li>
                </ol>

                <h3><?php esc_html_e( 'Supported Search Widgets', 'jezweb-search-result' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'WordPress Default Search Widget', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'Elementor Search Form Widget', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'Crocoblock JetSearch Widget', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'WooCommerce Product Search Widget', 'jezweb-search-result' ); ?></li>
                </ul>

                <h3><?php esc_html_e( 'Supported Filter Plugins', 'jezweb-search-result' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'Crocoblock JetSmartFilters', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'WooCommerce Layered Nav Widget', 'jezweb-search-result' ); ?></li>
                    <li><?php esc_html_e( 'Any checkbox/radio/select based filter', 'jezweb-search-result' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render general section description.
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure the general settings for Jezweb Search Result.', 'jezweb-search-result' ) . '</p>';
    }

    /**
     * Render taxonomies section description.
     */
    public function render_taxonomies_section() {
        echo '<p>' . esc_html__( 'Select which taxonomies should be used for filtering search results.', 'jezweb-search-result' ) . '</p>';
    }

    /**
     * Render integrations section description.
     */
    public function render_integrations_section() {
        echo '<p>' . esc_html__( 'Enable or disable integrations with specific plugins.', 'jezweb-search-result' ) . '</p>';
    }

    /**
     * Render detection section description.
     */
    public function render_detection_section() {
        echo '<p>' . esc_html__( 'Configure how the plugin detects active filters.', 'jezweb-search-result' ) . '</p>';
    }

    /**
     * Render checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : true;
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( $this->option_name . '[' . $args['id'] . ']' ); ?>"
                   value="1"
                   <?php checked( $value, true ); ?>>
            <?php echo esc_html( $args['description'] ); ?>
        </label>
        <?php
    }

    /**
     * Render text field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );
        ?>
        <input type="text"
               name="<?php echo esc_attr( $this->option_name . '[' . $args['id'] . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render taxonomies multi-select field.
     */
    public function render_taxonomies_field() {
        $options  = get_option( $this->option_name, array() );
        $selected = isset( $options['enabled_taxonomies'] )
            ? $options['enabled_taxonomies']
            : array( 'product_cat', 'product_tag', 'category', 'post_tag' );

        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        ?>
        <fieldset>
            <?php foreach ( $taxonomies as $taxonomy ) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox"
                           name="<?php echo esc_attr( $this->option_name . '[enabled_taxonomies][]' ); ?>"
                           value="<?php echo esc_attr( $taxonomy->name ); ?>"
                           <?php checked( in_array( $taxonomy->name, $selected, true ) ); ?>>
                    <?php echo esc_html( $taxonomy->label ); ?>
                    <code>(<?php echo esc_html( $taxonomy->name ); ?>)</code>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'Select the taxonomies that should be used for filtering search results.', 'jezweb-search-result' ); ?>
        </p>
        <?php
    }

    /**
     * Render integration checkbox with plugin status.
     *
     * @param array $args Field arguments.
     */
    public function render_integration_checkbox( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : true;
        $active  = $args['active'];
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( $this->option_name . '[' . $args['id'] . ']' ); ?>"
                   value="1"
                   <?php checked( $value, true ); ?>
                   <?php disabled( ! $active ); ?>>
            <?php echo esc_html( $args['description'] ); ?>
        </label>
        <?php if ( $active ) : ?>
            <span class="jezweb-plugin-status jezweb-plugin-active">
                <?php esc_html_e( 'Plugin Active', 'jezweb-search-result' ); ?>
            </span>
        <?php else : ?>
            <span class="jezweb-plugin-status jezweb-plugin-inactive">
                <?php
                printf(
                    /* translators: %s: plugin name */
                    esc_html__( '%s Not Detected', 'jezweb-search-result' ),
                    esc_html( $args['plugin'] )
                );
                ?>
            </span>
        <?php endif; ?>
        <?php
    }

    /**
     * Render detection sources field.
     */
    public function render_detection_sources_field() {
        $options  = get_option( $this->option_name, array() );
        $selected = isset( $options['detect_filters_from'] )
            ? $options['detect_filters_from']
            : array( 'url', 'checkbox', 'jetfilters' );

        $sources = array(
            'url'        => __( 'URL Parameters - Detect filters from URL query parameters', 'jezweb-search-result' ),
            'checkbox'   => __( 'Checkbox/Radio/Select - Detect from checked checkboxes, selected radio buttons, and dropdowns', 'jezweb-search-result' ),
            'jetfilters' => __( 'JetSmartFilters - Detect from JetSmartFilters state', 'jezweb-search-result' ),
            'archive'    => __( 'Archive Context - Detect from current category/tag archive page', 'jezweb-search-result' ),
        );
        ?>
        <fieldset>
            <?php foreach ( $sources as $key => $label ) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox"
                           name="<?php echo esc_attr( $this->option_name . '[detect_filters_from][]' ); ?>"
                           value="<?php echo esc_attr( $key ); ?>"
                           <?php checked( in_array( $key, $selected, true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'Select the sources from which the plugin should detect active filters.', 'jezweb-search-result' ); ?>
        </p>
        <?php
    }
}
