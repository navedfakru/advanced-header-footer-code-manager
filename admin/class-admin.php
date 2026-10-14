<?php
/**
 * Admin screens: global settings page, per-post meta box, tools.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class Admin {

	const PAGE_SLUG       = 'ahfcm';
	const META_BOX_ID     = 'ahfcm_code';
	const REPORT_TRANSIENT = 'ahfcm_report_';

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ahfcm_migrate', array( $this, 'handle_migration' ) );
		add_action( 'admin_post_ahfcm_restore', array( $this, 'handle_restore' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AHFCM_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds Settings → Header & Footer Code.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->page_hook = (string) add_options_page(
			__( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
			__( 'Header & Footer Code', 'advanced-header-footer-code-manager' ),
			Security::CAP_GLOBAL,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds the "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( Security::can_manage_global() ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'advanced-header-footer-code-manager' ) )
			);
		}
		return $links;
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Registers the per-post meta box for permitted users.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 * @return void
	 */
	public function add_meta_box( $post_type, $post ) {
		if ( ! in_array( $post_type, Post_Meta::supported_post_types(), true ) || ! $post instanceof \WP_Post || ! Security::can_edit_post_code( $post->ID ) ) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			__( 'Header & Body Code Embed', 'advanced-header-footer-code-manager' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Whether the current admin screen needs the editor assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( $hook ) {
		if ( $hook === $this->page_hook ) {
			return true;
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Post_Meta::supported_post_types(), true ) ) {
			return false;
		}

		$post_id = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : 0;
		return $post_id ? Security::can_edit_post_code( $post_id ) : current_user_can( 'unfiltered_html' );
	}

	/**
	 * Enqueues editor assets on plugin screens only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

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
			'javascript' => array(
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
							'wrappedTag' => __( 'This mode wraps your code in a tag automatically. Remove the tags or switch the language to HTML.', 'advanced-header-footer-code-manager' ),
							'needsTags'  => __( 'This looks like bare CSS or JavaScript. Switch the language so it is wrapped in the right tag, or add the tags yourself.', 'advanced-header-footer-code-manager' ),
						),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Language labels for selects and badges.
	 *
	 * @return array<string,string>
	 */
	public static function language_labels() {
		return array(
			'html'       => __( 'HTML', 'advanced-header-footer-code-manager' ),
			'css'        => __( 'CSS', 'advanced-header-footer-code-manager' ),
			'javascript' => __( 'JS', 'advanced-header-footer-code-manager' ),
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
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage header and footer code.', 'advanced-header-footer-code-manager' ) );
		}

		$settings          = Settings::get();
		$history           = Settings::get_history();
		$report            = $this->pull_report();
		$legacy_active     = Migration::legacy_active();
		$legacy_count      = count( Migration::find_legacy_posts() );
		$post_types        = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		include AHFCM_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Renders the per-post meta box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$data             = Post_Meta::get( $post->ID );
		$global           = Settings::get();
		$post_type_object = get_post_type_object( $post->post_type );
		$type_label       = $post_type_object ? $post_type_object->labels->singular_name : __( 'post', 'advanced-header-footer-code-manager' );

		include AHFCM_DIR . 'admin/views/meta-box.php';
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

	/**
	 * Runs the legacy import (dry run or real).
	 *
	 * @return void
	 */
	public function handle_migration() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-header-footer-code-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ahfcm_migrate' );

		$dry_run = ! isset( $_POST['ahfcm_run'] );
		$report  = Migration::run(
			array(
				'dry_run'     => $dry_run,
				'enable'      => ! empty( $_POST['enable'] ),
			)
		);

		set_transient(
			self::REPORT_TRANSIENT . get_current_user_id(),
			array(
				'type'    => 'migration',
				'dry_run' => $dry_run,
				'rows'    => $report,
			),
			10 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::page_url( array( 'ahfcm_notice' => 'migration' ) ) . '#tools' );
		exit;
	}

	/**
	 * Restores a global-code history entry.
	 *
	 * @return void
	 */
	public function handle_restore() {
		if ( ! Security::can_manage_global() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-header-footer-code-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ahfcm_restore' );

		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$history = Settings::get_history();
		$notice  = 'restore_failed';

		if ( isset( $history[ $index ]['value'] ) && is_array( $history[ $index ]['value'] ) ) {
			$current           = Settings::get();
			$restored          = $current;
			$restored['enabled'] = $history[ $index ]['value']['enabled'];
			$restored['slots']   = $history[ $index ]['value']['slots'];

			// The current version goes into history automatically (record_history).
			update_option( Settings::OPTION, Settings::normalize( $restored ) );
			$notice = 'restored';
		}

		wp_safe_redirect( self::page_url( array( 'ahfcm_notice' => $notice ) ) . '#tools' );
		exit;
	}

	/**
	 * Fetches and clears the current user's pending report.
	 *
	 * @return array|null
	 */
	private function pull_report() {
		$key    = self::REPORT_TRANSIENT . get_current_user_id();
		$report = get_transient( $key );
		if ( false === $report ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $report ) ? $report : null;
	}
}
