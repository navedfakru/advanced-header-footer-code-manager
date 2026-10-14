<?php
/**
 * Breakdance builder integration.
 *
 * Adds a "Header & Footer Code" button to the builder's top bar (after the
 * properties button). It opens a panel to edit the current document's code
 * and the global code, saved through the plugin's REST API.
 *
 * The builder page does not run wp_head()/wp_footer(), so assets are printed
 * on Breakdance's builder footer action — the same approach WindPress uses.
 * Only public WordPress APIs and Breakdance's documented builder hook are
 * used; no Breakdance internals.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Builder integration.
 */
final class Breakdance {

	/**
	 * Breakdance prints this after the builder app scripts.
	 *
	 * @see wp-content/plugins/breakdance/plugin/loader/loader.php
	 */
	const BUILDER_HOOK = 'unofficial_i_am_kevin_geary_master_of_all_things_css_and_html';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::BUILDER_HOOK, array( $this, 'print_builder_assets' ), 1000002 );
	}

	/**
	 * Request cache for active_document_ids().
	 *
	 * @var int[]|null
	 */
	private static $active_ids = null;

	/**
	 * Breakdance template-like post types that can carry their own code.
	 *
	 * Global blocks are included: their code runs on every page that embeds
	 * them (see global_block_ids()).
	 *
	 * @return array<string,string> Post type => label.
	 */
	public static function template_types() {
		$map = array(
			'BREAKDANCE_TEMPLATE_POST_TYPE' => __( 'Templates', 'advanced-header-footer-code-manager' ),
			'BREAKDANCE_HEADER_POST_TYPE'   => __( 'Headers', 'advanced-header-footer-code-manager' ),
			'BREAKDANCE_FOOTER_POST_TYPE'   => __( 'Footers', 'advanced-header-footer-code-manager' ),
			'BREAKDANCE_POPUP_POST_TYPE'    => __( 'Popups', 'advanced-header-footer-code-manager' ),
			'BREAKDANCE_BLOCK_POST_TYPE'    => __( 'Global Blocks', 'advanced-header-footer-code-manager' ),
		);

		$types = array();
		foreach ( $map as $constant => $label ) {
			if ( defined( $constant ) && post_type_exists( constant( $constant ) ) ) {
				$types[ constant( $constant ) ] = $label;
			}
		}
		return $types;
	}

	/**
	 * Template types enabled in settings (all by default).
	 *
	 * @return string[]
	 */
	public static function enabled_template_types() {
		$available = array_keys( self::template_types() );
		$selected  = Settings::get()['breakdance_types'];
		return null === $selected ? $available : array_values( array_intersect( $available, $selected ) );
	}

	/**
	 * IDs of the Breakdance templates, header, footer and popups that render
	 * the current front-end request, in output order.
	 *
	 * Resolved with the same functions Breakdance's own admin-bar menu uses.
	 * Any failure (e.g. a future Breakdance change) returns an empty list,
	 * so global and per-page code keep working.
	 *
	 * @return int[]
	 */
	public static function active_document_ids() {
		if ( null !== self::$active_ids ) {
			return self::$active_ids;
		}

		$ids     = array();
		$enabled = self::enabled_template_types();

		if ( $enabled && class_exists( '\Breakdance\Themeless\ThemelessController' ) && function_exists( '\Breakdance\Themeless\_getTemplateForRequest' ) ) {
			try {
				$controller = \Breakdance\Themeless\ThemelessController::getInstance();
				$single     = static function ( $list ) {
					$found = is_array( $list ) ? \Breakdance\Themeless\_getTemplateForRequest( $list ) : false;
					return is_array( $found ) && isset( $found['id'] ) ? array( (int) $found['id'] ) : array();
				};

				if ( defined( 'BREAKDANCE_HEADER_POST_TYPE' ) && in_array( BREAKDANCE_HEADER_POST_TYPE, $enabled, true ) ) {
					$ids = array_merge( $ids, $single( $controller->headers ) );
				}

				if ( defined( 'BREAKDANCE_TEMPLATE_POST_TYPE' ) && in_array( BREAKDANCE_TEMPLATE_POST_TYPE, $enabled, true ) ) {
					// Templates actually used for this request (includes nested templates).
					$hierarchy = $controller->templateHierarchyForRequest;
					if ( ! is_array( $hierarchy ) || ! $hierarchy ) {
						$hierarchy = $controller->originalTemplateHierarchyForRequest;
					}
					$ids = array_merge( $ids, is_array( $hierarchy ) && $hierarchy ? array_map( 'intval', $hierarchy ) : $single( $controller->templates ) );
				}

				if ( defined( 'BREAKDANCE_FOOTER_POST_TYPE' ) && in_array( BREAKDANCE_FOOTER_POST_TYPE, $enabled, true ) ) {
					$ids = array_merge( $ids, $single( $controller->footers ) );
				}

				if ( defined( 'BREAKDANCE_POPUP_POST_TYPE' ) && in_array( BREAKDANCE_POPUP_POST_TYPE, $enabled, true ) && function_exists( '\Breakdance\Themeless\_getAllTemplatesForRequest' ) && is_array( $controller->popups ) ) {
					foreach ( \Breakdance\Themeless\_getAllTemplatesForRequest( $controller->popups ) as $popup ) {
						if ( isset( $popup['id'] ) ) {
							$ids[] = (int) $popup['id'];
						}
					}
				}
			} catch ( \Throwable $e ) {
				$ids = array();
			}
		}

		/**
		 * Filters the Breakdance documents whose code applies to this request.
		 *
		 * @param int[] $ids Post IDs, in output order.
		 */
		$ids = (array) apply_filters( 'ahfcm_breakdance_active_documents', array_values( array_unique( array_filter( $ids ) ) ) );

		// Only cache once the main query has run, so early calls don't stick.
		if ( did_action( 'wp' ) ) {
			self::$active_ids = $ids;
		}
		return $ids;
	}

	/**
	 * Global blocks used by the given documents.
	 *
	 * Head code is printed before the body renders, so block usage is found by
	 * scanning the documents' Breakdance trees (and nested blocks). For the
	 * footer, blocks Breakdance actually rendered are added as well, which also
	 * covers blocks inserted through shortcodes or dynamic content.
	 *
	 * @param int[]  $doc_ids Documents rendering this request.
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public static function global_block_ids( array $doc_ids, $slot ) {
		if ( ! defined( 'BREAKDANCE_BLOCK_POST_TYPE' ) || ! in_array( BREAKDANCE_BLOCK_POST_TYPE, self::enabled_template_types(), true ) ) {
			return array();
		}

		$found = array();
		$queue = array_values( array_filter( array_map( 'intval', $doc_ids ) ) );
		$seen  = array();
		$depth = 0;

		// Breadth-first through nested blocks, bounded to avoid reference loops.
		while ( $queue && $depth < 6 ) {
			$next = array();
			foreach ( $queue as $id ) {
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				foreach ( self::block_refs_in( $id ) as $block_id ) {
					if ( ! in_array( $block_id, $found, true ) ) {
						$found[] = $block_id;
						$next[]  = $block_id;
					}
				}
			}
			$queue = $next;
			++$depth;
		}

		if ( 'footer' === $slot && class_exists( '\Breakdance\Render\BlockCounterManager' ) ) {
			try {
				$rendered = \Breakdance\Render\BlockCounterManager::getInstance()->getBlocks();
				foreach ( array_keys( (array) $rendered ) as $block_id ) {
					if ( ! in_array( (int) $block_id, $found, true ) ) {
						$found[] = (int) $block_id;
					}
				}
			} catch ( \Throwable $e ) {
				// Keep the scanned list.
			}
		}

		$found = array_values(
			array_filter(
				$found,
				static function ( $id ) {
					return BREAKDANCE_BLOCK_POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id );
				}
			)
		);

		/**
		 * Filters the global blocks whose code applies to this request.
		 *
		 * @param int[]  $found   Block post IDs.
		 * @param int[]  $doc_ids Documents rendering the request.
		 * @param string $slot    head|footer.
		 */
		return (array) apply_filters( 'ahfcm_breakdance_global_blocks', $found, $doc_ids, $slot );
	}

	/**
	 * Global block IDs referenced directly in one document's Breakdance tree.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function block_refs_in( $post_id ) {
		return self::refs_in( $post_id )['blocks'];
	}

	/**
	 * Global blocks and popups referenced in one document's Breakdance tree.
	 *
	 * - Global Block element: properties.content.content.block
	 * - Loops / menu custom areas: any `global_block` key
	 * - "Open popup" actions (buttons, links, interactions, forms): any
	 *   `popupId` key, e.g. action.popupOptions.popupId
	 *
	 * @param int $post_id Post ID.
	 * @return array{blocks:int[],popups:int[]}
	 */
	private static function refs_in( $post_id ) {
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$blocks = array();
		$popups = array();
		$raw    = get_post_meta( $post_id, '_breakdance_data', true );
		$data   = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		$tree   = is_array( $data ) && isset( $data['tree_json_string'] ) ? json_decode( (string) $data['tree_json_string'], true ) : null;

		if ( is_array( $tree ) ) {
			$walk = static function ( $node ) use ( &$walk, &$blocks, &$popups ) {
				if ( ! is_array( $node ) ) {
					return;
				}
				if ( isset( $node['type'] ) && is_string( $node['type'] ) && 0 === strcasecmp( substr( $node['type'], -12 ), '\GlobalBlock' ) && isset( $node['properties']['content']['content']['block'] ) ) {
					$blocks[] = (int) $node['properties']['content']['content']['block'];
				}
				foreach ( $node as $key => $value ) {
					if ( 'global_block' === $key && is_scalar( $value ) ) {
						$blocks[] = (int) $value;
					} elseif ( 'popupId' === $key && is_scalar( $value ) ) {
						$popups[] = (int) $value;
					} elseif ( is_array( $value ) ) {
						$walk( $value );
					}
				}
			};
			$walk( $tree );
		}

		$cache[ $post_id ] = array(
			'blocks' => array_values( array_unique( array_filter( $blocks ) ) ),
			'popups' => array_values( array_unique( array_filter( $popups ) ) ),
		);
		return $cache[ $post_id ];
	}

	/**
	 * Popups opened by the given documents (buttons, links, interactions,
	 * forms), including popups opened from inside other popups.
	 *
	 * These render even when the popup's own display rules are disabled, so
	 * their code must run too. Head code relies on scanning the documents'
	 * Breakdance data; footer code also adds every popup Breakdance actually
	 * rendered (it prints popups in wp_footer before this plugin's code).
	 *
	 * @param int[]  $doc_ids Documents rendering this request.
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public static function referenced_popup_ids( array $doc_ids, $slot ) {
		if ( ! defined( 'BREAKDANCE_POPUP_POST_TYPE' ) || ! in_array( BREAKDANCE_POPUP_POST_TYPE, self::enabled_template_types(), true ) ) {
			return array();
		}

		$found = array();
		$queue = array_values( array_filter( array_map( 'intval', $doc_ids ) ) );
		$seen  = array();
		$depth = 0;
		while ( $queue && $depth < 6 ) {
			$next = array();
			foreach ( $queue as $id ) {
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				foreach ( self::refs_in( $id )['popups'] as $popup_id ) {
					if ( ! in_array( $popup_id, $found, true ) ) {
						$found[] = $popup_id;
						$next[]  = $popup_id;
					}
				}
			}
			$queue = $next;
			++$depth;
		}

		if ( 'footer' === $slot && class_exists( '\\Breakdance\\Themeless\\PopupController' ) ) {
			try {
				$controller = \Breakdance\Themeless\PopupController::getInstance();
				foreach ( array_merge( (array) $controller->rendered, (array) $controller->popups ) as $popup_id ) {
					if ( ! in_array( (int) $popup_id, $found, true ) ) {
						$found[] = (int) $popup_id;
					}
				}
			} catch ( \Throwable $e ) {
				// Keep the scanned list.
			}
		}

		$found = array_values(
			array_filter(
				$found,
				static function ( $id ) {
					return BREAKDANCE_POPUP_POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id );
				}
			)
		);

		/**
		 * Filters popups opened by the current request's documents.
		 *
		 * @param int[]  $found   Popup post IDs.
		 * @param int[]  $doc_ids Documents rendering the request.
		 * @param string $slot    head|footer.
		 */
		return (array) apply_filters( 'ahfcm_breakdance_referenced_popups', $found, $doc_ids, $slot );
	}

	/**
	 * Clears the request cache (tests).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$active_ids = null;
	}

	/**
	 * Whether this request renders the Breakdance builder UI.
	 *
	 * @return bool
	 */
	public static function is_builder() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
		return defined( '__BREAKDANCE_VERSION' ) && isset( $_GET['breakdance'] ) && 'builder' === $_GET['breakdance'];
	}

	/**
	 * ID of the document opened in the builder (0 when none).
	 *
	 * @return int
	 */
	private static function document_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Roles that could ever see the button (they can publish raw HTML).
	 *
	 * @return array<string,string> Role slug => name.
	 */
	public static function eligible_roles() {
		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( ! empty( $role['capabilities']['unfiltered_html'] ) ) {
				$roles[ $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $roles;
	}

	/**
	 * Users who could ever see the button.
	 *
	 * @return \WP_User[]
	 */
	public static function eligible_users() {
		$roles = array_keys( self::eligible_roles() );
		if ( ! $roles ) {
			return array();
		}
		return get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'display_name',
				'number'   => 200,
			)
		);
	}

	/**
	 * Whether the builder button is shown to a user.
	 *
	 * Visibility only: hidden users keep whatever code access their
	 * capabilities give them in wp-admin.
	 *
	 * @param \WP_User|null $user  User (default current).
	 * @param string        $where builder (Breakdance) or editor (block editor).
	 * @return bool
	 */
	public static function button_visible_for( $user = null, $where = 'builder' ) {
		$user     = $user ? $user : wp_get_current_user();
		$settings = Settings::get();
		$visible  = true;
		$switch   = 'editor' === $where ? 'editor_button' : 'builder_button';

		if ( ! $settings[ $switch ] || ! $user || ! $user->exists() ) {
			$visible = false;
		} elseif ( in_array( (int) $user->ID, $settings['builder_button_hide_users'], true ) ) {
			$visible = false;
		} elseif ( null !== $settings['builder_button_roles'] && ! array_intersect( (array) $user->roles, $settings['builder_button_roles'] ) ) {
			// Super admins on multisite have no role on the site; let them through.
			$visible = is_multisite() && is_super_admin( $user->ID );
		}

		/**
		 * Filters whether the Header & Footer Code button shows in the builder.
		 *
		 * @param bool     $visible Visible.
		 * @param \WP_User $user    User.
		 * @param string   $where   builder|editor.
		 */
		return (bool) apply_filters( 'ahfcm_breakdance_button_visible', $visible, $user, $where );
	}

	/**
	 * Prints styles, scripts and the panel template on the builder page.
	 *
	 * @return void
	 */
	public function print_builder_assets() {
		/**
		 * Filters whether the builder integration loads.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! self::is_builder() || ! apply_filters( 'ahfcm_breakdance_integration', true ) || ! self::button_visible_for() ) {
			return;
		}

		$doc_id     = self::document_id();
		$can_post   = $doc_id && Security::can_edit_post_code( $doc_id );
		$can_global = Security::can_manage_global();
		if ( ! $can_post && ! $can_global ) {
			return;
		}

		$scripts_before = wp_scripts()->queue;
		$styles_before  = wp_styles()->queue;

		self::enqueue_panel( $doc_id, $can_global, 'builder' );

		$this->print_template( $can_post, $can_global );

		// Print only what this plugin added (plus dependencies).
		$new_styles  = array_values( array_diff( wp_styles()->queue, $styles_before ) );
		$new_scripts = array_values( array_diff( wp_scripts()->queue, $scripts_before ) );
		wp_print_styles( $new_styles );
		wp_print_scripts( $new_scripts );
	}

	/**
	 * Enqueues the panel (editor assets, builder.js/css and its config).
	 * Shared by the Breakdance builder and the block editor.
	 *
	 * @param int    $doc_id     Document whose code the "This page" tab edits.
	 * @param bool   $can_global Whether the Global tab is available.
	 * @param string $mode       builder|editor.
	 * @return void
	 */
	public static function enqueue_panel( $doc_id, $can_global, $mode ) {
		UI::enqueue_editor();
		wp_enqueue_style( 'ahfcm-builder', AHFCM_URL . 'assets/css/builder.css', array( 'ahfcm-admin' ), AHFCM_VERSION );
		wp_enqueue_script( 'ahfcm-builder', AHFCM_URL . 'assets/js/builder.js', array( 'ahfcm-editor' ), AHFCM_VERSION, true );
		wp_add_inline_script(
			'ahfcm-builder',
			'window.AHFCM_BUILDER = ' . wp_json_encode(
				array(
					'mode'      => 'editor' === $mode ? 'editor' : 'builder',
					'restUrl'   => esc_url_raw( rest_url( Rest::NAMESPACE_V1 . '/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'docId'     => (int) $doc_id,
					'canGlobal' => (bool) $can_global,
					'schema'    => Settings::SCHEMA_VERSION,
					'i18n'      => array(
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
						'noAccess'    => __( 'You cannot edit code for this document.', 'advanced-header-footer-code-manager' ),
						'thisPage'    => __( 'This page', 'advanced-header-footer-code-manager' ),
						/* translators: %s: Breakdance document type, e.g. "template". */
						'thisType'    => __( 'This %s', 'advanced-header-footer-code-manager' ),
						/* translators: %s: content type, e.g. "page" or "template". */
						'enableOn'    => __( 'Custom code on this %s', 'advanced-header-footer-code-manager' ),
						'scopePage'   => __( 'This page only', 'advanced-header-footer-code-manager' ),
						/* translators: %s: Breakdance document type, e.g. "template". */
						'scopeType'   => __( 'Every page using this %s', 'advanced-header-footer-code-manager' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Prints the panel <template> (used by the block editor's admin footer).
	 *
	 * @param bool $can_post   Whether the "This page" tab is shown.
	 * @param bool $can_global Whether the Global tab is shown.
	 * @return void
	 */
	public static function render_panel_template( $can_post, $can_global ) {
		( new self() )->print_template( $can_post, $can_global );
	}

	/**
	 * Prints the panel markup inside a <template> (cloned on first open).
	 *
	 * @param bool $can_post   Whether the user may edit the document's code.
	 * @param bool $can_global Whether the user may edit global code.
	 * @return void
	 */
	private function print_template( $can_post, $can_global ) {
		$settings = Settings::get();
		$empty    = Post_Meta::defaults();

		include AHFCM_DIR . 'admin/views/builder-panel.php';
	}
}
