<?php
/**
 * Integration verification suite.
 *
 * Run against a LOCAL or STAGING site (it creates and then deletes temporary
 * posts and users, and temporarily replaces the global settings):
 *
 *     wp eval-file wp-content/plugins/advanced-header-footer-code-manager/tests/verify.php
 *
 * Exit status is non-zero when any check fails. Front-end checks fetch real
 * pages over HTTP from home_url(), so the site must be reachable from the
 * machine running WP-CLI.
 *
 * @package AHFCM
 */

// phpcs:disable WordPress.Security, WordPress.WP.GlobalVariablesOverride, WordPress.PHP.DevelopmentFunctions

use AHFCM\Frontend;
use AHFCM\Post_Meta;
use AHFCM\Settings;
use AHFCM\Migration;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run with: wp eval-file tests/verify.php\n" );
}
if ( ! class_exists( 'AHFCM\\Plugin' ) ) {
	WP_CLI::error( 'Activate Advanced Header & Footer Code Manager first.' );
}

$GLOBALS['ahfcm_results'] = array( 'pass' => 0, 'fail' => 0 );

/**
 * Records one check.
 *
 * @param string $name Check name.
 * @param bool   $ok   Result.
 * @param string $info Extra info on failure.
 */
function ahfcm_check( $name, $ok, $info = '' ) {
	global $ahfcm_results;
	if ( $ok ) {
		++$ahfcm_results['pass'];
		WP_CLI::line( "  PASS  {$name}" );
	} else {
		++$ahfcm_results['fail'];
		WP_CLI::line( "  FAIL  {$name}" . ( $info ? " — {$info}" : '' ) );
	}
}

/**
 * Fetches a front-end URL as an anonymous visitor.
 *
 * @param string $url URL.
 * @return string Body ('' on error).
 */
function ahfcm_fetch( $url ) {
	$url      = add_query_arg( 'ahfcm_nocache', wp_generate_password( 8, false ), $url );
	$response = wp_remote_get( $url, array( 'timeout' => 30, 'sslverify' => false, 'redirection' => 3 ) );
	return is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
}

/**
 * Counts occurrences of a needle.
 */
function ahfcm_count( $haystack, $needle ) {
	return substr_count( $haystack, $needle );
}

/**
 * Simulates the meta box POST and runs the save handler.
 *
 * @param int    $post_id Post ID.
 * @param array  $fields  ahfcm[...] fields.
 * @param string $nonce   Nonce ('' = generate valid one for current user).
 */
function ahfcm_submit_meta_box( $post_id, array $fields, $nonce = '' ) {
	if ( ! array_key_exists( '_schema', $fields ) ) {
		$fields['_schema'] = Settings::SCHEMA_VERSION;
	} elseif ( null === $fields['_schema'] ) {
		unset( $fields['_schema'] );
	}
	$_POST = array(
		Post_Meta::NONCE_FIELD => '' === $nonce ? wp_create_nonce( Post_Meta::NONCE_PREFIX . $post_id ) : $nonce,
		Post_Meta::FORM_FIELD  => wp_slash( $fields ),
	);
	( new Post_Meta() )->save_from_request( $post_id, get_post( $post_id ) );
	$_POST = array();
}

$ahfcm_original_option = get_option( Settings::OPTION );
$ahfcm_original_hist   = get_option( Settings::HISTORY_OPTION, null );
$ahfcm_created_posts   = array();
$ahfcm_created_users   = array();
$ahfcm_admin_id        = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );

