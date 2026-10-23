<?php
/**
 * Settings → Header & Footer Code: the settings app, plus its tool actions
 * (legacy import, global and page code restore).
 *
 * Administrators can always open the page. While code editing is locked
 * (Security::lock_reason()) it is read-only: change log, history and safe mode.
 *
 * @package AHFCM
 */

namespace AHFCM\Admin;

use AHFCM\Core\ChangeLog;
use AHFCM\Core\PostMeta;
use AHFCM\Core\SafeMode;
use AHFCM\Core\Security;
use AHFCM\Core\Settings;
use AHFCM\Core\Visibility;
use AHFCM\Editor\Assets;
use AHFCM\Integration\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Settings app controller.
 */
final class AdminPage {

	const PAGE_SLUG        = 'ahfcm';
	const REPORT_TRANSIENT = 'ahfcm_report_';

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ahfcm_migrate', array( $this, 'handle_migration' ) );
		add_action( 'admin_post_ahfcm_restore', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_ahfcm_restore_post', array( $this, 'handle_restore_post' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AHFCM_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Adds Settings → Header & Footer Code.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->page_hook = (string) add_options_page(
			__( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
			__( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
			Security::CAP_VIEW,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds the "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( Security::can_view() ) {
			array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'advanced-header-footer-code-manager' ) ) );
		}
		return $links;
	}

	/**
	 * Loads the editor and app styles on the settings page only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}
		Assets::enqueue();
		wp_enqueue_style( 'ahfcm-admin', AHFCM_URL . 'assets/css/admin.css', array( 'ahfcm-editor' ), AHFCM_VERSION );
	}

	/**
	 * Renders the settings app.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Security::can_view() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage header and footer code.', 'advanced-header-footer-code-manager' ) );
		}

		$settings      = Settings::get();
		$history       = Settings::get_history();
		$lock_reason   = Security::lock_reason();
		$can_edit      = Security::can_manage_global();
		$change_log    = ChangeLog::get();
		$page_history  = PostMeta::posts_with_history();
		$safe_constant = SafeMode::by_constant();
		$report        = $this->pull_report();
		$legacy_active = LegacyImport::legacy_active();
		$legacy_count  = count( LegacyImport::find_legacy_posts() );
		$integrations  = Loader::all();
		$builder       = Loader::builder();
		$roles         = Visibility::eligible_roles();
		$users         = Visibility::eligible_users();
		$post_types    = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		include AHFCM_DIR . 'views/admin/app.php';
	}

	/**
	 * Runs the legacy import (dry run or real).
	 *
	 * @return void
	 */
	public function handle_migration() {
		$this->guard( 'ahfcm_migrate', Security::can_manage_global() );

		$dry_run = ! isset( $_POST['ahfcm_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$report  = LegacyImport::run(
			array(
				'dry_run' => $dry_run,
				'enable'  => ! empty( $_POST['enable'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
			)
		);

		set_transient(
			self::REPORT_TRANSIENT . get_current_user_id(),
			array(
				'type'    => 'migration',
				'dry_run' => $dry_run,
				'rows'    => $report,
			),
			10 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::url( array( 'ahfcm_notice' => 'migration' ) ) . '#tools' );
		exit;
	}

	/**
	 * Restores a global-code history entry.
	 *
	 * @return void
	 */
	public function handle_restore() {
		$this->guard( 'ahfcm_restore', Security::can_manage_global() );

		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$history = Settings::get_history();
		$notice  = 'restore_failed';

		if ( isset( $history[ $index ]['value'] ) && is_array( $history[ $index ]['value'] ) ) {
			$restored            = Settings::get();
			$restored['enabled'] = $history[ $index ]['value']['enabled'];
			$restored['slots']   = $history[ $index ]['value']['slots'];

			// The current version goes into history automatically (record_history).
			ChangeLog::mark_restore();
			update_option( Settings::OPTION, Settings::normalize( $restored ) );
			ChangeLog::take_restore(); // Unused when the restored version equals the current one.
			$notice = 'restored';
		}

		wp_safe_redirect( self::url( array( 'ahfcm_notice' => $notice ) ) . '#tools' );
		exit;
	}

	/**
	 * Restores a page code history entry.
	 *
	 * @return void
	 */
	public function handle_restore_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$this->guard( 'ahfcm_restore_post_' . $post_id, $post_id && Security::can_edit_post_code( $post_id ) );

		$notice = PostMeta::restore( $post_id, $index ) ? 'restored_post' : 'restore_failed';

		wp_safe_redirect( self::url( array( 'ahfcm_notice' => $notice ) ) . '#tools' );
		exit;
	}

	/**
	 * Capability + nonce check for tool actions.
	 *
	 * @param string $action  Nonce action.
	 * @param bool   $allowed Result of the capability check.
	 * @return void
	 */
	private function guard( $action, $allowed ) {
		if ( ! $allowed ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-header-footer-code-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Fetches and clears the current user's pending report.
	 *
	 * @return array|null
	 */
	private function pull_report() {
		$key    = self::REPORT_TRANSIENT . get_current_user_id();
		$report = get_transient( $key );
		if ( false === $report ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $report ) ? $report : null;
	}
}
