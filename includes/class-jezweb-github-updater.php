<?php
/**
 * GitHub Updater Class
 *
 * Handles automatic plugin updates from GitHub releases.
 *
 * @package Jezweb_Search_Result
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Jezweb_GitHub_Updater class.
 */
class Jezweb_GitHub_Updater {

    /**
     * Plugin file path.
     *
     * @var string
     */
    private $file;

    /**
     * Plugin data.
     *
     * @var array
     */
    private $plugin;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $basename;

    /**
     * GitHub username.
     *
     * @var string
     */
    private $github_username;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $github_repo;

    /**
     * GitHub API response.
     *
     * @var object
     */
    private $github_response;

    /**
     * Authorization token for private repos.
     *
     * @var string
     */
    private $authorize_token;

    /**
     * Constructor.
     *
     * @param string $file Plugin file path.
     */
    public function __construct( $file ) {
        $this->file = $file;

        add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );

        return $this;
    }

    /**
     * Set plugin properties.
     */
    public function set_plugin_properties() {
        $this->plugin   = get_plugin_data( $this->file );
        $this->basename = plugin_basename( $this->file );
    }

    /**
     * Set GitHub username.
     *
     * @param string $username GitHub username.
     * @return $this
     */
    public function set_username( $username ) {
        $this->github_username = $username;
        return $this;
    }

    /**
     * Set GitHub repository.
     *
     * @param string $repo Repository name.
     * @return $this
     */
    public function set_repository( $repo ) {
        $this->github_repo = $repo;
        return $this;
    }

    /**
     * Set authorization token for private repos.
     *
     * @param string $token GitHub personal access token.
     * @return $this
     */
    public function authorize( $token ) {
        $this->authorize_token = $token;
        return $this;
    }

    /**
     * Initialize the updater.
     */
    public function initialize() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // Add "Check for updates" link on plugins page.
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }

    /**
     * Get repository info from GitHub.
     *
     * @return object|false
     */
    private function get_repository_info() {
        if ( ! empty( $this->github_response ) ) {
            return $this->github_response;
        }

        // Build API URL.
        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        // Set up request args.
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );

        // Add authorization token if set.
        if ( $this->authorize_token ) {
            $args['headers']['Authorization'] = 'token ' . $this->authorize_token;
        }

        // Get cached response.
        $cached = get_transient( 'jezweb_github_response' );

        if ( false !== $cached ) {
            $this->github_response = $cached;
            return $cached;
        }

        // Make the request.
        $response = wp_remote_get( $request_uri, $args );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $result        = json_decode( $response_body );

        if ( ! $result || ! isset( $result->tag_name ) ) {
            return false;
        }

        $this->github_response = $result;

        // Cache for 6 hours.
        set_transient( 'jezweb_github_response', $result, 6 * HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Modify the update transient.
     *
     * @param object $transient Update transient.
     * @return object
     */
    public function modify_transient( $transient ) {
        if ( ! isset( $transient->checked ) ) {
            return $transient;
        }

        // Get plugin & GitHub release information.
        $this->set_plugin_properties();
        $release = $this->get_repository_info();

        if ( false === $release ) {
            return $transient;
        }

        // Check if a new version is available.
        $github_version  = ltrim( $release->tag_name, 'v' );
        $current_version = $this->plugin['Version'];

        $do_update = version_compare( $github_version, $current_version, '>' );

        if ( $do_update ) {
            // Get the download URL.
            $download_url = $this->get_download_url( $release );

            if ( $download_url ) {
                $plugin_data = array(
                    'id'           => $this->basename,
                    'slug'         => dirname( $this->basename ),
                    'plugin'       => $this->basename,
                    'new_version'  => $github_version,
                    'url'          => $this->plugin['PluginURI'],
                    'package'      => $download_url,
                    'icons'        => array(),
                    'banners'      => array(),
                    'banners_rtl'  => array(),
                    'tested'       => '',
                    'requires_php' => $this->plugin['RequiresPHP'] ?? '',
                    'compatibility' => new stdClass(),
                );

                $transient->response[ $this->basename ] = (object) $plugin_data;
            }
        }

        return $transient;
    }

    /**
     * Get download URL from release.
     *
     * @param object $release Release data.
     * @return string|false
     */
    private function get_download_url( $release ) {
        // Check for attached zip file first.
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( 'application/zip' === $asset->content_type ||
                     substr( $asset->name, -4 ) === '.zip' ) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fall back to zipball URL.
        if ( ! empty( $release->zipball_url ) ) {
            return $release->zipball_url;
        }

        return false;
    }

    /**
     * Populate plugin popup information.
     *
     * @param false|object|array $result Plugin API result.
     * @param string             $action API action.
     * @param object             $args   API arguments.
     * @return false|object|array
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( dirname( $this->basename ) !== $args->slug ) {
            return $result;
        }

        $this->set_plugin_properties();
        $release = $this->get_repository_info();

        if ( false === $release ) {
            return $result;
        }

        $github_version = ltrim( $release->tag_name, 'v' );

        $plugin_info = array(
            'name'              => $this->plugin['Name'],
            'slug'              => dirname( $this->basename ),
            'version'           => $github_version,
            'author'            => $this->plugin['AuthorName'],
            'author_profile'    => $this->plugin['AuthorURI'],
            'last_updated'      => $release->published_at,
            'homepage'          => $this->plugin['PluginURI'],
            'short_description' => $this->plugin['Description'],
            'sections'          => array(
                'description' => $this->plugin['Description'],
                'changelog'   => $this->get_changelog( $release ),
            ),
            'download_link'     => $this->get_download_url( $release ),
            'requires'          => $this->plugin['RequiresWP'] ?? '',
            'requires_php'      => $this->plugin['RequiresPHP'] ?? '',
            'tested'            => '',
        );

        return (object) $plugin_info;
    }

    /**
     * Get changelog from release body.
     *
     * @param object $release Release data.
     * @return string
     */
    private function get_changelog( $release ) {
        $changelog = '';

        if ( ! empty( $release->body ) ) {
            // Convert markdown to HTML (basic).
            $body = $release->body;

            // Convert headers.
            $body = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $body );
            $body = preg_replace( '/^## (.*)$/m', '<h3>$1</h3>', $body );
            $body = preg_replace( '/^# (.*)$/m', '<h2>$1</h2>', $body );

            // Convert lists.
            $body = preg_replace( '/^\* (.*)$/m', '<li>$1</li>', $body );
            $body = preg_replace( '/^- (.*)$/m', '<li>$1</li>', $body );

            // Wrap lists.
            $body = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $body );

            // Convert bold.
            $body = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $body );

            // Convert code.
            $body = preg_replace( '/`(.*?)`/', '<code>$1</code>', $body );

            // Convert line breaks.
            $body = nl2br( $body );

            $changelog = '<h4>' . sprintf(
                /* translators: %s: version number */
                esc_html__( 'Version %s', 'jezweb-search-result' ),
                ltrim( $release->tag_name, 'v' )
            ) . '</h4>';
            $changelog .= wp_kses_post( $body );
        }

        return $changelog;
    }

    /**
     * Handle post-install tasks.
     *
     * @param bool  $response   Installation response.
     * @param array $hook_extra Extra arguments.
     * @param array $result     Installation result.
     * @return array
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Only process our plugin.
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $result;
        }

        // Get the correct folder name.
        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->basename );

        // Move the plugin to the correct location.
        $wp_filesystem->move( $result['destination'], $plugin_folder );

        // Update result destination.
        $result['destination'] = $plugin_folder;

        // Activate if it was active before.
        if ( is_plugin_active( $this->basename ) ) {
            activate_plugin( $this->basename );
        }

        return $result;
    }

    /**
     * Add update check link to plugin row.
     *
     * @param array  $links Plugin links.
     * @param string $file  Plugin file.
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        if ( $file !== $this->basename ) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'plugins.php?jezweb_check_updates=1' ) ),
            esc_html__( 'Check for updates', 'jezweb-search-result' )
        );

        // Handle manual update check.
        if ( isset( $_GET['jezweb_check_updates'] ) && current_user_can( 'update_plugins' ) ) {
            delete_transient( 'jezweb_github_response' );
            delete_site_transient( 'update_plugins' );

            wp_safe_redirect( admin_url( 'plugins.php' ) );
            exit;
        }

        return $links;
    }

    /**
     * Clear update cache.
     */
    public static function clear_cache() {
        delete_transient( 'jezweb_github_response' );
    }
}
