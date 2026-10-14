<?php
/**
 * Private REST endpoints used by the Breakdance builder panel.
 *
 *     GET|POST /ahfcm/v1/post/{id}   per-post code   (edit_post + unfiltered_html)
 *     GET|POST /ahfcm/v1/global      global code     (manage_options + unfiltered_html)
 *
 * Cookie authentication with the standard wp_rest nonce. Nothing here is
 * readable by visitors or by users without the code capabilities.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class Rest {

	const NAMESPACE_V1 = 'ahfcm/v1';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/post/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_post' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_post' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
					'args'                => array(
						'data' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/global',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_global' ),
					'permission_callback' => array( __NAMESPACE__ . '\\Security', 'can_manage_global' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_global' ),
					'permission_callback' => array( __NAMESPACE__ . '\\Security', 'can_manage_global' ),
					'args'                => array(
						'data' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Refuses saves from a builder panel loaded before a data-format upgrade,
	 * which would otherwise send the old field layout and wipe code.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_Error|null
	 */
	private function stale_client( $request ) {
		if ( (int) $request->get_param( 'schema' ) === Settings::SCHEMA_VERSION ) {
			return null;
		}
		return new \WP_Error(
			'ahfcm_stale_client',
			__( 'Not saved: this panel was loaded before the plugin was updated. Reload the builder and make your changes again.', 'advanced-header-footer-code-manager' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Permission check for per-post routes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_post( $request ) {
		return Security::can_edit_post_code( (int) $request['id'] );
	}

	/**
	 * Response shape for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function post_payload( $post_id ) {
		$post      = get_post( $post_id );
		$type      = get_post_type_object( $post->post_type );
		$supported = in_array( $post->post_type, Post_Meta::supported_post_types(), true );

		return array(
			'id'        => $post_id,
			'title'     => get_the_title( $post ),
			'type'      => $post->post_type,
			'typeLabel' => $type ? $type->labels->singular_name : $post->post_type,
			'supported' => $supported,
			'breakdance' => array_key_exists( $post->post_type, Breakdance::template_types() ),
			'editUrl'   => get_edit_post_link( $post_id, 'raw' ),
			'data'      => Post_Meta::get( $post_id ),
		);
	}

	/**
	 * GET /post/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_post( $request ) {
		return rest_ensure_response( $this->post_payload( (int) $request['id'] ) );
	}

	/**
	 * POST /post/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_post( $request ) {
		$stale = $this->stale_client( $request );
		if ( $stale ) {
			return $stale;
		}
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! in_array( $post->post_type, Post_Meta::supported_post_types(), true ) ) {
			return new \WP_Error( 'ahfcm_unsupported_type', __( 'Per-page code is not enabled for this content type.', 'advanced-header-footer-code-manager' ), array( 'status' => 400 ) );
		}

		$data = $request->get_param( 'data' );
		Post_Meta::save( $post_id, is_array( $data ) ? $data : array() );

		return rest_ensure_response( $this->post_payload( $post_id ) );
	}

	/**
	 * Global code as exposed to the builder (code fields only).
	 *
	 * @return array
	 */
	private function global_payload() {
		$settings = Settings::get();
		return array(
			'data'        => array(
				'enabled' => $settings['enabled'],
				'slots'   => $settings['slots'],
			),
			'settingsUrl' => admin_url( 'options-general.php?page=ahfcm' ),
		);
	}

	/**
	 * GET /global.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_global() {
		return rest_ensure_response( $this->global_payload() );
	}

	/**
	 * POST /global. Only the master switch and the code slots can change here;
	 * every other setting is kept.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_global( $request ) {
		$stale = $this->stale_client( $request );
		if ( $stale ) {
			return $stale;
		}
		$data     = (array) $request->get_param( 'data' );
		$settings = Settings::get();

		if ( isset( $data['enabled'] ) ) {
			$settings['enabled'] = Settings::to_bool( $data['enabled'] );
		}
		foreach ( Settings::SLOTS as $slot ) {
			if ( ! isset( $data['slots'][ $slot ] ) || ! is_array( $data['slots'][ $slot ] ) ) {
				continue;
			}
			foreach ( array( 'html', 'css', 'js', 'enabled' ) as $key ) {
				if ( array_key_exists( $key, $data['slots'][ $slot ] ) ) {
					$settings['slots'][ $slot ][ $key ] = $data['slots'][ $slot ][ $key ];
				}
			}
		}

		update_option( Settings::OPTION, Settings::normalize( $settings ) );
		Settings::flush_cache();

		return rest_ensure_response( $this->global_payload() );
	}
}
