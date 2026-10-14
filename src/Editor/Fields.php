<?php
/**
 * Editor field rendering and labels, shared by the settings app, the meta box
 * and the panel.
 *
 * @package AHFCM
 */

namespace AHFCM\Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Code slot cards (HTML / CSS / JS tabs).
 */
final class Fields {

	/**
	 * Labels of a slot's code parts.
	 *
	 * @return array<string,string>
	 */
	public static function language_labels() {
		return array(
			'html' => __( 'HTML', 'advanced-header-footer-code-manager' ),
			'css'  => __( 'CSS', 'advanced-header-footer-code-manager' ),
			'js'   => __( 'JS', 'advanced-header-footer-code-manager' ),
		);
	}

	/**
	 * Location labels and descriptions.
	 *
	 * @return array<string,array{label:string,where:string,description:string}>
	 */
	public static function slot_labels() {
		return array(
			'head'   => array(
				'label'       => __( 'Header Code', 'advanced-header-footer-code-manager' ),
				'where'       => '<head>',
				'description' => __( 'Printed by wp_head, before </head>. Meta and verification tags, analytics loaders, preload hints, CSS.', 'advanced-header-footer-code-manager' ),
			),
			'footer' => array(
				'label'       => __( 'Footer Code', 'advanced-header-footer-code-manager' ),
				'where'       => '</body>',
				'description' => __( 'Printed by wp_footer, after footer scripts and before </body>. Chat widgets, tag-manager noscript, scripts that need the page loaded.', 'advanced-header-footer-code-manager' ),
			),
		);
	}

	/**
	 * Renders one slot card.
	 *
	 * @param string $id         Element ID prefix.
	 * @param string $name_base  Form name prefix, e.g. ahfcm[slots][head].
	 * @param string $slot       head|footer.
	 * @param array  $data       Slot data (html, css, js, enabled, placement, priority).
	 * @param string $scope_note Short scope reminder.
	 * @param string $context    post|global|global_mirror (see render_placement()).
	 * @return void
	 */
	public static function render_slot( $id, $name_base, $slot, array $data, $scope_note, $context = 'post' ) {
		$labels    = self::slot_labels();
		$languages = self::language_labels();

		include AHFCM_DIR . 'views/editor/slot.php';
	}

	/**
	 * Renders the Position control of one slot.
	 *
	 * @param string $id        Element ID prefix.
	 * @param string $name_base Form name prefix, e.g. ahfcm[slots][head].
	 * @param string $slot      head|footer.
	 * @param array  $data      Slot data (placement, priority).
	 * @param string $context   post (can follow the global position), global
	 *                          (saved), global_mirror (unsaved copy synced with
	 *                          the global control by editor.js).
	 * @return void
	 */
	public static function render_placement( $id, $name_base, $slot, array $data, $context = 'post' ) {
		include AHFCM_DIR . 'views/editor/placement.php';
	}
}
