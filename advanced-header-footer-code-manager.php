<?php
/**
 * Plugin Name:       Advanced Header & Footer Code Manager
 * Plugin URI:        https://github.com/navedfakru/advanced-header-footer-code-manager
 * Description:       Add trusted HTML, CSS and JavaScript to &lt;head&gt; and before &lt;/body&gt; — site-wide, per page, or per Breakdance/Oxygen template — with a CodeMirror editor, linting, autocomplete and formatting. Works in the block editor, Breakdance and Oxygen.
 * Version:           2.2.1
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

require_once __DIR__ . '/constant.php';
require_once __DIR__ . '/src/Autoloader.php';

AHFCM\Autoloader::register();

register_activation_hook( AHFCM_FILE, array( AHFCM\Core\Upgrade::class, 'activate' ) );
register_deactivation_hook( AHFCM_FILE, array( AHFCM\Core\Upgrade::class, 'deactivate' ) );

AHFCM\Plugin::instance()->boot();
