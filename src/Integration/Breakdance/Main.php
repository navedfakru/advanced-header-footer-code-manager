<?php
/**
 * Breakdance integration (also the shared engine for Oxygen 6, which runs on
 * the same code base — see Integration\Oxygen\Main).
 *
 * - Templates, headers, footers, popups and global blocks can carry their
 *   own code, which runs on every page that uses them.
 * - A toolbar button in the builder opens the Header & Footer Code panel
 *   (see Editor).
 *
 * Template detection uses the same functions the builder's own admin-bar menu
 * uses. Any failure (e.g. a future builder change) yields no documents, so
 * global and per-page code keep working.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration\Breakdance;

use AHFCM\Core\Settings;
use AHFCM\Integration\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Breakdance-engine integration.
 */
class Main implements IntegrationInterface {

	/**
	 * Request cache for layout documents.
	 *
	 * @var int[]|null
	 */
	private $layout_ids = null;

	/**
	 * Request cache for tree references, keyed by post ID.
	 *
	 * @var array<int,array{blocks:int[],popups:int[]}>
	 */
	private $refs = array();

	/**
	 * Value of BREAKDANCE_MODE this integration handles.
	 *
	 * @return string
	 */
	public function get_mode() {
		return 'breakdance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'breakdance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Breakdance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Code on templates, headers, footers, popups and global blocks, plus a code button in the builder top bar.', 'advanced-header-footer-code-manager' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Official logo, copied from the builder's own files (builder/dist/favicon.svg).
	 */
	public function get_icon_url() {
		return AHFCM_URL . 'assets/images/integrations/' . $this->get_name() . '.svg';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'BREAKDANCE_MODE' ) && $this->get_mode() === BREAKDANCE_MODE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register() {
		( new Editor( $this ) )->register();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_template_types() {
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
	 * {@inheritDoc}
	 *
	 * Order: header, template(s), footer, popups, then referenced popups and
	 * global blocks used by any of them or by the page.
	 */
	public function get_documents_for_request( $page_id, $slot ) {
		$layout = $this->layout_document_ids();
		$scan   = array_merge( $layout, array( (int) $page_id ) );
		$blocks = $this->global_block_ids( $scan, $slot );
		$popups = $this->referenced_popup_ids( array_merge( $scan, $blocks ), $slot );

		return array_values( array_unique( array_merge( $layout, $popups, $blocks ) ) );
	}

	/**
	 * Whether a template type is switched on in Settings.
	 *
	 * @param string $constant Builder constant, e.g. BREAKDANCE_HEADER_POST_TYPE.
	 * @return bool
	 */
	private function type_enabled( $constant ) {
		if ( ! defined( $constant ) ) {
			return false;
		}
		$selected = Settings::get()['breakdance_types'];
		return null === $selected || in_array( constant( $constant ), $selected, true );
	}

	/**
	 * Header, template(s), footer and rule-matched popups rendering the
	 * request, in output order.
	 *
	 * @return int[]
	 */
	public function layout_document_ids() {
		if ( null !== $this->layout_ids ) {
			return $this->layout_ids;
		}

		$ids = array();
		if ( class_exists( '\Breakdance\Themeless\ThemelessController' ) && function_exists( '\Breakdance\Themeless\_getTemplateForRequest' ) ) {
			try {
				$controller = \Breakdance\Themeless\ThemelessController::getInstance();
				$single     = static function ( $list ) {
					$found = is_array( $list ) ? \Breakdance\Themeless\_getTemplateForRequest( $list ) : false;
					return is_array( $found ) && isset( $found['id'] ) ? array( (int) $found['id'] ) : array();
				};

				if ( $this->type_enabled( 'BREAKDANCE_HEADER_POST_TYPE' ) ) {
					$ids = array_merge( $ids, $single( $controller->headers ) );
				}

				if ( $this->type_enabled( 'BREAKDANCE_TEMPLATE_POST_TYPE' ) ) {
					// Templates actually used for this request (includes nested templates).
					$hierarchy = $controller->templateHierarchyForRequest;
					if ( ! is_array( $hierarchy ) || ! $hierarchy ) {
						$hierarchy = $controller->originalTemplateHierarchyForRequest;
					}
					$ids = array_merge( $ids, is_array( $hierarchy ) && $hierarchy ? array_map( 'intval', $hierarchy ) : $single( $controller->templates ) );
				}

				if ( $this->type_enabled( 'BREAKDANCE_FOOTER_POST_TYPE' ) ) {
					$ids = array_merge( $ids, $single( $controller->footers ) );
				}

				if ( $this->type_enabled( 'BREAKDANCE_POPUP_POST_TYPE' ) && function_exists( '\Breakdance\Themeless\_getAllTemplatesForRequest' ) && is_array( $controller->popups ) ) {
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
		 * Filters the builder documents whose code applies to this request.
		 *
		 * @param int[] $ids Post IDs, in output order.
		 */
		$ids = (array) apply_filters( 'ahfcm_breakdance_active_documents', array_values( array_unique( array_filter( $ids ) ) ) );

		// Only cache once the main query has run, so early calls don't stick.
		if ( did_action( 'wp' ) ) {
			$this->layout_ids = $ids;
		}
		return $ids;
	}

	/**
	 * Global blocks used by the given documents, including nested blocks.
	 *
	 * Head code prints before the body renders, so usage is found by scanning
	 * the documents' builder data. For the footer, blocks the builder actually
	 * rendered (shortcodes, dynamic content) are added too.
	 *
	 * @param int[]  $doc_ids Documents rendering this request.
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public function global_block_ids( array $doc_ids, $slot ) {
		if ( ! $this->type_enabled( 'BREAKDANCE_BLOCK_POST_TYPE' ) ) {
			return array();
		}

		$found = $this->collect_refs( $doc_ids, 'blocks' );

		if ( 'footer' === $slot && class_exists( '\Breakdance\Render\BlockCounterManager' ) ) {
			try {
				foreach ( array_keys( (array) \Breakdance\Render\BlockCounterManager::getInstance()->getBlocks() ) as $block_id ) {
					$found[] = (int) $block_id;
				}
			} catch ( \Throwable $e ) {
				// Keep the scanned list.
			}
		}

		$found = $this->published_of_type( $found, BREAKDANCE_BLOCK_POST_TYPE );

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
	 * Popups opened by the given documents (buttons, links, interactions,
	 * forms), including popups opened from inside other popups. They render
	 * even when the popup's own display rules are disabled.
	 *
	 * @param int[]  $doc_ids Documents rendering this request.
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public function referenced_popup_ids( array $doc_ids, $slot ) {
		if ( ! $this->type_enabled( 'BREAKDANCE_POPUP_POST_TYPE' ) ) {
			return array();
		}

		$found = $this->collect_refs( $doc_ids, 'popups' );

		if ( 'footer' === $slot && class_exists( '\Breakdance\Themeless\PopupController' ) ) {
			try {
				$controller = \Breakdance\Themeless\PopupController::getInstance();
				foreach ( array_merge( (array) $controller->rendered, (array) $controller->popups ) as $popup_id ) {
					$found[] = (int) $popup_id;
				}
			} catch ( \Throwable $e ) {
				// Keep the scanned list.
			}
		}

		$found = $this->published_of_type( $found, BREAKDANCE_POPUP_POST_TYPE );

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
	 * Breadth-first collection of block or popup references, following nested
	 * references (bounded to avoid loops).
	 *
	 * @param int[]  $doc_ids Start documents.
	 * @param string $kind    blocks|popups.
	 * @return int[]
	 */
	private function collect_refs( array $doc_ids, $kind ) {
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
				foreach ( $this->refs_in( $id )[ $kind ] as $ref ) {
					if ( ! in_array( $ref, $found, true ) ) {
						$found[] = $ref;
						$next[]  = $ref;
					}
				}
			}
			$queue = $next;
			++$depth;
		}
		return $found;
	}

	/**
	 * Unique published IDs of one post type.
	 *
	 * @param int[]  $ids       IDs.
	 * @param string $post_type Post type.
	 * @return int[]
	 */
	private function published_of_type( array $ids, $post_type ) {
		return array_values(
			array_filter(
				array_unique( array_map( 'intval', $ids ) ),
				static function ( $id ) use ( $post_type ) {
					return $post_type === get_post_type( $id ) && 'publish' === get_post_status( $id );
				}
			)
		);
	}

	/**
	 * Global blocks and popups referenced in one document's builder tree.
	 *
	 * - Global Block element: properties.content.content.block
	 * - Loops / menu custom areas: any `global_block` key
	 * - "Open popup" actions: any `popupId` key
	 *
	 * @param int $post_id Post ID.
	 * @return array{blocks:int[],popups:int[]}
	 */
	private function refs_in( $post_id ) {
		if ( isset( $this->refs[ $post_id ] ) ) {
			return $this->refs[ $post_id ];
		}

		$blocks = array();
		$popups = array();
		$tree   = $this->tree( $post_id );

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

		$this->refs[ $post_id ] = array(
			'blocks' => array_values( array_unique( array_filter( $blocks ) ) ),
			'popups' => array_values( array_unique( array_filter( $popups ) ) ),
		);
		return $this->refs[ $post_id ];
	}

	/**
	 * A document's builder tree.
	 *
	 * Uses the engine's own reader when available; it knows the meta key
	 * (`_breakdance_data` for Breakdance, `_oxygen_data` for Oxygen). The
	 * fallback reads that key directly.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function tree( $post_id ) {
		if ( function_exists( '\Breakdance\Data\get_tree' ) ) {
			try {
				$tree = \Breakdance\Data\get_tree( $post_id );
				return is_array( $tree ) ? $tree : null;
			} catch ( \Throwable $e ) {
				// Fall through to the direct read.
			}
		}

		$bdox   = '\Breakdance\BreakdanceOxygen\Strings\__bdox';
		$prefix = function_exists( $bdox ) ? (string) call_user_func( $bdox, '_meta_prefix' ) : '_' . $this->get_mode() . '_';
		$raw    = get_post_meta( $post_id, $prefix . 'data', true );
		$data   = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		$tree   = is_array( $data ) && isset( $data['tree_json_string'] ) ? json_decode( (string) $data['tree_json_string'], true ) : null;
		return is_array( $tree ) ? $tree : null;
	}

	/**
	 * Clears request caches (tests).
	 *
	 * @return void
	 */
	public function flush_cache() {
		$this->layout_ids = null;
		$this->refs       = array();
	}
}
