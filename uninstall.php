<?php
/**
 * Uninstall handler.
 *
 * Data is deleted ONLY when "Permanently delete all global and per-post code
 * when the plugin is deleted" was enabled in Settings → Header & Footer Code.
 * Otherwise all code stays in the database so a reinstall picks it up again.
 *
 * Legacy "Page Header & Body Code Embed" meta (_page_header_code,
 * _page_body_code) is never touched.
 *
 * @package AHFCM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes plugin data for the current site if the site opted in.
 *
 * @return void
 */
function ahfcm_uninstall_site() {
	$settings = get_option( 'ahfcm_global_settings' );
	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	delete_option( 'ahfcm_global_settings' );
	delete_option( 'ahfcm_global_history' );
	delete_option( 'ahfcm_version' );
	delete_transient( 'ahfcm_missing_body_open' ); // Left by 1.0.0.

	// Removes the key from every post, including revisions.
	delete_post_meta_by_key( '_ahfcm_settings' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $ahfcm_site_id ) {
		switch_to_blog( $ahfcm_site_id );
		ahfcm_uninstall_site();
		restore_current_blog();
	}
} else {
	ahfcm_uninstall_site();
}
