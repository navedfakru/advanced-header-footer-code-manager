<?php
/**
 * Plugin bootstrap: wires every component to WordPress.
 *
 *     Core\         storage, output, permissions, upgrades
 *     Api\          private REST API used by the editor panel
 *     Admin\        settings app, meta box, tools
 *     Editor\       shared CodeMirror editor, fields and panel
 *     Integration\  Gutenberg, Breakdance, Oxygen (see Integration\Loader)
 *
 * @package AHFCM
 */

namespace AHFCM;

use AHFCM\Admin\AdminPage;
use AHFCM\Admin\MetaBox;
use AHFCM\Api\Rest;
use AHFCM\Core\Injector;
use AHFCM\Core\PostMeta;
use AHFCM\Core\Security;
use AHFCM\Core\Settings;
use AHFCM\Core\Updater;
use AHFCM\Core\Upgrade;
use AHFCM\Integration\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Security::register();
		Updater::register();
		Upgrade::register();
		( new Settings() )->register();
		( new PostMeta() )->register();
		( new Injector() )->register();
		( new Rest() )->register();

		// Builder plugins load after this one, so integrations register once all plugins are in.
		add_action( 'plugins_loaded', array( Loader::class, 'register' ), 20 );

		if ( is_admin() ) {
			( new AdminPage() )->register();
			( new MetaBox() )->register();
		}
	}
}
