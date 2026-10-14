<?php
/**
 * Plugin Name:       Advanced Header & Footer Code Manager
 * Description:       Add trusted code to &lt;head&gt; and before &lt;/body&gt; — site-wide or per post/page — with a CodeMirror editor, linting, autocomplete and formatting.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Naved
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-header-footer-code-manager
 * Domain Path:       /languages
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

define( 'AHFCM_VERSION', '1.1.1' );
define( 'AHFCM_FILE', __FILE__ );
define( 'AHFCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'AHFCM_URL', plugin_dir_url( __FILE__ ) );

require_once AHFCM_DIR . 'includes/class-security.php';
require_once AHFCM_DIR . 'includes/class-settings.php';
require_once AHFCM_DIR . 'includes/class-post-meta.php';
require_once AHFCM_DIR . 'includes/class-frontend.php';
require_once AHFCM_DIR . 'includes/class-activator.php';
require_once AHFCM_DIR . 'includes/class-migration.php';
require_once AHFCM_DIR . 'includes/class-ui.php';
require_once AHFCM_DIR . 'includes/class-rest.php';
require_once AHFCM_DIR . 'includes/class-breakdance.php';
require_once AHFCM_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once AHFCM_DIR . 'admin/class-admin.php';
}

register_activation_hook( __FILE__, array( 'AHFCM\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AHFCM\\Activator', 'deactivate' ) );

AHFCM\Plugin::instance()->boot();
