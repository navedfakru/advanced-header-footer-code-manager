<?php
/**
 * PSR-4 autoloader for the AHFCM namespace (src/), so the plugin needs no
 * Composer install.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Maps AHFCM\Foo\Bar to src/Foo/Bar.php.
 */
final class Autoloader {

	const PREFIX = 'AHFCM\\';

	/**
	 * Registers the autoloader once.
	 *
	 * @return void
	 */
	public static function register() {
		static $done = false;
		if ( ! $done ) {
			spl_autoload_register( array( __CLASS__, 'load' ) );
			$done = true;
		}
	}

	/**
	 * Loads a class from src/.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}
		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
