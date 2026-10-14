<?php
/**
 * Contract for editor / page-builder integrations.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * An integration (block editor, Breakdance, Oxygen, ...).
 */
interface IntegrationInterface {

	/**
	 * Machine name, e.g. "breakdance". Used as the settings key.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Human name, e.g. "Breakdance".
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * One-line description for the Integrations screen.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Brand logo URL for the Integrations screen ('' = generic icon).
	 *
	 * @return string
	 */
	public function get_icon_url();

	/**
	 * Whether the editor/builder is installed and running on this site.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Hooks the integration into WordPress. Called only when active and enabled.
	 *
	 * @return void
	 */
	public function register();

	/**
	 * Builder document post types that can carry their own code.
	 *
	 * @return array<string,string> Post type => label.
	 */
	public function get_template_types();

	/**
	 * Builder documents whose code applies to the current request, in output
	 * order (excluding the page itself).
	 *
	 * @param int    $page_id Singular post being viewed (0 if none).
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public function get_documents_for_request( $page_id, $slot );
}
