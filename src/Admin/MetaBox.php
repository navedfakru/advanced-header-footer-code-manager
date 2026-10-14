<?php
/**
 * "Header & Body Code Embed" meta box on post edit screens.
 *
 * Shown in the Classic editor, and in the block editor only when the toolbar
 * panel is unavailable there (see Integration\Gutenberg\Editor).
 *
 * @package AHFCM
 */

namespace AHFCM\Admin;

use AHFCM\Core\PostMeta;
use AHFCM\Core\Security;
use AHFCM\Core\Settings;
use AHFCM\Editor\Assets;
use AHFCM\Integration\Gutenberg\Editor as GutenbergEditor;

defined( 'ABSPATH' ) || exit;

/**
 * Per-post meta box.
 */
final class MetaBox {

	const BOX_ID = 'ahfcm_code';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the meta box belongs on this post's edit screen.
	 *
	 * @param \WP_Post|null $post Post.
	 * @return bool
	 */
	private function applies_to( $post ) {
		return $post instanceof \WP_Post
			&& in_array( $post->post_type, PostMeta::supported_post_types(), true )
			&& Security::can_edit_post_code( $post->ID )
			&& ! GutenbergEditor::replaces_meta_box( $post );
	}

	/**
	 * Adds the meta box.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 * @return void
	 */
	public function add( $post_type, $post ) {
		if ( ! $this->applies_to( $post ) ) {
			return;
		}

		add_meta_box(
			self::BOX_ID,
			__( 'Header & Body Code Embed', 'advanced-header-footer-code-manager' ),
			array( $this, 'render' ),
			$post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Loads the editor on edit screens that show the box.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && $this->applies_to( isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null ) ) {
			Assets::enqueue();
		}
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render( $post ) {
		$data             = PostMeta::get( $post->ID );
		$global           = Settings::get();
		$post_type_object = get_post_type_object( $post->post_type );
		$type_label       = $post_type_object ? $post_type_object->labels->singular_name : __( 'post', 'advanced-header-footer-code-manager' );

		include AHFCM_DIR . 'views/admin/meta-box.php';
	}
}
