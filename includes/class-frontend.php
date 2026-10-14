<?php
/**
 * Front-end injection engine.
 *
 * Execution order inside each location:
 *
 *     1. Global code (Settings → Header & Footer Code)
 *     2. Breakdance header, template(s), footer, popups and global blocks used by the request
 *     3. Code of the current singular post/page/CPT entry
 *
 * Locations and default hook priorities (configurable per slot):
 *
 *     head    wp_head    10   inside <head>
 *     footer  wp_footer  100  before </body>, after footer scripts
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 */
final class Frontend {

	/**
	 * Slots already printed in this request, to prevent duplicate output when a
	 * template fires a hook more than once.
	 *
	 * @var array<string,bool>
	 */
	private $printed = array();

	/**
	 * Registers output hooks.
	 *
	 * @return void
	 */
	public function register() {
		$settings = Settings::get();

		add_action( 'wp_head', array( $this, 'print_head' ), $settings['slots']['head']['priority'] );
		add_action( 'wp_footer', array( $this, 'print_footer' ), $settings['slots']['footer']['priority'] );
	}

	/**
	 * Prints head code.
	 *
	 * @return void
	 */
	public function print_head() {
		$this->print_slot( 'head' );
	}

	/**
	 * Prints footer code.
	 *
	 * @return void
	 */
	public function print_footer() {
		$this->print_slot( 'footer' );
	}

