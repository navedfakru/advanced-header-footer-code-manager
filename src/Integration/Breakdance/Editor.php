<?php
/**
 * Builder UI: a code button in the Breakdance/Oxygen top bar (after the
 * properties button) that opens the Header & Footer Code panel.
 *
 * The builder page does not run wp_head()/wp_footer(), so assets are printed
 * on the builder's footer action — the same approach WindPress uses.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration\Breakdance;

use AHFCM\Core\Security;
use AHFCM\Core\Visibility;
use AHFCM\Editor\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Builder toolbar button + panel.
 */
class Editor {

	/**
	 * Printed by the builder after its app scripts.
	 *
	 * @see wp-content/plugins/breakdance/plugin/loader/loader.php
	 */
	const BUILDER_HOOK = 'unofficial_i_am_kevin_geary_master_of_all_things_css_and_html';

	/**
	 * Integration this editor belongs to (Breakdance or Oxygen).
	 *
	 * @var Main
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param Main $integration Integration.
	 */
	public function __construct( Main $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::BUILDER_HOOK, array( $this, 'print_assets' ), 1000002 );
	}

	/**
	 * Whether this request renders the builder UI (?breakdance=builder or
	 * ?oxygen=builder).
	 *
	 * @return bool
	 */
	public function is_builder() {
		$param = $this->integration->get_mode();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
		return isset( $_GET[ $param ] ) && 'builder' === $_GET[ $param ];
	}

	/**
	 * ID of the document opened in the builder (0 when none).
	 *
	 * @return int
	 */
	private function document_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Prints styles, scripts and the panel template on the builder page.
	 *
	 * @return void
	 */
	public function print_assets() {
		/**
		 * Filters whether the builder button loads.
		 *
		 * @param bool   $enabled Default true.
		 * @param string $builder breakdance|oxygen.
		 */
		if ( ! $this->is_builder() || ! apply_filters( 'ahfcm_breakdance_integration', true, $this->integration->get_name() ) || ! Visibility::button_visible_for( null, Visibility::WHERE_BUILDER ) ) {
			return;
		}

		$doc_id     = $this->document_id();
		$can_post   = $doc_id && Security::can_edit_post_code( $doc_id );
		$can_global = Security::can_manage_global();
		if ( ! $can_post && ! $can_global ) {
			return;
		}

		$scripts_before = wp_scripts()->queue;
		$styles_before  = wp_styles()->queue;

		Panel::enqueue( $doc_id, $can_global, Panel::MODE_BUILDER, $this->integration->get_label() );
		Panel::template( $can_post, $can_global );

		// Print only what this plugin added (plus dependencies).
		wp_print_styles( array_values( array_diff( wp_styles()->queue, $styles_before ) ) );
		wp_print_scripts( array_values( array_diff( wp_scripts()->queue, $scripts_before ) ) );
	}
}
