<?php
/**
 * Code editor assets: WordPress' bundled CodeMirror (via
 * wp_enqueue_code_editor), the js-beautify formatter, and the plugin's editor
 * CSS/JS with its localized config.
 *
 * Used on wp-admin screens and on builder pages, so it must not depend on
 * wp-admin-only code.
 *
 * @package AHFCM
 */

namespace AHFCM\Editor;

use AHFCM\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Editor asset loader.
 */
final class Assets {

	/**
	 * Enqueues the editor once per request.
	 *
	 * @return array|false Editor settings per language, or false when the user
	 *                     turned off syntax highlighting in their profile.
	 */
	public static function enqueue() {
		static $editors = null;
		if ( null !== $editors ) {
			return $editors;
		}

		$settings = Settings::get();
		$editors  = self::code_editors( 'dark' === $settings['editor_theme'] ? 'ahfcm-dark' : 'default' );

		wp_enqueue_style( 'ahfcm-editor', AHFCM_URL . 'assets/css/editor.css', false === $editors ? array() : array( 'code-editor' ), AHFCM_VERSION );

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
					'i18n'    => self::strings(),
				)
			) . ';',
			'before'
		);

		return $editors;
	}

	/**
	 * CodeMirror settings for HTML, CSS and JS (also enqueues CodeMirror,
	 * its addons and the linters).
	 *
	 * @param string $theme CodeMirror theme.
	 * @return array|false
	 */
	private static function code_editors( $theme ) {
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
			'theme'             => $theme,
		);

		$types = array(
			'html' => array(
				'type'       => 'text/html',
				'codemirror' => array(
					'mode'          => 'htmlmixed',
					'autoCloseTags' => true,
					'matchTags'     => array( 'bothTags' => true ),
				),
			),
			'css'  => array(
				'type'       => 'text/css',
				'codemirror' => array( 'mode' => 'css' ),
			),
			'js'   => array(
				'type'       => 'text/javascript',
				'codemirror' => array( 'mode' => 'javascript' ),
			),
		);

		$editors = array();
		foreach ( $types as $language => $args ) {
			$args['codemirror'] = array_merge( $common, $args['codemirror'] );
			$editor             = wp_enqueue_code_editor( $args );
			if ( false === $editor ) {
				return false;
			}
			$editors[ $language ] = $editor;
		}
		return $editors;
	}

	/**
	 * Translated UI strings for editor.js.
	 *
	 * @return array
	 */
	private static function strings() {
		return array(
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
			'languages'        => Fields::language_labels(),
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
		);
	}
}
