<?php
/**
 * Front-end injection engine.
 *
 * Execution order inside each location:
 *
 *     1. Global code (Settings → Header & Footer Code)
 *     2. Code of the current singular post/page/CPT entry
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
		$post   = null;

		if ( null === $post_id ) {
			$post_id = $this->current_post_id();
		}
		if ( $post_id ) {
			$post = Post_Meta::get( $post_id );
		}

		$chunks = array();

		$suppress_global = $post && $post['enabled'] && $post['suppress_global'];
		if ( $global['enabled'] && ! $suppress_global ) {
			$chunks[] = array( 'global', $global['slots'][ $slot ] );
		}
		if ( $post && $post['enabled'] ) {
			$chunks[] = array( 'post', $post['slots'][ $slot ] );
		}

		$html = '';
		foreach ( $chunks as $chunk ) {
			list( $source, $data ) = $chunk;

			if ( ! $data['enabled'] || '' === trim( $data['code'] ) ) {
				continue;
			}

			$code = self::wrap( $data['code'], $data['language'] );

			/**
			 * Filters one snippet before output. Return '' to skip it.
			 *
			 * @param string $code    Snippet HTML.
			 * @param string $slot    head|footer.
			 * @param string $source  global|post.
			 * @param int    $post_id Current post ID or 0.
			 */
			$code = (string) apply_filters( 'ahfcm_snippet_output', $code, $slot, $source, (int) $post_id );
			if ( '' === $code ) {
				continue;
			}

			if ( $global['output_markers'] ) {
				$label = 'global' === $source ? 'global' : 'post ' . (int) $post_id;
				$code  = "\n<!-- AHFCM {$slot} ({$label}) -->\n" . $code . "\n<!-- /AHFCM {$slot} ({$label}) -->\n";
			} else {
				$code = "\n" . $code . "\n";
			}

			$html .= $code;
		}

		return $html;
	}

	/**
	 * Wraps pure CSS / JavaScript in the matching tag. HTML is output as-is.
	 *
	 * @param string $code     Code.
	 * @param string $language html|css|javascript.
	 * @return string
	 */
	public static function wrap( $code, $language ) {
		if ( 'css' === $language ) {
			return "<style>\n" . $code . "\n</style>";
		}
		if ( 'javascript' === $language ) {
			return "<script>\n" . $code . "\n</script>";
		}
		return $code;
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
