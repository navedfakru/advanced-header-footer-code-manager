<?php
/**
 * Shared editor UI: assets, labels and the editor field partial.
 *
 * Used by wp-admin screens and by the Breakdance builder integration, so it
 * must not depend on wp-admin-only code.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Editor UI helpers.
 */
final class UI {

	/**
	 * Registers and enqueues the editor assets (CodeMirror via core,
	 * formatter, plugin CSS/JS) and the localized config.
	 *
	 * @return array|false Editor settings per language, or false when the
	 *                     user turned off syntax highlighting.
	 */
	public static function enqueue_editor() {
		$settings = Settings::get();
		$editors  = array();

		$common = array(
			'indentUnit'        => 2,
			'tabSize'           => 2,
			'indentWithTabs'    => false,
			'lineWrapping'      => false,
			'lineNumbers'       => true,
			'foldGutter'        => true,
			'gutters'           => array( 'CodeMirror-lint-markers', 'CodeMirror-linenumbers', 'CodeMirror-foldgutter' ),
			'matchBrackets'     => true,
			'autoCloseBrackets' => true,
			'styleActiveLine'   => true,
			'lint'              => true,
			'theme'             => 'dark' === $settings['editor_theme'] ? 'ahfcm-dark' : 'default',
		);

		$types = array(
			'html'       => array(
				'type'       => 'text/html',
				'codemirror' => array(
					'mode'          => 'htmlmixed',
					'autoCloseTags' => true,
					'matchTags'     => array( 'bothTags' => true ),
				),
			),
			'css'        => array(
				'type'       => 'text/css',
				'codemirror' => array( 'mode' => 'css' ),
			),
			'js'         => array(
				'type'       => 'text/javascript',
				'codemirror' => array( 'mode' => 'javascript' ),
			),
		);

		foreach ( $types as $language => $args ) {
			$args['codemirror'] = array_merge( $common, $args['codemirror'] );
			$editor_settings    = wp_enqueue_code_editor( $args );

			// False when the user turned off syntax highlighting in their profile.
			if ( false === $editor_settings ) {
				$editors = false;
				break;
			}
			$editors[ $language ] = $editor_settings;
		}

		wp_enqueue_style( 'ahfcm-admin', AHFCM_URL . 'assets/css/admin.css', false === $editors ? array() : array( 'code-editor' ), AHFCM_VERSION );

		wp_register_script( 'ahfcm-beautifier', AHFCM_URL . 'assets/vendor/js-beautify/beautifier.min.js', array(), '1.15.4', true );
		wp_register_script( 'ahfcm-core', AHFCM_URL . 'assets/js/core.js', array(), AHFCM_VERSION, true );
		wp_enqueue_script(
			'ahfcm-editor',
			AHFCM_URL . 'assets/js/editor.js',
			false === $editors ? array( 'jquery', 'wp-a11y', 'ahfcm-core', 'ahfcm-beautifier' ) : array( 'jquery', 'wp-a11y', 'code-editor', 'ahfcm-core', 'ahfcm-beautifier' ),
			AHFCM_VERSION,
			true
		);

		wp_add_inline_script(
			'ahfcm-editor',
			'window.AHFCM_EDITOR = ' . wp_json_encode(
				array(
					'editors' => $editors,
					'height'  => $settings['editor_height'],
					'theme'   => $settings['editor_theme'],
					'i18n'    => array(
						'formatted'        => __( 'Code formatted.', 'advanced-header-footer-code-manager' ),
						'unchanged'        => __( 'Code is already formatted.', 'advanced-header-footer-code-manager' ),
						'empty'            => __( 'Nothing to format.', 'advanced-header-footer-code-manager' ),
						'noFormatter'      => __( 'The formatter did not load, so the code was not changed.', 'advanced-header-footer-code-manager' ),
						'unsafeFormat'     => __( 'Formatting was cancelled because it would change more than whitespace. Your code was not modified.', 'advanced-header-footer-code-manager' ),
						'phpNotFormatted'  => __( 'This snippet contains PHP tags. PHP is not formatted or executed; the code was not changed.', 'advanced-header-footer-code-manager' ),
						/* translators: %s: error message from the formatter. */
						'formatError'      => __( 'The formatter failed: %s. Your code was not modified.', 'advanced-header-footer-code-manager' ),
						'noIssues'         => __( 'No problems found', 'advanced-header-footer-code-manager' ),
						/* translators: 1: number of errors, 2: number of warnings. */
						'issues'           => __( '%1$d error(s), %2$d warning(s)', 'advanced-header-footer-code-manager' ),
						'unsaved'          => __( 'You have unsaved code changes.', 'advanced-header-footer-code-manager' ),
						/* translators: %s: language name. */
						'languageSwitched' => __( 'Editor language changed to %s.', 'advanced-header-footer-code-manager' ),
						'exitFullscreen'   => __( 'Exit full screen', 'advanced-header-footer-code-manager' ),
						'fullscreen'       => __( 'Full screen', 'advanced-header-footer-code-manager' ),
						/* translators: 1: line number, 2: column number. */
						'cursor'           => __( 'Ln %1$d, Col %2$d', 'advanced-header-footer-code-manager' ),
						/* translators: %d: number of lines. */
						'lines'            => __( '%d lines', 'advanced-header-footer-code-manager' ),
						'languages'        => self::language_labels(),
						'pickColor'        => __( 'Pick color', 'advanced-header-footer-code-manager' ),
						'hue'              => __( 'Hue', 'advanced-header-footer-code-manager' ),
						'opacity'          => __( 'Opacity', 'advanced-header-footer-code-manager' ),
						'saturation'       => __( 'Saturation and brightness', 'advanced-header-footer-code-manager' ),
						'colorValue'       => __( 'Color value', 'advanced-header-footer-code-manager' ),
						'eyedropper'       => __( 'Pick from screen', 'advanced-header-footer-code-manager' ),
						'diagnostics'      => array(
							'php'        => __( 'PHP tags are not executed; they will be sent to the browser as text.', 'advanced-header-footer-code-manager' ),
							'wrappedTag' => __( 'This tab wraps your code in <style> or <script> for you. Remove the tags here, or move tag-based code to the HTML tab.', 'advanced-header-footer-code-manager' ),
							'needsTags'  => __( 'This looks like plain CSS or JavaScript. Put it in the CSS or JS tab, or wrap it in <style> / <script>.', 'advanced-header-footer-code-manager' ),
						),
					),
				)
			) . ';',
			'before'
		);

		return $editors;
	}

	/**
	 * Language labels for selects and badges.
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
	 * Location labels/descriptions.
	 *
	 * @return array<string,array{label:string,description:string}>
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
	 * Renders one editor field. Shared by both screens.
	 *
	 * @param string $id         Element ID.
	 * @param string $name_base  Form name prefix, e.g. ahfcm[slots][head].
	 * @param string $slot       Slot key.
	 * @param array  $data       Slot data (code, enabled, language).
	 * @param string $scope_note Short scope reminder.
	 * @return void
	 */
	public static function render_field( $id, $name_base, $slot, array $data, $scope_note ) {
		$labels    = self::slot_labels();
		$languages = self::language_labels();

		include AHFCM_DIR . 'admin/views/editor-field.php';
	}

}
