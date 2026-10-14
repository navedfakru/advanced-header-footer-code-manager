<?php
/**
 * Admin screens: global settings page, per-post meta box, tools.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class Admin {

	const PAGE_SLUG       = 'ahfcm';
	const META_BOX_ID     = 'ahfcm_code';
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ahfcm_migrate', array( $this, 'handle_migration' ) );
		add_action( 'admin_post_ahfcm_restore', array( $this, 'handle_restore' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AHFCM_FILE ), array( $this, 'action_links' ) );
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
			Security::CAP_GLOBAL,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds the "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( Security::can_manage_global() ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'advanced-header-footer-code-manager' ) )
			);
		}
		return $links;
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Registers the per-post meta box for permitted users.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 * @return void
	 */
	public function add_meta_box( $post_type, $post ) {
		if ( ! in_array( $post_type, Post_Meta::supported_post_types(), true ) || ! $post instanceof \WP_Post || ! Security::can_edit_post_code( $post->ID ) ) {
			return;
		}

		// In the block editor the toolbar panel replaces the meta box, which
		// also keeps the "Meta Boxes" area away when nothing else uses it.
		// The box stays when the panel is unavailable (icon off or hidden for
		// this user), since it is then the only way to edit the code.
		if ( self::panel_replaces_meta_box( $post ) ) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			__( 'Header & Body Code Embed', 'advanced-header-footer-code-manager' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Whether the block-editor panel is used instead of the meta box.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return bool
	 */
	public static function panel_replaces_meta_box( $post ) {
		$replace = function_exists( 'use_block_editor_for_post' )
			&& use_block_editor_for_post( $post )
			&& Breakdance::button_visible_for( null, 'editor' );

		/**
		 * Filters whether the meta box is hidden in the block editor (where the
		 * toolbar panel edits the same code). Return false to show both.
		 *
		 * @param bool     $replace Whether the panel replaces the meta box.
		 * @param \WP_Post $post    Post.
		 */
		return (bool) apply_filters( 'ahfcm_panel_replaces_meta_box', $replace, $post );
	}

	/**
	 * Whether the current admin screen needs the editor assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( $hook ) {
		if ( $hook === $this->page_hook ) {
			return true;
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Post_Meta::supported_post_types(), true ) ) {
			return false;
		}

		$post_id = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : 0;
		return $post_id ? Security::can_edit_post_code( $post_id ) : current_user_can( 'unfiltered_html' );
	}

	/**
	 * Enqueues editor assets on plugin screens only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		UI::enqueue_editor();
		$this->enqueue_toolbar_button();
	}

	/**
	 * Adds the code icon to the block editor's top toolbar. It opens the
	 * "Header & Body Code Embed" meta box (the single place this post's code
	 * is edited on this screen, so nothing can be saved twice).
	 *
	 * @return void
	 */
	private function enqueue_toolbar_button() {
		$screen = get_current_screen();
		$post   = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post'] : null;
		if ( ! $screen || ! $post || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() ) {
			return;
		}
		if ( ! Breakdance::button_visible_for( null, 'editor' ) ) {
			return;
		}

		$data     = Post_Meta::get( $post->ID );
		$has_code = $data['enabled'] && ( Settings::slot_has_code( $data['slots']['head'] ) || Settings::slot_has_code( $data['slots']['footer'] ) );

		// The same panel as in the Breakdance builder, synced with the meta box.
		$can_global = Security::can_manage_global();
		Breakdance::enqueue_panel( $post->ID, $can_global, 'editor' );
		add_action(
			'admin_footer',
			static function () use ( $can_global ) {
				Breakdance::render_panel_template( true, $can_global );
			}
		);

		wp_enqueue_script( 'ahfcm-toolbar', AHFCM_URL . 'assets/js/toolbar.js', array( 'wp-data', 'wp-dom-ready', 'ahfcm-builder' ), AHFCM_VERSION, true );
		wp_add_inline_script(
			'ahfcm-toolbar',
			'window.AHFCM_TOOLBAR = ' . wp_json_encode(
				array(
					'boxId'   => self::META_BOX_ID,
					'hasCode' => $has_code,
					'label'   => __( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Language labels.
	 *
	 * @return array<string,string>
	 */
	public static function language_labels() {
		return UI::language_labels();
	}

	/**
	 * Location labels.
	 *
	 * @return array
	 */
	public static function slot_labels() {
		return UI::slot_labels();
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage header and footer code.', 'advanced-header-footer-code-manager' ) );
		}

		$settings          = Settings::get();
		$history           = Settings::get_history();
		$report            = $this->pull_report();
		$legacy_active     = Migration::legacy_active();
		$legacy_count      = count( Migration::find_legacy_posts() );
		$post_types        = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		include AHFCM_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Renders the per-post meta box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$data             = Post_Meta::get( $post->ID );
		$global           = Settings::get();
		$post_type_object = get_post_type_object( $post->post_type );
		$type_label       = $post_type_object ? $post_type_object->labels->singular_name : __( 'post', 'advanced-header-footer-code-manager' );

		include AHFCM_DIR . 'admin/views/meta-box.php';
	}

	/**
	 * Renders one editor field.
	 *
	 * @param string $id         Element ID.
	 * @param string $name_base  Form name prefix.
	 * @param string $slot       Slot key.
	 * @param array  $data       Slot data.
	 * @param string $scope_note Scope reminder.
	 * @return void
	 */
	public static function render_field( $id, $name_base, $slot, array $data, $scope_note ) {
		UI::render_field( $id, $name_base, $slot, $data, $scope_note );
	}

	/**
	 * Runs the legacy import (dry run or real).
	 *
	 * @return void
	 */
	public function handle_migration() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-header-footer-code-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ahfcm_migrate' );

		$dry_run = ! isset( $_POST['ahfcm_run'] );
		$report  = Migration::run(
			array(
				'dry_run'     => $dry_run,
				'enable'      => ! empty( $_POST['enable'] ),
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

		wp_safe_redirect( self::page_url( array( 'ahfcm_notice' => 'migration' ) ) . '#tools' );
		exit;
	}

	/**
	 * Restores a global-code history entry.
	 *
	 * @return void
	 */
	public function handle_restore() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-header-footer-code-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ahfcm_restore' );

		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$history = Settings::get_history();
		$notice  = 'restore_failed';

		if ( isset( $history[ $index ]['value'] ) && is_array( $history[ $index ]['value'] ) ) {
			$current           = Settings::get();
			$restored          = $current;
			$restored['enabled'] = $history[ $index ]['value']['enabled'];
			$restored['slots']   = $history[ $index ]['value']['slots'];

			// The current version goes into history automatically (record_history).
			update_option( Settings::OPTION, Settings::normalize( $restored ) );
			$notice = 'restored';
		}

		wp_safe_redirect( self::page_url( array( 'ahfcm_notice' => $notice ) ) . '#tools' );
		exit;
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
