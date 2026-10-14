<?php
/**
 * Section: Editor appearance and data handling.
 *
 * @var array  $settings  Global settings.
 * @var string $ahfcm_opt Option name.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="ahfcm-panel" id="ahfcm-section-editor" data-ahfcm-panel="editor" role="tabpanel" hidden>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Editor', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'How the code editors look, and what happens to your data on uninstall.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-card ahfcm-settings">
		<div class="ahfcm-row">
			<div class="ahfcm-row__label"><label for="ahfcm-theme"><?php esc_html_e( 'Theme', 'advanced-header-footer-code-manager' ); ?></label></div>
			<div class="ahfcm-row__control">
				<select id="ahfcm-theme" name="<?php echo esc_attr( $ahfcm_opt ); ?>[editor_theme]">
					<option value="dark" <?php selected( $settings['editor_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'advanced-header-footer-code-manager' ); ?></option>
					<option value="light" <?php selected( $settings['editor_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'advanced-header-footer-code-manager' ); ?></option>
				</select>
			</div>
		</div>

		<div class="ahfcm-row">
			<div class="ahfcm-row__label">
				<label for="ahfcm-height"><?php esc_html_e( 'Height', 'advanced-header-footer-code-manager' ); ?></label>
				<p><?php esc_html_e( 'Starting height. Drag the bottom edge of any editor to resize it.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<input type="number" id="ahfcm-height" class="small-text" min="120" max="2000" step="10" name="<?php echo esc_attr( $ahfcm_opt ); ?>[editor_height]" value="<?php echo esc_attr( $settings['editor_height'] ); ?>"> px
			</div>
		</div>
	</div>

	<div class="ahfcm-card ahfcm-settings ahfcm-card--danger">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Uninstall', 'advanced-header-footer-code-manager' ); ?></h3>
		<div class="ahfcm-row">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'Delete data when the plugin is deleted', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Permanently removes all global and per-page code. Deactivating never deletes anything.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<label class="ahfcm-switch">
					<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[delete_data_on_uninstall]" value="0">
					<input type="checkbox" role="switch" aria-label="<?php esc_attr_e( 'Delete data when the plugin is deleted', 'advanced-header-footer-code-manager' ); ?>" name="<?php echo esc_attr( $ahfcm_opt ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?>>
					<span class="ahfcm-switch__track" aria-hidden="true"></span>
				</label>
			</div>
		</div>
	</div>
</section>