	/**
	 * Prints a slot once per request.
	 *
	 * @param string $slot Slot name.
	 * @return void
	 */
	private function print_slot( $slot ) {
		if ( ! empty( $this->printed[ $slot ] ) || ! $this->should_inject() ) {
			return;
		}
		$this->printed[ $slot ] = true;

		echo $this->render( $slot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted administrator code, see render().
	}

	/**
	 * Builds the HTML for one slot.
	 *
	 * Code is written only by users holding unfiltered_html (see Security) and
	 * must reach the browser unmodified, so it is deliberately not escaped.
	 *
	 * @param string $slot    Slot name.
	 * @param int    $post_id Optional post ID; defaults to the queried singular object.
	 * @return string
	 */
	public function render( $slot, $post_id = null ) {
		$global = Settings::get();

		// Documents whose code applies: Breakdance header/templates/footer/popups,
		// then the page itself (most specific last). An explicit $post_id
		// renders that document only.
		if ( null === $post_id ) {
			$page    = $this->current_post_id();
			$layout  = Breakdance::active_document_ids();
			$blocks  = Breakdance::global_block_ids( array_merge( $layout, array( $page ) ), $slot );
			$popups  = Breakdance::referenced_popup_ids( array_merge( $layout, $blocks, array( $page ) ), $slot );
			$doc_ids = array_merge( $layout, $popups, $blocks, array( $page ) );
		} else {
			$doc_ids = array( (int) $post_id );
		}

		$supported = Post_Meta::supported_post_types();
		$docs      = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $doc_ids ) ) ) as $doc_id ) {
			$type = get_post_type( $doc_id );
			if ( $type && in_array( $type, $supported, true ) ) {
				$docs[ $doc_id ] = Post_Meta::get( $doc_id );
			}
		}

		$suppress_global = false;
		foreach ( $docs as $doc ) {
			$suppress_global = $suppress_global || ( $doc['enabled'] && $doc['suppress_global'] );
		}

		$chunks = array();
		if ( $global['enabled'] && ! $suppress_global ) {
			$chunks[] = array( 'global', 0, $global['slots'][ $slot ] );
		}
		foreach ( $docs as $doc_id => $doc ) {
			if ( $doc['enabled'] ) {
				$chunks[] = array( 'post', $doc_id, $doc['slots'][ $slot ] );
			}
		}

		$html = '';
		foreach ( $chunks as $chunk ) {
			list( $source, $doc_id, $data ) = $chunk;

			if ( ! $data['enabled'] || ! Settings::slot_has_code( $data ) ) {
				continue;
			}

			$code = self::compose( $data );

			/**
			 * Filters one snippet before output. Return '' to skip it.
			 *
			 * @param string $code    Snippet HTML.
			 * @param string $slot    head|footer.
			 * @param string $source  global|post.
			 * @param int    $post_id Document the snippet belongs to (0 for global).
			 */
			$code = (string) apply_filters( 'ahfcm_snippet_output', $code, $slot, $source, (int) $doc_id );
			if ( '' === $code ) {
				continue;
			}

			if ( $global['output_markers'] ) {
				$label = 'global' === $source ? 'global' : self::marker_label( $doc_id );
				$code  = "
<!-- AHFCM {$slot} ({$label}) -->
" . $code . "
<!-- /AHFCM {$slot} ({$label}) -->
";
			} else {
				$code = "
" . $code . "
";
			}

			$html .= $code;
		}

		return $html;
	}

	/**
	 * Marker label: "post 81", or "breakdance_header 12" for Breakdance documents.
	 *
	 * @param int $doc_id Post ID.
	 * @return string
	 */
	private static function marker_label( $doc_id ) {
		$type = (string) get_post_type( $doc_id );
		return ( 0 === strpos( $type, 'breakdance_' ) || 0 === strpos( $type, 'oxygen_' ) ? $type : 'post' ) . ' ' . (int) $doc_id;
	}

	/**
	 * Builds one snippet from a slot's parts: CSS (in <style>), then HTML as
	 * written, then JavaScript (in <script>). Empty parts are skipped.
	 *
	 * @param array $slot Normalized slot.
	 * @return string
	 */
	public static function compose( array $slot ) {
		$out = array();
		if ( '' !== trim( $slot['css'] ) ) {
			$out[] = "<style>
" . $slot['css'] . "
</style>";
		}
		if ( '' !== trim( $slot['html'] ) ) {
			$out[] = $slot['html'];
		}
		if ( '' !== trim( $slot['js'] ) ) {
			$out[] = "<script>
" . $slot['js'] . "
</script>";
		}
		return implode( "
", $out );
	}

	/**
	 * ID of the singular post being viewed, if it supports per-post code.
	 *
	 * @return int
	 */
	private function current_post_id() {
		if ( ! is_singular() ) {
			return 0;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Post_Meta::supported_post_types(), true ) ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * Whether the current request is a normal HTML page view.
	 *
	 * @return bool
	 */
	public function should_inject() {
		$inject = true;

		if (
			is_admin()
			|| wp_doing_ajax()
			|| wp_doing_cron()
			|| wp_is_json_request()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			|| ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST )
			|| is_feed()
			|| is_embed()
			|| is_robots()
			|| is_trackback()
			|| ( function_exists( 'is_favicon' ) && is_favicon() )
		) {
			$inject = false;
		}

		if ( $inject && ! Settings::get()['run_in_builder_preview'] && self::is_builder_request() ) {
			$inject = false;
		}

		/**
		 * Filters whether code is injected into the current request.
		 *
		 * @param bool $inject Whether to inject.
		 */
		return (bool) apply_filters( 'ahfcm_should_inject', $inject );
	}

	/**
	 * Detects page-builder editor/preview requests from their public URL
	 * parameters (no builder internals are used).
	 *
	 * @return bool
	 */
	public static function is_builder_request() {
		/**
		 * Filters query parameters that mark a page-builder editor request.
		 *
		 * @param string[] $params Parameter names.
		 */
		$params = apply_filters(
			'ahfcm_builder_query_params',
			array( 'breakdance', 'breakdance_iframe', 'oxygen', 'oxygen_iframe', 'ct_builder', 'elementor-preview', 'fl_builder', 'et_fb', 'bricks' )
		);

		foreach ( (array) $params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presence check.
			if ( isset( $_GET[ $param ] ) ) {
				return true;
			}
		}
		return false;
	}
}
