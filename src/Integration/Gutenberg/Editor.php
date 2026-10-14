<?php
/**
 * Block editor UI: toolbar icon + Header & Footer Code panel.
 *
 * In the block editor the panel replaces the "Header & Body Code Embed" meta
 * box (see replaces_meta_box()), so code is edited in one place and a post
 * Update can never overwrite it. The meta box stays in the Classic editor.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration\Gutenberg;

use AHFCM\Core\PostMeta;
use AHFCM\Core\Security;
use AHFCM\Core\Settings;
use AHFCM\Core\Visibility;
use AHFCM\Editor\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Block editor toolbar button + panel.
 */
class Editor {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the block-editor panel is used instead of the meta box for a
	 * post. False when the integration is off or the icon is hidden for the
	 * current user (the meta box is then the only way to edit code).
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return bool
	 */
	public static function replaces_meta_box( $post ) {
		$replace = Settings::integration_enabled( 'gutenberg' )
			&& function_exists( 'use_block_editor_for_post' )
			&& use_block_editor_for_post( $post )
			&& Visibility::button_visible_for( null, Visibility::WHERE_EDITOR );

		/**
		 * Filters whether the meta box is hidden in the block editor. Return
		 * false to show both.
		 *
		 * @param bool     $replace Whether the panel replaces the meta box.
		 * @param \WP_Post $post    Post.
		 */
		return (bool) apply_filters( 'ahfcm_panel_replaces_meta_box', $replace, $post );
	}

	/**
	 * Loads the panel and toolbar icon on block-editor screens.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		$post   = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post'] : null;
		if ( ! $screen || ! $post || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() ) {
			return;
		}
		if ( ! in_array( $post->post_type, PostMeta::supported_post_types(), true ) || ! Security::can_edit_post_code( $post->ID ) ) {
			return;
		}
		if ( ! Visibility::button_visible_for( null, Visibility::WHERE_EDITOR ) ) {
			return;
		}

		$data       = PostMeta::get( $post->ID );
		$has_code   = $data['enabled'] && ( Settings::slot_has_code( $data['slots']['head'] ) || Settings::slot_has_code( $data['slots']['footer'] ) );
		$can_global = Security::can_manage_global();

		Panel::enqueue( $post->ID, $can_global, Panel::MODE_EDITOR );
		add_action(
			'admin_footer',
			static function () use ( $can_global ) {
				Panel::template( true, $can_global );
			}
		);

		wp_enqueue_script( 'ahfcm-gutenberg', AHFCM_URL . 'assets/js/integrations/gutenberg.js', array( 'wp-data', 'wp-dom-ready', 'ahfcm-panel' ), AHFCM_VERSION, true );
		wp_add_inline_script(
			'ahfcm-gutenberg',
			'window.AHFCM_GUTENBERG = ' . wp_json_encode(
				array(
					'boxId'   => \AHFCM\Admin\MetaBox::BOX_ID,
					'hasCode' => $has_code,
					'label'   => __( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
				)
			) . ';',
			'before'
		);
	}
}
