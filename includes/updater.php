<?php
/**
 * Cookiebaas — GitHub Updater
 *
 * Koppelt WordPress' ingebouwde update-systeem aan een GitHub repository.
 * Ondersteunt zowel publieke als private repositories (via personal access token).
 * Controleert automatisch op nieuwe releases en biedt 1-klik updates in wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CM_GitHub_Updater {

    private $slug;
    private $plugin_file;
    private $github_user;
    private $github_repo;
    private $current_version;
    private $github_response;

    public function __construct( $plugin_file, $github_user, $github_repo ) {
        $this->plugin_file     = $plugin_file;
        $this->slug            = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->current_version = CM_VERSION;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install',                 array( $this, 'after_install' ), 10, 3 );
        add_filter( 'http_request_args',                     array( $this, 'add_auth_to_download' ), 10, 2 );
    }

    /**
     * Haal het GitHub token op (opgeslagen als WordPress-optie).
     */
    private function get_token() {
        $token = get_option( 'cm_github_token', '' );
        return is_string( $token ) ? trim( $token ) : '';
    }

    /**
     * Bouw de HTTP headers voor GitHub API requests.
     */
    private function api_headers() {
        $headers = array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'Cookiebaas-Updater/' . $this->current_version,
        );
        $token = $this->get_token();
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    /**
     * Haal de laatste release op van GitHub (cached voor 6 uur).
     */
    private function get_github_release() {
        if ( $this->github_response !== null ) {
            return $this->github_response;
        }

        $transient_key = 'cm_github_release_' . md5( $this->github_user . $this->github_repo );
        $cached = get_transient( $transient_key );

        if ( $cached !== false ) {
            $this->github_response = $cached;
            return $cached;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_user,
            $this->github_repo
        );

        $response = wp_remote_get( $url, array(
            'headers' => $this->api_headers(),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $this->github_response = false;
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tag_name'] ) ) {
            $this->github_response = false;
            return false;
        }

        $this->github_response = $body;
        set_transient( $transient_key, $body, 6 * HOUR_IN_SECONDS );

        return $body;
    }

    /**
     * Bepaal de download-URL voor de release.
     * Private repos: gebruik de API-URL voor assets (vereist auth header).
     */
    private function get_download_url( $release ) {
        $token = $this->get_token();

        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( substr( $asset['name'], -4 ) === '.zip' ) {
                    if ( $token ) {
                        return $asset['url'];
                    }
                    return $asset['browser_download_url'];
                }
            }
        }

        if ( ! empty( $release['zipball_url'] ) ) {
            return $release['zipball_url'];
        }

        return '';
    }

    /**
     * Voeg de auth header toe aan download-requests voor private repo assets.
     */
    public function add_auth_to_download( $args, $url ) {
        $token = $this->get_token();
        if ( ! $token ) return $args;

        $api_prefix  = sprintf( 'https://api.github.com/repos/%s/%s/', $this->github_user, $this->github_repo );
        $site_prefix = sprintf( 'https://github.com/%s/%s/', $this->github_user, $this->github_repo );

        if ( strpos( $url, $api_prefix ) !== 0 && strpos( $url, $site_prefix ) !== 0 ) {
            return $args;
        }

        if ( ! isset( $args['headers'] ) ) $args['headers'] = array();
        $args['headers']['Authorization'] = 'Bearer ' . $token;
        $args['headers']['Accept']        = 'application/octet-stream';

        return $args;
    }

    /**
     * Vergelijk versies en voeg update toe aan WordPress transient.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ( ! $release ) return $transient;

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
            $download_url = $this->get_download_url( $release );

            if ( $download_url ) {
                $transient->response[ $this->slug ] = (object) array(
                    'slug'        => dirname( $this->slug ),
                    'plugin'      => $this->slug,
                    'new_version' => $remote_version,
                    'url'         => $release['html_url'],
                    'package'     => $download_url,
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => '',
                    'requires'    => '5.0',
                    'requires_php'=> '7.4',
                );
            }
        }

        return $transient;
    }

    /**
     * Toon plugin-informatie in de "Bekijk details" popup.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) return $result;

        $release = $this->get_github_release();
        if ( ! $release ) return $result;

        $remote_version = ltrim( $release['tag_name'], 'vV' );
        $published      = isset( $release['published_at'] ) ? date_i18n( get_option('date_format'), strtotime( $release['published_at'] ) ) : '';

        $info = (object) array(
            'name'              => 'Cookiebaas',
            'slug'              => dirname( $this->slug ),
            'version'           => $remote_version,
            'author'            => '<a href="https://www.cookiebaas.nl/">Ruud van der Heijden</a>',
            'homepage'          => 'https://www.cookiebaas.nl/',
            'requires'          => '5.0',
            'requires_php'      => '7.4',
            'tested'            => '',
            'last_updated'      => $published,
            'sections'          => array(
                'description'   => 'Cookiemelding plugin volgens AVG/GDPR-conformiteit met Google Consent Mode (v2) integratie en privacyverklaring generator.',
                'changelog'     => nl2br( esc_html( $release['body'] ?? 'Geen changelog beschikbaar.' ) ),
            ),
            'download_link'     => $this->get_download_url( $release ),
        );

        return $info;
    }

    /**
     * Na installatie: hernoem de map naar de juiste plugin-mapnaam.
     */
    public function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
            return $result;
        }

        global $wp_filesystem;

        $plugin_dir  = WP_PLUGIN_DIR . '/' . dirname( $this->slug );
        $install_dir = $result['destination'];

        if ( $install_dir !== $plugin_dir ) {
            $wp_filesystem->move( $install_dir, $plugin_dir );
            $result['destination'] = $plugin_dir;
        }

        activate_plugin( $this->slug );

        return $result;
    }
}
