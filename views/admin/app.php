<?php
/**
 * Settings → Header & Footer Code: app shell with a top bar and tab navigation.
 *
 * Sections inside the form (saved together): Global code, Integrations,
 * Output, Editor. Outside the form: Tools, Help.
 *
 * @var array                                     $settings      Global settings.
 * @var array                                     $history       Global code history.
 * @var array|null                                $report        Pending tool report.
 * @var bool                                      $legacy_active Whether the legacy snippet is loaded.
 * @var int                                       $legacy_count  Posts with legacy code.
 * @var \AHFCM\Integration\IntegrationInterface[] $integrations  All integrations.
 * @var \AHFCM\Integration\Breakdance\Main|null   $builder       Running Breakdance/Oxygen integration.
 * @var array<string,string>                      $roles         Roles that may see toolbar buttons.
 * @var \WP_User[]                                $users         Users that may see toolbar buttons.
 * @var \WP_Post_Type[]                           $post_types    Public post types.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Core\Settings;

$ahfcm_opt = Settings::OPTION;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice key.
$ahfcm_notice = isset( $_GET['ahfcm_notice'] ) ? sanitize_key( wp_unslash( $_GET['ahfcm_notice'] ) ) : '';
$ahfcm_live   = $settings['enabled'] && Settings::has_code( $settings );

$ahfcm_sections = array(
	'code'         => array( __( 'Global code', 'advanced-header-footer-code-manager' ), 'M8.6 16.6 4 12l4.6-4.6L7.2 6l-6 6 6 6 1.4-1.4Zm6.8 0L20 12l-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z', true ),
	'integrations' => array( __( 'Integrations', 'advanced-header-footer-code-manager' ), 'M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm12 0h2v3h3v2h-3v3h-2v-3h-3v-2h3v-3Z', true ),
	'output'       => array( __( 'Output', 'advanced-header-footer-code-manager' ), 'M4 5h16v2H4V5Zm0 6h10v2H4v-2Zm0 6h16v2H4v-2Zm14-7 4 2-4 2v-4Z', true ),
	'editor'       => array( __( 'Editor', 'advanced-header-footer-code-manager' ), 'M3 17.3V21h3.7l11-11-3.7-3.7-11 11ZM20.7 7a1 1 0 0 0 0-1.4l-2.3-2.3a1 1 0 0 0-1.4 0l-1.8 1.8 3.7 3.7L20.7 7Z', true ),
	'tools'        => array( __( 'Tools', 'advanced-header-footer-code-manager' ), 'M22.7 19 13.6 9.9a6.4 6.4 0 0 0-1.5-6.9 6.6 6.6 0 0 0-7.4-1.3L9 6 6 9 1.6 4.7a6.6 6.6 0 0 0 1.4 7.4 6.4 6.4 0 0 0 6.9 1.5l9.1 9.1a1 1 0 0 0 1.4 0l2.3-2.3a1 1 0 0 0 0-1.4Z', false ),
	'help'         => array( __( 'Help', 'advanced-header-footer-code-manager' ), 'M11 18h2v-2h-2v2Zm1-16a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm0-14a4 4 0 0 0-4 4h2a2 2 0 1 1 4 0c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5a4 4 0 0 0-4-4Z', false ),
);
?>
<div class="wrap ahfcm-app ahfcm-shell ahfcm-theme-<?php echo esc_attr( $settings['editor_theme'] ); ?>" data-ahfcm-root data-ahfcm-context="global">

	<header class="ahfcm-topbar">
		<div class="ahfcm-brand">
			<span class="ahfcm-brand__logo" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="m8.6 16.6-4.6-4.6 4.6-4.6L7.2 6 1.2 12l6 6 1.4-1.4Zm6.8 0 4.6-4.6-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z"/></svg>
			</span>
			<div>
				<h1 class="ahfcm-brand__title"><?php esc_html_e( 'Header & Footer Code', 'advanced-header-footer-code-manager' ); ?></h1>
				<span class="ahfcm-brand__meta">
					<?php
					/* translators: %s: plugin version. */
					printf( esc_html__( 'Version %s', 'advanced-header-footer-code-manager' ), esc_html( AHFCM_VERSION ) );
					?>
				</span>
			</div>
		</div>

		<span class="ahfcm-status-pill <?php echo $ahfcm_live ? 'is-live' : 'is-idle'; ?>">
			<span class="ahfcm-status-pill__dot" aria-hidden="true"></span>
			<?php echo $ahfcm_live ? esc_html__( 'Global code live', 'advanced-header-footer-code-manager' ) : esc_html__( 'Global code not running', 'advanced-header-footer-code-manager' ); ?>
		</span>

		<div class="ahfcm-topbar__actions" data-ahfcm-submit>
			<span class="ahfcm-topbar__dirty" data-ahfcm-dirty hidden><span class="ahfcm-dot" aria-hidden="true"></span><?php esc_html_e( 'Unsaved changes', 'advanced-header-footer-code-manager' ); ?></span>
			<button type="submit" form="ahfcm-settings-form" class="button button-primary ahfcm-save"><?php esc_html_e( 'Save changes', 'advanced-header-footer-code-manager' ); ?></button>
		</div>
	</header>

	<hr class="wp-header-end">

	<div class="ahfcm-layout">
		<nav class="ahfcm-tabbar" role="tablist" aria-label="<?php esc_attr_e( 'Sections', 'advanced-header-footer-code-manager' ); ?>">
			<?php foreach ( $ahfcm_sections as $ahfcm_key => $ahfcm_section ) : ?>
				<a href="#<?php echo esc_attr( $ahfcm_key ); ?>" class="ahfcm-nav<?php echo 'code' === $ahfcm_key ? ' is-active' : ''; ?>" role="tab" data-ahfcm-tab="<?php echo esc_attr( $ahfcm_key ); ?>" aria-selected="<?php echo 'code' === $ahfcm_key ? 'true' : 'false'; ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="<?php echo esc_attr( $ahfcm_section[1] ); ?>"/></svg>
					<span><?php echo esc_html( $ahfcm_section[0] ); ?></span>
				</a>
			<?php endforeach; ?>
			<a class="ahfcm-tabbar__link" href="https://github.com/navedfakru/advanced-header-footer-code-manager" target="_blank" rel="noopener"><?php esc_html_e( 'Docs & changelog ↗', 'advanced-header-footer-code-manager' ); ?></a>
		</nav>

		<main class="ahfcm-main">
			<?php settings_errors(); ?>
			<?php if ( 'restored' === $ahfcm_notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Previous version restored. The version it replaced was added to the history.', 'advanced-header-footer-code-manager' ); ?></p></div>
			<?php elseif ( 'restore_failed' === $ahfcm_notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'That history entry no longer exists.', 'advanced-header-footer-code-manager' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $legacy_active ) : ?>
				<div class="notice notice-info"><p><?php esc_html_e( 'The legacy "Page Header & Body Code Embed" snippet (WPCodeBox) is still active and keeps printing its own per-page code. Avoid enabling the same code in both places — see Tools.', 'advanced-header-footer-code-manager' ); ?></p></div>
			<?php endif; ?>

			<form id="ahfcm-settings-form" method="post" action="options.php" data-ahfcm-form>
				<?php settings_fields( Settings::GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[_schema]" value="<?php echo esc_attr( Settings::SCHEMA_VERSION ); ?>">
				<?php
				foreach ( array( 'code', 'integrations', 'output', 'editor' ) as $ahfcm_key ) {
					include __DIR__ . '/sections/' . $ahfcm_key . '.php';
				}
				?>
			</form>

			<?php
			foreach ( array( 'tools', 'help' ) as $ahfcm_key ) {
				include __DIR__ . '/sections/' . $ahfcm_key . '.php';
			}
			?>
		</main>
	</div>
</div>
