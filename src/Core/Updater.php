<?php
/**
 * Updates from GitHub releases.
 *
 * The plugin header declares `Update URI: https://github.com/navedfakru/…`,
 * so WordPress asks the `update_plugins_github.com` filter (never
 * WordPress.org) whether a newer version exists. The latest GitHub release is
 * read from the public API and cached for 12 hours; its tag (v2.3.1 or 2.3.1)
 * is the version and its attached `advanced-header-footer-code-manager-*.zip`
 * asset is the package, so WordPress installs it like any other update.
 *
 * Network: one anonymous HTTPS request to api.github.com per cache period,
 * only from WordPress's own update checks. Nothing about the site is sent
 * beyond what any HTTP request carries.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * GitHub release updater.
 */
final class Updater {

	const REPO      = 'navedfakru/advanced-header-footer-code-manager';
	const SLUG      = 'advanced-header-footer-code-manager';
	const CACHE_KEY = 'ahfcm_github_release';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/** Cache time after a failed request, so an outage is not retried on every load. */
	const ERROR_TTL = HOUR_IN_SECONDS;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
	}

	/**
	 * Basename of this plugin (folder/file.php).
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( AHFCM_FILE );
	}

	/**
	 * Answers WordPress's update check for this plugin.
	 *
	 * @param array|false $update      Update data from earlier filters.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			'icons'        => array(
				'svg' => AHFCM_URL . 'assets/images/ahfcm-icon.svg',
			),
		);
	}

	/**
	 * "View details" modal for this plugin.
	 *
	 * @param false|object|array $result Result from earlier filters.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $result;
		}

		$headers = get_file_data(
			AHFCM_FILE,
			array(
				'name'         => 'Plugin Name',
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'author'       => 'Author',
			)
		);

		return (object) array(
			'name'          => $headers['name'],
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => esc_html( $headers['author'] ),
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => $headers['requires'],
			'requires_php'  => $headers['requires_php'],
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				/* translators: %s: GitHub URL. */
				'description' => '<p>' . sprintf( esc_html__( 'Source, documentation and all releases: %s', 'advanced-header-footer-code-manager' ), '<a href="' . esc_url( 'https://github.com/' . self::REPO ) . '">github.com/' . esc_html( self::REPO ) . '</a>' ) . '</p>',
				'changelog'   => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Latest usable release, or null.
	 *
	 * @return array{version:string,package:string,url:string,notes:string,published:string}|null
	 */
	public static function latest() {
		// "Check again" on Dashboard → Updates bypasses the cache.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by core's own link.
		$force  = is_admin() && isset( $_GET['force-check'] );
		$cached = $force ? false : get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$release = self::fetch();
		set_site_transient( self::CACHE_KEY, $release ? $release : array( 'error' => true ), $release ? self::CACHE_TTL : self::ERROR_TTL );
		return $release;
	}

	/**
	 * Reads the latest release from the GitHub API.
	 *
	 * @return array|null
	 */
	private static function fetch() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'AHFCM/' . AHFCM_VERSION . '; WordPress',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? self::parse( $data ) : null;
	}

	/**
	 * Turns a GitHub release object into update data.
	 *
	 * The package must be an uploaded asset (its zip has the right folder
	 * name); GitHub's automatic source zips are named after the tag and would
	 * install into the wrong folder.
	 *
	 * @param array $data Release JSON.
	 * @return array|null
	 */
	public static function parse( array $data ) {
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) || empty( $data['tag_name'] ) ) {
			return null;
		}
		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
			return null;
		}

		$package = '';
		foreach ( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array() as $asset ) {
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( 0 === strpos( $name, self::SLUG ) && '.zip' === substr( $name, -4 ) && 0 === strpos( $url, 'https://github.com/' . self::REPO . '/releases/download/' ) ) {
				$package = $url;
				break;
			}
		}
		if ( '' === $package ) {
			return null;
		}

		return array(
			'version'   => $version,
			'package'   => $package,
			'url'       => isset( $data['html_url'] ) ? esc_url_raw( (string) $data['html_url'] ) : 'https://github.com/' . self::REPO . '/releases',
			'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
		);
	}

	/**
	 * Clears the cached release.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_site_transient( self::CACHE_KEY );
	}
}
