<?php
/**
 * Front-end output: prints code in wp_head and wp_footer.
 *
 * Execution order inside each location:
 *
 *     1. Global code (Settings → Header & Footer Code)
 *     2. Builder documents used by the request (Breakdance/Oxygen header,
 *        template(s), footer, popups, global blocks) — see Integration\Loader
 *     3. Code of the current singular post/page/CPT entry
 *
 * Each snippet prints CSS (in <style>), then HTML, then JS (in <script>).
 * Default hook priority is 1000001 for both locations, right after the
 * builders' own CSS/JS (Breakdance/Oxygen use 1000000).
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

use AHFCM\Integration\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end injection engine.
 */
final class Injector {

	/**
	 * Slots already printed in this request (a theme may fire a hook twice).
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
	 * @param string   $slot    head|footer.
	 * @param int|null $post_id Render only this document (null = current request).
	 * @return string
	 */
	public function render( $slot, $post_id = null ) {
		$global = Settings::get();

		if ( null === $post_id ) {
			$page    = $this->current_post_id();
			$doc_ids = array_merge( Loader::documents_for_request( $page, $slot ), array( $page ) );
		} else {
			$doc_ids = array( (int) $post_id );
		}

		$docs = $this->load_documents( $doc_ids );

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

			/**
			 * Filters one snippet before output. Return '' to skip it.
			 *
			 * @param string $code    Snippet HTML.
			 * @param string $slot    head|footer.
			 * @param string $source  global|post.
			 * @param int    $post_id Document the snippet belongs to (0 for global).
			 */
			$code = (string) apply_filters( 'ahfcm_snippet_output', self::compose( $data ), $slot, $source, (int) $doc_id );
			if ( '' === $code ) {
				continue;
			}

			if ( $global['output_markers'] ) {
				$label = 'global' === $source ? 'global' : self::marker_label( $doc_id );
				$code  = "\n<!-- AHFCM {$slot} ({$label}) -->\n{$code}\n<!-- /AHFCM {$slot} ({$label}) -->\n";
			} else {
				$code = "\n{$code}\n";
			}
			$html .= $code;
		}

		return $html;
	}

	/**
	 * Normalized code of each supported document, keyed by ID (order kept).
	 *
	 * @param int[] $doc_ids Document IDs.
	 * @return array<int,array>
	 */
	private function load_documents( array $doc_ids ) {
		$supported = PostMeta::supported_post_types();
		$docs      = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $doc_ids ) ) ) as $doc_id ) {
			$type = get_post_type( $doc_id );
			if ( $type && in_array( $type, $supported, true ) ) {
				$docs[ $doc_id ] = PostMeta::get( $doc_id );
			}
		}
		return $docs;
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
			$out[] = "<style>\n" . $slot['css'] . "\n</style>";
		}
		if ( '' !== trim( $slot['html'] ) ) {
			$out[] = $slot['html'];
		}
		if ( '' !== trim( $slot['js'] ) ) {
			$out[] = "<script>\n" . $slot['js'] . "\n</script>";
		}
		return implode( "\n", $out );
	}

	/**
	 * Marker label: "post 81", or the post type for builder documents
	 * (e.g. "breakdance_header 12", "oxygen_template 5").
	 *
	 * @param int $doc_id Post ID.
	 * @return string
	 */
	private static function marker_label( $doc_id ) {
		$type = (string) get_post_type( $doc_id );
		return ( Loader::is_template_type( $type ) ? $type : 'post' ) . ' ' . (int) $doc_id;
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
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, PostMeta::supported_post_types(), true ) ) {
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
		$inject = ! (
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
		);

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
