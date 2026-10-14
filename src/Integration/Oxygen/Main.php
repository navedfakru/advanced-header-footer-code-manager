<?php
/**
 * Oxygen 6 integration.
 *
 * Oxygen 6 runs on the Breakdance engine (BREAKDANCE_MODE = "oxygen"): the
 * same template controller, builder hook and data format. Only the builder
 * URL (?oxygen=builder) and the post type names (oxygen_template,
 * oxygen_header, oxygen_footer, oxygen_block) differ, and the post types come
 * from the engine's own constants. So everything is inherited.
 *
 * Oxygen Classic (4.x) is a different product and is not supported.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration\Oxygen;

use AHFCM\Integration\Breakdance\Main as BreakdanceEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Oxygen 6 integration.
 */
class Main extends BreakdanceEngine {

	/**
	 * {@inheritDoc}
	 */
	public function get_mode() {
		return 'oxygen';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'oxygen';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Oxygen';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Oxygen 6: code on templates, headers, footers, popups and blocks, plus a code button in the builder top bar.', 'advanced-header-footer-code-manager' );
	}
}
