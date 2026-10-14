<?php
/**
 * Section: Global code.
 *
 * @var array  $settings  Global settings.
 * @var string $ahfcm_opt Option name.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Core\Settings;
use AHFCM\Editor\Fields;
?>
<section class="ahfcm-panel" id="ahfcm-section-code" data-ahfcm-panel="code" role="tabpanel" aria-labelledby="ahfcm-tab-code" tabindex="0">
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Global code', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'Runs on every page of the site. Pages, templates and global blocks can add their own code from their editor.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-card ahfcm-master">
		<label class="ahfcm-switch ahfcm-switch--lg ahfcm-master-toggle">
			<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[enabled]" value="0">
			<input type="checkbox" role="switch" name="<?php echo esc_attr( $ahfcm_opt ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
			<span class="ahfcm-switch__track" aria-hidden="true"></span>
			<span class="ahfcm-switch__label">
				<strong><?php esc_html_e( 'Global code', 'advanced-header-footer-code-manager' ); ?></strong>
				<small><?php esc_html_e( 'Pause everything below without deleting it.', 'advanced-header-footer-code-manager' ); ?></small>
			</span>
		</label>
		<p class="ahfcm-master__warn">
			<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2 3 6v6c0 5 3.8 9.7 9 10 5.2-.3 9-5 9-10V6l-9-4Zm-1 6h2v6h-2V8Zm0 8h2v2h-2v-2Z"/></svg>
			<?php esc_html_e( 'Output exactly as written. Only paste code you trust.', 'advanced-header-footer-code-manager' ); ?>
		</p>
	</div>

	<p class="ahfcm-master-off" role="status"><?php esc_html_e( 'Global code is switched OFF, so none of the code below is loaded on any page. Turn on "Global code" above to load it.', 'advanced-header-footer-code-manager' ); ?></p>
	<div class="ahfcm-grid">
		<?php
		foreach ( Settings::SLOTS as $ahfcm_slot ) {
			Fields::render_slot( 'ahfcm-global-' . $ahfcm_slot, $ahfcm_opt . '[slots][' . $ahfcm_slot . ']', $ahfcm_slot, $settings['slots'][ $ahfcm_slot ], __( 'Site-wide', 'advanced-header-footer-code-manager' ), 'global_mirror' );
		}
		?>
	</div>
</section>