try {
	wp_set_current_user( $ahfcm_admin_id );

	// Code with characters that commonly get mangled: backslashes, quotes,
	// </script> inside strings, unicode, JSON-LD, CRLF line endings.
	$tricky = "<script>\r\n  var re = /\\d+\\.\\w*/g; var s = \"it's \\\"quoted\\\"\"; var t = '<\\/script>'; // ünïcødé ✓\r\n</script>\r\n<script type=\"application/ld+json\">{\"@context\":\"https://schema.org\",\"name\":\"A \\\\ B\"}</script>";

	WP_CLI::line( 'Setup' );
	$page_a = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM test page A' ) );
	$page_b = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM test page B' ) );
	$post_a = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'AHFCM test post A' ) );
	$ahfcm_created_posts = array( $page_a, $page_b, $post_a );
	$cpt     = post_type_exists( 'event' ) ? 'event' : null;
	$event_a = 0;
	if ( $cpt ) {
		$event_a               = wp_insert_post( array( 'post_type' => $cpt, 'post_status' => 'publish', 'post_title' => 'AHFCM test event' ) );
		$ahfcm_created_posts[] = $event_a;
	}

	update_option(
		Settings::OPTION,
		Settings::normalize(
			array(
				'slots'          => array(
					'head'      => array( 'code' => '<meta name="ahfcm-test" content="G-HEAD">' ),
					'footer'    => array( 'code' => 'console.log("G-FOOT");', 'language' => 'javascript' ),
				),
				'output_markers' => true,
			)
		)
	);
	ahfcm_check( 'Global settings saved (3 slots)', Settings::has_code( Settings::get() ) );

	ahfcm_submit_meta_box(
		$page_a,
		array(
			'enabled' => '1',
			'slots'   => array(
				'head'      => array( 'code' => 'body{--ahfcm:"P-HEAD"}', 'language' => 'css', 'enabled' => '1' ),
				'footer'    => array( 'code' => "<!-- P-FOOT -->
" . $tricky, 'language' => 'html', 'enabled' => '1' ),
			),
		)
	);
	ahfcm_submit_meta_box(
		$post_a,
		array(
			'slots' => array(
				'head' => array( 'code' => '<!-- POST-HEAD -->', 'enabled' => '1' ),
			),
		)
	);
	if ( $event_a ) {
		ahfcm_submit_meta_box( $event_a, array( 'slots' => array( 'footer' => array( 'code' => '<!-- CPT-FOOT -->' ) ) ) );
	}

	WP_CLI::line( 'Storage' );
	wp_cache_flush();
	Settings::flush_cache();
	$stored = Post_Meta::get( $page_a );
	ahfcm_check( '8/14 Per-post code persists and is stored byte-exact (CRLF → LF only)', Settings::normalize_code( "<!-- P-FOOT -->
" . $tricky ) === $stored['slots']['footer']['html'] );
	ahfcm_check( '19 Saving post code did not change global code', '<meta name="ahfcm-test" content="G-HEAD">' === Settings::get()['slots']['head']['html'] );
	$raw_meta = get_post_meta( $page_a, Post_Meta::META_KEY, true );
	ahfcm_check( 'Meta stored under a single protected key', is_array( $raw_meta ) && is_protected_meta( Post_Meta::META_KEY, 'post' ) );
	ahfcm_check( 'Legacy meta keys untouched', ! metadata_exists( 'post', $page_a, '_page_header_code' ) );

	$before_global = Post_Meta::get( $page_a );
	$g             = Settings::get();
	$g['slots']['head']['html'] .= "\n<!-- G-HEAD-2 -->";
	update_option( Settings::OPTION, $g );
	ahfcm_check( '19 Saving global code did not change post code', Post_Meta::get( $page_a ) === $before_global );
	ahfcm_check( 'Previous global version recorded in history', count( Settings::get_history() ) >= 1 );

	WP_CLI::line( 'Front end (HTTP)' );
	$html_a = ahfcm_fetch( get_permalink( $page_a ) );
	$html_b = ahfcm_fetch( get_permalink( $page_b ) );
	$html_p = ahfcm_fetch( get_permalink( $post_a ) );

	ahfcm_check( 'Page A fetched', false !== strpos( $html_a, '</html>' ), 'empty or truncated response' );

	$head_open  = strpos( $html_a, '<head' );
	$head_close = strpos( $html_a, '</head>' );
	$g_head     = strpos( $html_a, 'content="G-HEAD"' );
	ahfcm_check( '1 Global header code is inside <head>', false !== $g_head && $g_head > $head_open && $g_head < $head_close );


	$body_close = strrpos( $html_a, '</body>' );
	$g_foot     = strpos( $html_a, 'console.log("G-FOOT");' );
	ahfcm_check( '3 Global footer code is before </body>', false !== $g_foot && $g_foot < $body_close && $g_foot > $head_close );
	ahfcm_check( 'JavaScript-mode code is wrapped in <script>', (bool) preg_match( '#<script>\s*console\.log\("G-FOOT"\);\s*</script>#', $html_a ) );

	$p_head = strpos( $html_a, '--ahfcm:"P-HEAD"' );
	ahfcm_check( '4 Page code appears on its page (head, CSS wrapped in <style>)', false !== $p_head && $p_head < $head_close && (bool) preg_match( '#<style>\s*body\{--ahfcm:"P-HEAD"\}\s*</style>#', $html_a ) );
	ahfcm_check( '4 Page code does not appear on another page', false === strpos( $html_b, 'P-HEAD' ) && false === strpos( $html_b, 'P-FOOT' ) );
	ahfcm_check( '5 Post code appears on its post', false !== strpos( $html_p, '<!-- POST-HEAD -->' ) );
	ahfcm_check( '5 Post code does not appear on pages', false === strpos( $html_a, 'POST-HEAD' ) && false === strpos( $html_b, 'POST-HEAD' ) );
	if ( $event_a ) {
		$html_e = ahfcm_fetch( get_permalink( $event_a ) );
		ahfcm_check( 'Public custom post type (event) code is output', false !== strpos( $html_e, '<!-- CPT-FOOT -->' ) );
	}

	ahfcm_check( '6 Global + page code both appear, global first', $g_head < $p_head && strpos( $html_a, 'G-FOOT' ) < strpos( $html_a, 'P-FOOT' ) );
	ahfcm_check( '6 Global code still appears on page without page code', false !== strpos( $html_b, 'G-HEAD' ) && false !== strpos( $html_b, 'G-FOOT' ) );

	ahfcm_check( '14 Tricky code is output byte-exact', false !== strpos( $html_a, Settings::normalize_code( $tricky ) ) );

	ahfcm_check( '15 No duplicate output (each marker once)', 1 === ahfcm_count( $html_a, 'content="G-HEAD"' ) && 1 === ahfcm_count( $html_a, 'G-FOOT' ) && 1 === ahfcm_count( $html_a, 'P-HEAD' ) && 1 === ahfcm_count( $html_a, '<!-- P-FOOT -->' ) );

	ahfcm_check( 'Body-start slot is gone: nothing printed on wp_body_open', ! has_action( 'wp_body_open' ) || false === strpos( $html_a, 'AHFCM body_open' ) );
	$legacy_shape                               = Settings::get();
	$legacy_shape['slots']['body_open']['html'] = '<!-- OLD-1.0-BODY -->';
	update_option( Settings::OPTION, $legacy_shape );
	ahfcm_check( 'Stored 1.0.0 body-start data is ignored, not output', ! isset( Settings::get()['slots']['body_open'] ) && false === strpos( ahfcm_fetch( get_permalink( $page_b ) ), 'OLD-1.0-BODY' ) );

	WP_CLI::line( 'HTML + CSS + JS together' );
	$multi_page            = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM multi' ) );
	$ahfcm_created_posts[] = $multi_page;
	ahfcm_submit_meta_box(
		$multi_page,
		array(
			'slots' => array(
				'head'   => array(
					'html'    => '<meta name="ahfcm-multi" content="M-HTML">',
					'css'     => '.m-css{color:red}',
					'js'      => 'window.M_JS=1;',
					'enabled' => '1',
				),
				'footer' => array(
					'html'    => '<div id="m-foot-html"></div>',
					'css'     => '#m-foot-html{color:blue}',
					'js'      => 'console.log("M-FOOT-JS");',
					'enabled' => '1',
				),
			),
		)
	);
	$m      = Post_Meta::get( $multi_page );
	$html_m = ahfcm_fetch( get_permalink( $multi_page ) );
	ahfcm_check( 'All three parts are stored separately', '.m-css{color:red}' === $m['slots']['head']['css'] && 'window.M_JS=1;' === $m['slots']['head']['js'] && '<meta name="ahfcm-multi" content="M-HTML">' === $m['slots']['head']['html'] );
	ahfcm_check( 'Head prints CSS (in <style>), then HTML, then JS (in <script>)', (bool) preg_match( '#<style>\s*\.m-css\{color:red\}\s*</style>\s*<meta name="ahfcm-multi" content="M-HTML">\s*<script>\s*window\.M_JS=1;\s*</script>#', $html_m ) && strpos( $html_m, 'M_JS' ) < strpos( $html_m, '</head>' ) );
	ahfcm_check( 'Footer prints all three parts before </body>', (bool) preg_match( '#<style>\s*\#m-foot-html\{color:blue\}\s*</style>\s*<div id="m-foot-html"></div>\s*<script>\s*console\.log\("M-FOOT-JS"\);\s*</script>#', $html_m ) && strpos( $html_m, 'M-FOOT-JS' ) < strrpos( $html_m, '</body>' ) );
	ahfcm_check( 'Each part prints once', 1 === ahfcm_count( $html_m, '.m-css{color:red}' ) && 1 === ahfcm_count( $html_m, 'M-FOOT-JS' ) );
	$legacy_slot = Settings::normalize_slot( array( 'code' => 'a{b:c}', 'language' => 'css', 'enabled' => true ), Settings::slot_defaults( 'head' ) );
	$legacy_js   = Settings::normalize_slot( array( 'code' => 'x()', 'language' => 'javascript' ), Settings::slot_defaults( 'head' ) );
	ahfcm_check( 'Old one-language data converts into the matching part', 'a{b:c}' === $legacy_slot['css'] && '' === $legacy_slot['html'] && 'x()' === $legacy_js['js'] );

	WP_CLI::line( 'Enable / disable' );
	ahfcm_submit_meta_box(
		$page_a,
		array(
			'enabled'         => '1',
			'suppress_global' => '1',
			'slots'           => array(
				'head'      => array( 'code' => 'body{--ahfcm:"P-HEAD"}', 'language' => 'css', 'enabled' => '0' ),
				'footer'    => array( 'code' => "<!-- P-FOOT -->
" . $tricky, 'enabled' => '1' ),
			),
		)
	);
	$html_a2 = ahfcm_fetch( get_permalink( $page_a ) );
	ahfcm_check( '7 Disabled page slot is not injected (code kept)', false === strpos( $html_a2, 'P-HEAD' ) && false !== strpos( Post_Meta::get( $page_a )['slots']['head']['css'], 'P-HEAD' ) );
	ahfcm_check( '7 "Do not load global code" suppresses global code on that page only', false === strpos( $html_a2, 'G-HEAD' ) && false !== strpos( $html_a2, 'P-FOOT' ) && false !== strpos( ahfcm_fetch( get_permalink( $page_b ) ), 'G-HEAD' ) );

	ahfcm_submit_meta_box( $page_a, array( 'enabled' => '0', 'slots' => array( 'footer' => array( 'code' => '<!-- P-FOOT -->' ) ) ) );
	$html_a3 = ahfcm_fetch( get_permalink( $page_a ) );
	ahfcm_check( '7 Page master switch off disables page code and its global opt-out', false === strpos( $html_a3, 'P-FOOT' ) && false !== strpos( $html_a3, 'G-HEAD' ) );

	$g                                = Settings::get();
	$g['slots']['footer']['enabled'] = false;
	update_option( Settings::OPTION, $g );
	$html_b2 = ahfcm_fetch( get_permalink( $page_b ) );
	ahfcm_check( '7 Disabled global slot is not injected', false === strpos( $html_b2, 'G-FOOT' ) && false !== strpos( $html_b2, 'G-HEAD' ) );

	$g['enabled'] = false;
	update_option( Settings::OPTION, $g );
	$html_b3 = ahfcm_fetch( get_permalink( $page_b ) );
	ahfcm_check( '7 Global master switch off removes all global code', false === strpos( $html_b3, 'G-HEAD' ) && false === strpos( $html_b3, 'G-FOOT' ) );
	$g['enabled']                      = true;
	$g['slots']['footer']['enabled'] = true;
	update_option( Settings::OPTION, $g );

	WP_CLI::line( 'Non-HTML contexts' );
	$feed = ahfcm_fetch( get_feed_link() );
	ahfcm_check( '16 Feed has no snippets', '' !== $feed && false === strpos( $feed, '<!-- AHFCM' ) && false === strpos( $feed, 'G-HEAD' ) );
	$rest = ahfcm_fetch( rest_url( 'wp/v2/pages/' . $page_b ) );
	ahfcm_check( '16 REST response has no snippets', '' !== $rest && false === strpos( $rest, 'G-HEAD' ) );
	$rest_a = ahfcm_fetch( rest_url( 'wp/v2/pages/' . $page_a ) );
	ahfcm_check( 'REST does not expose per-post code', '' !== $rest_a && false === strpos( $rest_a, '_ahfcm_settings' ) && false === strpos( $rest_a, 'P-FOOT' ) );
	$ajax = wp_remote_retrieve_body( wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'body' => array( 'action' => 'heartbeat' ), 'sslverify' => false ) ) );
	ahfcm_check( '16 admin-ajax response has no snippets', false === strpos( (string) $ajax, 'G-HEAD' ) );
	$builder = ahfcm_fetch( add_query_arg( 'breakdance_iframe', '1', get_permalink( $page_b ) ) );
	ahfcm_check( 'Builder preview request gets no snippets (default)', false === strpos( $builder, 'G-HEAD' ) );

	WP_CLI::line( 'In-process engine' );
	$frontend = new Frontend();
	ob_start();
	$frontend->print_head();
	$frontend->print_head();
	$twice = ob_get_clean();
	ahfcm_check( '15 Hook firing twice prints once', 1 === ahfcm_count( $twice, 'content="G-HEAD"' ) );

	$errors = array();
	set_error_handler(
		function ( $no, $str ) use ( &$errors ) {
			$errors[] = $str;
			return true;
		}
	);
	$no_meta_post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM no meta' ) );
	$ahfcm_created_posts[] = $no_meta_post;
	update_post_meta( $no_meta_post, Post_Meta::META_KEY, 'garbage-not-an-array' );
	$out1 = $frontend->render( 'head', $no_meta_post );
	delete_post_meta( $no_meta_post, Post_Meta::META_KEY );
	$out2 = $frontend->render( 'footer', $no_meta_post );
	update_option( Settings::OPTION, 'garbage' );
	Settings::flush_cache();
	$out3 = $frontend->render( 'head', 999999999 );
	restore_error_handler();
	ahfcm_check( '12 Missing/malformed meta and option raise no PHP warnings', empty( $errors ), implode( '; ', $errors ) );
	ahfcm_check( '12 Malformed option falls back to safe defaults', '' === $out3 && is_array( Settings::get()['slots'] ) );
	update_option( Settings::OPTION, $g );


	WP_CLI::line( 'Security' );
	ahfcm_submit_meta_box( $page_b, array( 'slots' => array( 'head' => array( 'code' => '<!-- BAD-NONCE -->' ) ) ), 'invalid-nonce' );
	ahfcm_check( '11 Invalid nonce is rejected', false === strpos( Post_Meta::get( $page_b )['slots']['head']['html'], 'BAD-NONCE' ) );

	$author_id             = wp_insert_user( array( 'user_login' => 'ahfcm_author_' . wp_rand(), 'user_pass' => wp_generate_password( 24 ), 'role' => 'author' ) );
	$editor_id             = wp_insert_user( array( 'user_login' => 'ahfcm_editor_' . wp_rand(), 'user_pass' => wp_generate_password( 24 ), 'role' => 'editor' ) );
	$subscriber_id         = wp_insert_user( array( 'user_login' => 'ahfcm_sub_' . wp_rand(), 'user_pass' => wp_generate_password( 24 ), 'role' => 'subscriber' ) );
	$ahfcm_created_users   = array( $author_id, $editor_id, $subscriber_id );
	$author_post           = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'AHFCM author post', 'post_author' => $author_id ) );
	$ahfcm_created_posts[] = $author_post;

	wp_set_current_user( $author_id );
	ahfcm_check( '10 Author can edit own post but lacks code capability', current_user_can( 'edit_post', $author_post ) && ! current_user_can( 'ahfcm_edit_post_code', $author_post ) );
	ahfcm_submit_meta_box( $author_post, array( 'slots' => array( 'head' => array( 'code' => '<script>alert(1)</script>' ) ) ) );
	ahfcm_check( '10 Author (no unfiltered_html) cannot save code, even with a valid nonce', '' === Post_Meta::get( $author_post )['slots']['head']['html'] );
	ahfcm_check( '10 Author cannot manage global code', ! current_user_can( 'ahfcm_manage_global' ) );

	wp_set_current_user( $subscriber_id );
	ahfcm_submit_meta_box( $page_b, array( 'slots' => array( 'head' => array( 'code' => '<!-- SUB -->' ) ) ) );
	ahfcm_check( '10 Subscriber cannot save code', false === strpos( Post_Meta::get( $page_b )['slots']['head']['html'], 'SUB' ) );

	wp_set_current_user( $editor_id );
	$editor_can = current_user_can( 'unfiltered_html' );
	ahfcm_check( '10 Editor (has unfiltered_html on single site) can edit post code but not global code', ! $editor_can || ( current_user_can( 'ahfcm_edit_post_code', $page_b ) && ! current_user_can( 'ahfcm_manage_global' ) ) );

	wp_set_current_user( $ahfcm_admin_id );
	ahfcm_check( 'Administrator can manage global code', current_user_can( 'ahfcm_manage_global' ) );
	ahfcm_check( 'Global option is not exposed in REST settings', false === strpos( ahfcm_fetch( rest_url( 'wp/v2/settings' ) ), 'ahfcm' ) );

	WP_CLI::line( 'REST API (Breakdance panel)' );
	do_action( 'rest_api_init' );
	$ahfcm_rest = function ( $method, $route, $body = null ) {
		$request = new WP_REST_Request( $method, '/ahfcm/v1/' . $route );
		if ( null !== $body ) {
			if ( ! array_key_exists( 'schema', $body ) ) {
				$body['schema'] = Settings::SCHEMA_VERSION;
			} elseif ( null === $body['schema'] ) {
				unset( $body['schema'] );
			}
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	};
	wp_set_current_user( $ahfcm_admin_id );
	$res = $ahfcm_rest( 'GET', 'post/' . $page_b );
	ahfcm_check( 'REST GET post returns code for admin', 200 === $res->get_status() && true === $res->get_data()['supported'] && isset( $res->get_data()['data']['slots']['head'] ) );
	$res = $ahfcm_rest( 'POST', 'post/' . $page_b, array( 'data' => array( 'enabled' => true, 'slots' => array( 'footer' => array( 'code' => "<script>var r=/\d+/;</script>", 'enabled' => true, 'language' => 'html' ) ) ) ) );
	ahfcm_check( 'REST POST post saves byte-exact code (backslashes kept)', 200 === $res->get_status() && "<script>var r=/\d+/;</script>" === Post_Meta::get( $page_b )['slots']['footer']['html'] );
	$before_global = Settings::get();
	$res           = $ahfcm_rest( 'POST', 'global', array( 'data' => array( 'enabled' => true, 'slots' => array( 'head' => array( 'html' => '<!-- REST-GLOBAL -->' ) ) ) ) );
	$after_global  = Settings::get();
	ahfcm_check( 'REST POST global updates code but keeps other settings', 200 === $res->get_status() && '<!-- REST-GLOBAL -->' === $after_global['slots']['head']['html'] && $after_global['slots']['head']['priority'] === $before_global['slots']['head']['priority'] && $after_global['editor_theme'] === $before_global['editor_theme'] && $after_global['slots']['footer'] === $before_global['slots']['footer'] );
	if ( post_type_exists( 'breakdance_template' ) ) {
		WP_CLI::line( 'Breakdance templates' );
		$template_id           = wp_insert_post( array( 'post_type' => 'breakdance_template', 'post_status' => 'publish', 'post_title' => 'AHFCM tpl' ) );
		$ahfcm_created_posts[] = $template_id;
		$res                   = $ahfcm_rest( 'POST', 'post/' . $template_id, array( 'data' => array( 'slots' => array( 'head' => array( 'code' => '<!-- TPL-SAVE -->' ) ) ) ) );
		ahfcm_check( 'REST saves code on a Breakdance template (enabled by default)', 200 === $res->get_status() && true === $res->get_data()['breakdance'] && '<!-- TPL-SAVE -->' === Post_Meta::get( $template_id )['slots']['head']['html'] );

		// Find the template Breakdance actually uses for a single post.
		$probe = ahfcm_fetch( get_permalink( $post_a ) );
		$used  = 0;
		foreach ( get_posts( array( 'post_type' => 'breakdance_template', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'publish' ) ) as $tid ) {
			if ( (int) $tid === (int) $template_id ) {
				continue;
			}
			$backup = get_post_meta( $tid, Post_Meta::META_KEY, true );
			Post_Meta::save( $tid, array( 'slots' => array( 'footer' => array( 'code' => '<!-- TPL-' . $tid . ' -->' ) ) ) );
			$html = ahfcm_fetch( get_permalink( $post_a ) );
			if ( '' === $backup ) {
				delete_post_meta( $tid, Post_Meta::META_KEY );
			} else {
				update_post_meta( $tid, Post_Meta::META_KEY, wp_slash( $backup ) );
			}
			if ( false !== strpos( $html, '<!-- TPL-' . $tid . ' -->' ) ) {
				$used = (int) $tid;
				break;
			}
		}
		if ( $used ) {
			Post_Meta::save( $used, array( 'slots' => array( 'footer' => array( 'code' => '<!-- TPL-USED -->' ) ) ) );
			$html_post = ahfcm_fetch( get_permalink( $post_a ) );
			ahfcm_check( 'Template code is output on a post rendered by that template, after global code', false !== strpos( $html_post, '<!-- AHFCM footer (breakdance_template ' . $used . ') -->' ) && strpos( $html_post, 'G-FOOT' ) < strpos( $html_post, 'TPL-USED' ) );
			ahfcm_check( 'Template code is not output on pages that use another template', false === strpos( ahfcm_fetch( get_permalink( $page_b ) ), 'TPL-USED' ) );
			$g                     = Settings::get();
			$g['breakdance_types'] = array();
			update_option( Settings::OPTION, $g );
			ahfcm_check( 'Unticking "Breakdance templates" stops template code', false === strpos( ahfcm_fetch( get_permalink( $post_a ) ), 'TPL-USED' ) );
			$g['breakdance_types'] = null;
			update_option( Settings::OPTION, $g );
			delete_post_meta( $used, Post_Meta::META_KEY );
		} else {
			WP_CLI::line( '  SKIP  No published Breakdance template renders single posts on this site' );
		}
	}

	if ( post_type_exists( 'breakdance_block' ) ) {
		WP_CLI::line( 'Breakdance global blocks' );
		$block_id              = wp_insert_post( array( 'post_type' => 'breakdance_block', 'post_status' => 'publish', 'post_title' => 'AHFCM block' ) );
		$nested_id             = wp_insert_post( array( 'post_type' => 'breakdance_block', 'post_status' => 'publish', 'post_title' => 'AHFCM nested block' ) );
		$host_page             = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM block host' ) );
		$ahfcm_created_posts[] = $block_id;
		$ahfcm_created_posts[] = $nested_id;
		$ahfcm_created_posts[] = $host_page;
		$tree = function ( $children ) {
			return wp_slash( wp_json_encode( array( 'tree_json_string' => wp_json_encode( array( 'root' => array( 'id' => 1, 'data' => array( 'type' => 'root' ), 'children' => $children ) ) ) ) ) );
		};
		update_post_meta( $host_page, '_breakdance_data', $tree( array( array( 'id' => 2, 'data' => array( 'type' => 'EssentialElements\GlobalBlock', 'properties' => array( 'content' => array( 'content' => array( 'block' => $block_id ) ) ) ), 'children' => array() ) ) ) );
		update_post_meta( $block_id, '_breakdance_data', $tree( array( array( 'id' => 3, 'data' => array( 'type' => 'EssentialElements\PostsLoop', 'properties' => array( 'content' => array( 'repeated_block' => array( 'global_block' => $nested_id ) ) ) ), 'children' => array() ) ) ) );
		ahfcm_check( 'Global blocks found in a page tree, including nested blocks', array( $block_id, $nested_id ) === \AHFCM\Breakdance::global_block_ids( array( $host_page ), 'head' ) );
		ahfcm_check( 'No global blocks for a page that uses none', array() === \AHFCM\Breakdance::global_block_ids( array( $page_b ), 'head' ) );
		$res = $ahfcm_rest( 'POST', 'post/' . $nested_id, array( 'data' => array( 'slots' => array( 'head' => array( 'code' => '<!-- NESTED-BLOCK -->' ) ) ) ) );
		ahfcm_check( 'REST saves code on a global block', 200 === $res->get_status() );
		$html_host = ahfcm_fetch( get_permalink( $host_page ) );
		ahfcm_check( 'Nested global block head code is output on the page using it', false !== strpos( $html_host, '<!-- AHFCM head (breakdance_block ' . $nested_id . ') -->' ) && strpos( $html_host, '<!-- NESTED-BLOCK -->' ) < strpos( $html_host, '</head>' ) );
		ahfcm_check( 'Global block code is not output on unrelated pages', false === strpos( ahfcm_fetch( get_permalink( $page_b ) ), 'NESTED-BLOCK' ) );
	}
	wp_set_current_user( $author_id );
	ahfcm_check( 'REST denies author (no unfiltered_html) read and write', 403 === $ahfcm_rest( 'GET', 'post/' . $author_post )->get_status() && 403 === $ahfcm_rest( 'POST', 'post/' . $author_post, array( 'data' => array() ) )->get_status() );
	wp_set_current_user( $editor_id );
	ahfcm_check( 'REST denies global code to editors', in_array( $ahfcm_rest( 'GET', 'global' )->get_status(), array( 401, 403 ), true ) );
	wp_set_current_user( 0 );
	ahfcm_check( 'REST denies anonymous visitors', 401 === $ahfcm_rest( 'GET', 'post/' . $page_b )->get_status() && 401 === $ahfcm_rest( 'GET', 'global' )->get_status() );
	wp_set_current_user( $ahfcm_admin_id );

	if ( post_type_exists( 'breakdance_popup' ) ) {
		WP_CLI::line( 'Popups opened by a button' );
		$popup_id              = wp_insert_post( array( 'post_type' => 'breakdance_popup', 'post_status' => 'publish', 'post_title' => 'AHFCM popup' ) );
		$button_page           = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM popup host' ) );
		$ahfcm_created_posts[] = $popup_id;
		$ahfcm_created_posts[] = $button_page;
		// Popup with its own display rules disabled: it only appears when opened.
		update_post_meta( $popup_id, '_breakdance_template_settings', wp_slash( wp_json_encode( array( 'type' => 'everywhere', 'ruleGroups' => array(), 'priority' => 1, 'triggers' => array(), 'disabled' => true ) ) ) );
		update_post_meta(
			$button_page,
			'_breakdance_data',
			wp_slash( wp_json_encode( array( 'tree_json_string' => wp_json_encode( array( 'root' => array( 'id' => 1, 'data' => array( 'type' => 'root' ), 'children' => array( array( 'id' => 2, 'data' => array( 'type' => 'EssentialElements\\Button', 'properties' => array( 'content' => array( 'content' => array( 'link' => array( 'type' => 'action', 'action' => array( 'type' => 'popup', 'popupOptions' => array( 'popupId' => (string) $popup_id ) ) ) ) ) ) ), 'children' => array() ) ) ) ) ) ) ) )
		);
		Post_Meta::save( $popup_id, array( 'slots' => array( 'head' => array( 'css' => '.ahfcm-popup-test{}' ) ) ) );
		ahfcm_check( 'Popup referenced by a button is detected', array( $popup_id ) === \AHFCM\Breakdance::referenced_popup_ids( array( $button_page ), 'head' ) );
		ahfcm_check( 'Pages without the button reference no popups', array() === \AHFCM\Breakdance::referenced_popup_ids( array( $page_b ), 'head' ) );
		$html_btn = ahfcm_fetch( get_permalink( $button_page ) );
		ahfcm_check( 'Opened popup head code loads even when its display rules are disabled', false !== strpos( $html_btn, '<!-- AHFCM head (breakdance_popup ' . $popup_id . ') -->' ) && strpos( $html_btn, '.ahfcm-popup-test{}' ) < strpos( $html_btn, '</head>' ) );
		ahfcm_check( 'Popup code does not load on unrelated pages', false === strpos( ahfcm_fetch( get_permalink( $page_b ) ), 'ahfcm-popup-test' ) );
	}

	WP_CLI::line( 'Builder button visibility' );
	wp_set_current_user( $ahfcm_admin_id );
	$admin_user = wp_get_current_user();
	$bd         = new \AHFCM\Breakdance();
	$builder_html = function () use ( $bd ) {
		$_GET['breakdance'] = 'builder';
		$_GET['id']         = 0;
		ob_start();
		$bd->print_builder_assets();
		$out = ob_get_clean();
		unset( $_GET['breakdance'], $_GET['id'] );
		return $out;
	};
	$gb = Settings::get();
	ahfcm_check( 'Button visible by default for administrators', \AHFCM\Breakdance::button_visible_for( $admin_user ) );
	if ( defined( '__BREAKDANCE_VERSION' ) ) {
		ahfcm_check( 'Builder page prints the panel when visible', false !== strpos( $builder_html(), 'ahfcm-bd-template' ) );
	}
	$gb['builder_button'] = false;
	update_option( Settings::OPTION, $gb );
	ahfcm_check( 'Toggle off hides the button for everyone', ! \AHFCM\Breakdance::button_visible_for( $admin_user ) );
	if ( defined( '__BREAKDANCE_VERSION' ) ) {
		ahfcm_check( 'Builder page prints nothing when the button is off', '' === $builder_html() );
	}
	$gb['builder_button']       = true;
	$gb['builder_button_roles'] = array( 'editor' );
	update_option( Settings::OPTION, $gb );
	ahfcm_check( 'Role condition hides it for roles not ticked (administrator)', ! \AHFCM\Breakdance::button_visible_for( $admin_user ) );
	ahfcm_check( 'Role condition shows it for ticked roles (editor)', \AHFCM\Breakdance::button_visible_for( get_userdata( $editor_id ) ) );
	$gb['builder_button_roles']      = null;
	$gb['builder_button_hide_users'] = array( (int) $ahfcm_admin_id );
	update_option( Settings::OPTION, $gb );
	ahfcm_check( 'Hidden user does not see it', ! \AHFCM\Breakdance::button_visible_for( $admin_user ) );
	ahfcm_check( 'Other users still see it', \AHFCM\Breakdance::button_visible_for( get_userdata( $editor_id ) ) );
	ahfcm_check( 'Hiding the button does not remove code access in wp-admin', current_user_can( 'ahfcm_manage_global' ) );
	$gb['builder_button_hide_users'] = array();
	update_option( Settings::OPTION, $gb );
	$norm = Settings::normalize( array( 'builder_button_roles' => array( '', 'administrator' ), 'builder_button_hide_users' => array( '', '5', 'x' ) ) );
	ahfcm_check( 'Form values are sanitized (empty markers dropped, IDs as integers)', array( 'administrator' ) === $norm['builder_button_roles'] && array( 5 ) === $norm['builder_button_hide_users'] );

	WP_CLI::line( 'Block editor toolbar button' );
	require_once AHFCM_DIR . 'admin/class-admin.php';
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	wp_set_current_user( $ahfcm_admin_id );
	$toolbar_loaded = function ( $block_editor ) use ( $page_a ) {
		$GLOBALS['wp_scripts'] = null;
		wp_scripts();
		$GLOBALS['post'] = get_post( $page_a );
		set_current_screen( 'page' );
		get_current_screen()->is_block_editor( $block_editor );
		( new \AHFCM\Admin() )->enqueue( 'post.php' );
		return wp_script_is( 'ahfcm-toolbar', 'enqueued' );
	};
	$gt = Settings::get();
	ahfcm_check( 'Toolbar icon loads in the block editor', $toolbar_loaded( true ) );
	ahfcm_check( 'Toolbar icon is not loaded in the Classic editor', ! $toolbar_loaded( false ) );
	$gt['editor_button'] = false;
	update_option( Settings::OPTION, $gt );
	ahfcm_check( '"Show the button in the WordPress editor" off removes it', ! $toolbar_loaded( true ) );
	$gt['editor_button']             = true;
	$gt['builder_button_hide_users'] = array( (int) $ahfcm_admin_id );
	update_option( Settings::OPTION, $gt );
	ahfcm_check( 'Hidden users do not get the editor icon either', ! $toolbar_loaded( true ) );
	$gt['builder_button_hide_users'] = array();
	update_option( Settings::OPTION, $gt );
	unset( $GLOBALS['post'], $GLOBALS['current_screen'] );

	WP_CLI::line( 'Meta box vs. block editor panel' );
	wp_set_current_user( $ahfcm_admin_id );
	$box_added = function ( $block_editor ) use ( $page_a ) {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		$force         = static function () use ( $block_editor ) {
			return $block_editor;
		};
		add_filter( 'use_block_editor_for_post', $force, 999 );
		set_current_screen( 'page' );
		( new \AHFCM\Admin() )->add_meta_box( 'page', get_post( $page_a ) );
		remove_filter( 'use_block_editor_for_post', $force, 999 );
		return ! empty( $wp_meta_boxes['page']['normal']['default'][ \AHFCM\Admin::META_BOX_ID ] );
	};
	$gm = Settings::get();
	ahfcm_check( 'Block editor: no meta box (the toolbar panel is used)', ! $box_added( true ) );
	ahfcm_check( 'Classic editor: meta box shown', $box_added( false ) );
	$gm['editor_button'] = false;
	update_option( Settings::OPTION, $gm );
	ahfcm_check( 'Block editor with the toolbar icon off: meta box shown (only way to edit)', $box_added( true ) );
	$gm['editor_button']             = true;
	$gm['builder_button_hide_users'] = array( (int) $ahfcm_admin_id );
	update_option( Settings::OPTION, $gm );
	ahfcm_check( 'Block editor for a user hidden from the icon: meta box shown', $box_added( true ) );
	$gm['builder_button_hide_users'] = array();
	update_option( Settings::OPTION, $gm );
	unset( $GLOBALS['current_screen'] );

	WP_CLI::line( 'Stale-page guard' );
	$before_stale = Post_Meta::get( $page_b );
	ahfcm_submit_meta_box( $page_b, array( '_schema' => null, 'slots' => array( 'head' => array( 'code' => '' ) ), 'enabled' => '0' ) );
	ahfcm_check( 'Meta box form without the current format version is ignored', Post_Meta::get( $page_b ) === $before_stale );
	ahfcm_submit_meta_box( $page_b, array( '_schema' => 1, 'slots' => array( 'head' => array( 'code' => '' ) ) ) );
	ahfcm_check( 'Meta box form from an older format version is ignored', Post_Meta::get( $page_b ) === $before_stale );
	$res = $ahfcm_rest( 'POST', 'post/' . $page_b, array( 'schema' => null, 'data' => array( 'enabled' => false, 'slots' => array() ) ) );
	ahfcm_check( 'REST save without the format version is refused (409) and changes nothing', 409 === $res->get_status() && Post_Meta::get( $page_b ) === $before_stale );
	$res = $ahfcm_rest( 'POST', 'global', array( 'schema' => 2, 'data' => array( 'enabled' => false ) ) );
	ahfcm_check( 'REST global save from an old panel is refused (409)', 409 === $res->get_status() );
	$stored_global       = Settings::get();
	$_POST['option_page'] = Settings::GROUP;
	$kept                = Settings::sanitize( array( 'enabled' => '0', 'slots' => array( 'head' => array( 'code' => '' ) ) ) );
	$fresh               = Settings::sanitize( array( '_schema' => Settings::SCHEMA_VERSION, 'enabled' => '1' ) );
	$_POST               = array();
	ahfcm_check( 'Settings form without the format version keeps stored settings', $kept === $stored_global );
	ahfcm_check( 'Settings form with the current format version saves normally', true === $fresh['enabled'] );

	WP_CLI::line( 'Revisions' );
	ahfcm_submit_meta_box( $page_b, array( 'slots' => array( 'head' => array( 'code' => '<!-- REV-1 -->' ) ) ) );
	$_POST = array(
		Post_Meta::NONCE_FIELD => wp_create_nonce( Post_Meta::NONCE_PREFIX . $page_b ),
		Post_Meta::FORM_FIELD  => wp_slash( array( '_schema' => Settings::SCHEMA_VERSION, 'slots' => array( 'head' => array( 'code' => '<!-- REV-2 -->' ) ) ) ),
	);
	wp_update_post( array( 'ID' => $page_b, 'post_content' => 'rev ' . wp_rand() ) );
	$_POST     = array();
	$revisions = wp_get_post_revisions( $page_b );
	$latest    = $revisions ? reset( $revisions ) : null;
	$rev_meta  = $latest ? get_post_meta( $latest->ID, Post_Meta::META_KEY, true ) : null;
	ahfcm_check( 'Per-post code is captured in post revisions', is_array( $rev_meta ) && '<!-- REV-2 -->' === $rev_meta['slots']['head']['html'] );

	WP_CLI::line( 'Migration' );
	update_post_meta( $page_b, '_page_header_code', wp_slash( '<!-- LEGACY-HEAD \\d -->' ) );
	update_post_meta( $page_b, '_page_body_code', '<!-- LEGACY-BODY -->' );
	$legacy_post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'AHFCM legacy only' ) );
	$ahfcm_created_posts[] = $legacy_post;
	update_post_meta( $legacy_post, '_page_header_code', '<!-- LEGACY-ONLY -->' );
	update_post_meta( $legacy_post, '_page_body_code', '<!-- LEGACY-ONLY-BODY -->' );

	$dry = Migration::run( array( 'dry_run' => true ) );
	ahfcm_check( 'Dry run changes nothing', '' === Post_Meta::get( $legacy_post )['slots']['head']['html'] );
	$rows = wp_list_filter( $dry, array( 'post_id' => $page_b ) );
	ahfcm_check( 'Slot with different code is reported as conflict (no overwrite)', in_array( 'skipped_conflict', wp_list_pluck( $rows, 'status' ), true ) );
	ahfcm_check( 'Backslash difference vs. old stripslashes() output is flagged', (bool) array_filter( wp_list_pluck( $rows, 'notes' ) ) );

	$run  = Migration::run( array( 'dry_run' => false ) );
	$lp   = Post_Meta::get( $legacy_post );
	ahfcm_check( 'Import copies legacy code (body → footer), disabled', '<!-- LEGACY-ONLY -->' === $lp['slots']['head']['html'] && '<!-- LEGACY-ONLY-BODY -->' === $lp['slots']['footer']['html'] && ! $lp['slots']['head']['enabled'] );
	ahfcm_check( 'Import reports success', in_array( 'imported', wp_list_pluck( $run, 'status' ), true ) && ! in_array( 'failed', wp_list_pluck( $run, 'status' ), true ) );
	ahfcm_check( 'Imported-but-disabled code does not change front-end output', false === strpos( ahfcm_fetch( get_permalink( $legacy_post ) ), '<!-- AHFCM head (post' ) );
	$again = Migration::run( array( 'dry_run' => false ) );
	$lp2   = Post_Meta::get( $legacy_post );
	ahfcm_check( 'Re-running import does not duplicate code', $lp2 === $lp && in_array( 'already_imported', wp_list_pluck( $again, 'status' ), true ) );
	Migration::run( array( 'dry_run' => false, 'enable' => true ) );
	ahfcm_check( 'Import with "enable" switches imported slots on', Post_Meta::get( $legacy_post )['slots']['head']['enabled'] );
	ahfcm_check( 'Legacy meta is never modified', '<!-- LEGACY-ONLY -->' === get_post_meta( $legacy_post, '_page_header_code', true ) );

	WP_CLI::line( 'Deactivate / reactivate' );
	$snapshot_global = Settings::get();
	$snapshot_post   = Post_Meta::get( $page_a );
	deactivate_plugins( 'advanced-header-footer-code-manager/advanced-header-footer-code-manager.php', true );
	ahfcm_check( 'Data present while deactivated', is_array( get_option( Settings::OPTION ) ) && metadata_exists( 'post', $page_a, Post_Meta::META_KEY ) );
	activate_plugin( 'advanced-header-footer-code-manager/advanced-header-footer-code-manager.php' );
	Settings::flush_cache();
	wp_cache_flush();
	ahfcm_check( '9 Code survives deactivation and reactivation', Settings::get() === $snapshot_global && Post_Meta::get( $page_a ) === $snapshot_post );
} catch ( \Throwable $e ) {
	ahfcm_check( 'Suite ran without exceptions', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
} finally {
	WP_CLI::line( 'Cleanup' );
	$_POST = array();
	wp_set_current_user( $ahfcm_admin_id );
	foreach ( $ahfcm_created_posts as $id ) {
		if ( $id ) {
			wp_delete_post( $id, true );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $ahfcm_created_users as $id ) {
		if ( $id && ! is_wp_error( $id ) ) {
			wp_delete_user( $id );
		}
	}
	if ( false === $ahfcm_original_option ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $ahfcm_original_option );
	}
	if ( null === $ahfcm_original_hist ) {
		delete_option( Settings::HISTORY_OPTION );
	} else {
		update_option( Settings::HISTORY_OPTION, $ahfcm_original_hist, false );
	}
}

WP_CLI::line( '' );
$ahfcm_summary = sprintf( '%d passed, %d failed', $GLOBALS['ahfcm_results']['pass'], $GLOBALS['ahfcm_results']['fail'] );
if ( $GLOBALS['ahfcm_results']['fail'] ) {
	WP_CLI::error( $ahfcm_summary );
}
WP_CLI::success( $ahfcm_summary );
