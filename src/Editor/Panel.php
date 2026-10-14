<?php
/**
 * The Header & Footer Code panel (dark modal with "This page" / "Global"
 * tabs), shared by the block editor and the Breakdance/Oxygen builders.
 *
 * @package AHFCM
 */

namespace AHFCM\Editor;

use AHFCM\Api\Rest;
use AHFCM\Core\PostMeta;
use AHFCM\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Panel assets and markup.
 */
final class Panel {

	const MODE_BUILDER = 'builder';
	const MODE_EDITOR  = 'editor';

	/**
	 * Enqueues the panel with its config.
	 *
	 * @param int    $doc_id      Document whose code the first tab edits.
	 * @param bool   $can_global  Whether the Global tab is available.
	 * @param string $mode        builder (Breakdance/Oxygen) or editor (block editor).
	 * @param string $builder     Builder name shown in labels (builder mode).
	 * @return void
	 */
	public static function enqueue( $doc_id, $can_global, $mode, $builder = '' ) {
		Assets::enqueue();

		wp_enqueue_style( 'ahfcm-panel', AHFCM_URL . 'assets/css/panel.css', array( 'ahfcm-editor' ), AHFCM_VERSION );
		wp_enqueue_script( 'ahfcm-panel', AHFCM_URL . 'assets/js/panel.js', array( 'ahfcm-editor' ), AHFCM_VERSION, true );
		wp_add_inline_script(
			'ahfcm-panel',
			'window.AHFCM_PANEL = ' . wp_json_encode(
				array(
					'mode'      => self::MODE_EDITOR === $mode ? self::MODE_EDITOR : self::MODE_BUILDER,
					'builder'   => $builder,
					'restUrl'   => esc_url_raw( rest_url( Rest::NAMESPACE_V1 . '/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'docId'     => (int) $doc_id,
					'canGlobal' => (bool) $can_global,
					'schema'    => Settings::SCHEMA_VERSION,
					'i18n'      => self::strings(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Prints the panel markup inside a <template> (cloned on first open).
	 *
	 * @param bool $can_post   Whether the "This page" tab is shown.
	 * @param bool $can_global Whether the Global tab is shown.
	 * @return void
	 */
	public static function template( $can_post, $can_global ) {
		$settings = Settings::get();
		$empty    = PostMeta::defaults();

		include AHFCM_DIR . 'views/editor/panel.php';
	}

	/**
	 * Translated strings for panel.js.
	 *
	 * @return array
	 */
	private static function strings() {
		return array(
			'button'      => __( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
			'loading'     => __( 'Loading…', 'advanced-header-footer-code-manager' ),
			'saving'      => __( 'Saving…', 'advanced-header-footer-code-manager' ),
			'saved'       => __( 'Saved', 'advanced-header-footer-code-manager' ),
			'unsaved'     => __( 'Unsaved changes', 'advanced-header-footer-code-manager' ),
			/* translators: %s: error message. */
			'error'       => __( 'Could not save: %s', 'advanced-header-footer-code-manager' ),
			/* translators: %s: error message. */
			'loadError'   => __( 'Could not load code: %s', 'advanced-header-footer-code-manager' ),
			'confirmLose' => __( 'You have unsaved code changes. Close anyway?', 'advanced-header-footer-code-manager' ),
			/* translators: 1: post type name, 2: post title. */
			'thisDoc'     => __( '%1$s: %2$s', 'advanced-header-footer-code-manager' ),
			'thisPage'    => __( 'This page', 'advanced-header-footer-code-manager' ),
			/* translators: %s: builder document type, e.g. "template". */
			'thisType'    => __( 'This %s', 'advanced-header-footer-code-manager' ),
			/* translators: %s: content type, e.g. "page" or "template". */
			'enableOn'    => __( 'Custom code on this %s', 'advanced-header-footer-code-manager' ),
			'scopePage'   => __( 'This page only', 'advanced-header-footer-code-manager' ),
			/* translators: %s: builder document type, e.g. "template". */
			'scopeType'   => __( 'Every page using this %s', 'advanced-header-footer-code-manager' ),
		);
	}
}
