<?php
/**
 * Section: Output (where per-page code is available, hook priorities, markers).
 *
 * @var array           $settings   Global settings.
 * @var string          $ahfcm_opt  Option name.
 * @var \WP_Post_Type[] $post_types Public post types.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="ahfcm-panel" id="ahfcm-section-output" data-ahfcm-panel="output" role="tabpanel" aria-labelledby="ahfcm-tab-output" tabindex="0" hidden>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Output', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'Where per-page code is available and where code lands in the page.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-card ahfcm-settings">
		<div class="ahfcm-row">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'Per-page code on', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Content types whose editor offers page-specific code.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[post_types][]" value="">
				<div class="ahfcm-checks">
					<?php foreach ( $post_types as $ahfcm_type ) : ?>
						<label class="ahfcm-check">
							<input type="checkbox" name="<?php echo esc_attr( $ahfcm_opt ); ?>[post_types][]" value="<?php echo esc_attr( $ahfcm_type->name ); ?>" <?php checked( null === $settings['post_types'] || in_array( $ahfcm_type->name, $settings['post_types'], true ) ); ?>>
							<span><?php echo esc_html( $ahfcm_type->labels->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="ahfcm-row">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'Hook priority', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Lower runs earlier. The default 1000001 prints at the very end of <head> and <body>, after theme and builder CSS/JS, so your styles win and scripts can use loaded libraries. Use a low number (e.g. 1) for tags that must load first.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control ahfcm-inline-fields">
				<label><code>wp_head</code> <input type="number" class="regular-text ahfcm-prio" min="-99999999" max="99999999" name="<?php echo esc_attr( $ahfcm_opt ); ?>[slots][head][priority]" value="<?php echo esc_attr( $settings['slots']['head']['priority'] ); ?>"></label>
				<label><code>wp_footer</code> <input type="number" class="regular-text ahfcm-prio" min="-99999999" max="99999999" name="<?php echo esc_attr( $ahfcm_opt ); ?>[slots][footer][priority]" value="<?php echo esc_attr( $settings['slots']['footer']['priority'] ); ?>"></label>
			</div>
		</div>

		<div class="ahfcm-row">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'HTML comment markers', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Wraps each snippet in <!-- AHFCM … --> comments so you can find it in View Source.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<label class="ahfcm-switch">
					<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[output_markers]" value="0">
					<input type="checkbox" role="switch" aria-label="<?php esc_attr_e( 'HTML comment markers', 'advanced-header-footer-code-manager' ); ?>" name="<?php echo esc_attr( $ahfcm_opt ); ?>[output_markers]" value="1" <?php checked( $settings['output_markers'] ); ?>>
					<span class="ahfcm-switch__track" aria-hidden="true"></span>
				</label>
			</div>
		</div>
	</div>
</section>
