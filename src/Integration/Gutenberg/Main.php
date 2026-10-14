<?php
/**
 * Block editor (Gutenberg) integration: a code icon in the editor's top
 * toolbar that opens the Header & Footer Code panel.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration\Gutenberg;

use AHFCM\Integration\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Block editor integration.
 */
class Main implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'gutenberg';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Block editor', 'advanced-header-footer-code-manager' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'A code icon in the block editor toolbar (next to “Edit in Breakdance/Oxygen”) opens the code panel for the page.', 'advanced-header-footer-code-manager' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses the WordPress logo (dashicons) instead of an image.
	 */
	public function get_icon_url() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return function_exists( 'use_block_editor_for_post' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register() {
		( new Editor() )->register();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_template_types() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_documents_for_request( $page_id, $slot ) {
		return array();
	}
}
