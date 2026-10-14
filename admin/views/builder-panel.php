<?php
/**
 * Breakdance builder panel (cloned from a <template> by builder.js).
 *
 * @var bool  $can_post   Whether the document's code can be edited.
 * @var bool  $can_global Whether global code can be edited.
 * @var array $settings   Global settings (theme only; values load via REST).
 * @var array $empty      Empty per-post structure used for initial markup.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Settings;
use AHFCM\UI;
?>
<template id="ahfcm-bd-template">
	<div class="ahfcm-bd-backdrop" data-ahfcm-bd-backdrop>
		<div class="ahfcm-app ahfcm-bd ahfcm-theme-<?php echo esc_attr( $settings['editor_theme'] ); ?>" role="dialog" aria-modal="true" aria-labelledby="ahfcm-bd-title" data-ahfcm-root data-ahfcm-context="builder">
			<header class="ahfcm-bd__header">
				<span class="ahfcm-bd__logo" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="m8.6 16.6-4.6-4.6 4.6-4.6L7.2 6 1.2 12l6 6 1.4-1.4Zm6.8 0 4.6-4.6-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z"/></svg>
				</span>
				<h2 id="ahfcm-bd-title" class="ahfcm-bd__title"><?php esc_html_e( 'Header & Footer Code', 'advanced-header-footer-code-manager' ); ?></h2>

				<div class="ahfcm-bd__tabs" role="tablist">
					<?php if ( $can_post ) : ?>
						<button type="button" role="tab" class="ahfcm-bd__tab" data-ahfcm-bd-tab="post" aria-selected="true" data-ahfcm-bd-post-tab><?php esc_html_e( 'This page', 'advanced-header-footer-code-manager' ); ?></button>
					<?php endif; ?>
					<?php if ( $can_global ) : ?>
						<button type="button" role="tab" class="ahfcm-bd__tab" data-ahfcm-bd-tab="global" aria-selected="false"><?php esc_html_e( 'Global', 'advanced-header-footer-code-manager' ); ?></button>
					<?php endif; ?>
				</div>

				<span class="ahfcm-bd__state" data-ahfcm-bd-state aria-live="polite"></span>
				<button type="button" class="ahfcm-bd__save" data-ahfcm-bd-save title="<?php esc_attr_e( 'Save (Ctrl+S)', 'advanced-header-footer-code-manager' ); ?>"><?php esc_html_e( 'Save', 'advanced-header-footer-code-manager' ); ?></button>
				<button type="button" class="ahfcm-bd__close" data-ahfcm-bd-close aria-label="<?php esc_attr_e( 'Close', 'advanced-header-footer-code-manager' ); ?>" title="<?php esc_attr_e( 'Close (Esc)', 'advanced-header-footer-code-manager' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="m12 10.6 5-5 1.4 1.4-5 5 5 5-1.4 1.4-5-5-5 5L5.6 17l5-5-5-5L7 5.6l5 5Z"/></svg>
				</button>
			</header>

			<div class="ahfcm-bd__body">
				<?php if ( $can_post ) : ?>
					<section class="ahfcm-bd__pane" data-ahfcm-bd-pane="post" role="tabpanel">
						<p class="ahfcm-bd__notice" data-ahfcm-bd-unsupported hidden>
							<?php esc_html_e( 'Code is not enabled for this content type. Turn it on under Settings → Header & Footer Code → Settings (“Per-page code on” or “Breakdance templates”), or use the Global tab.', 'advanced-header-footer-code-manager' ); ?>
						</p>
						<div data-ahfcm-bd-form>
							<div class="ahfcm-metabox__bar">
								<label class="ahfcm-switch ahfcm-master-toggle">
									<input type="hidden" name="post[enabled]" value="0">
									<input type="checkbox" role="switch" name="post[enabled]" value="1" checked>
									<span class="ahfcm-switch__track" aria-hidden="true"></span>
									<span class="ahfcm-switch__label" data-ahfcm-bd-enable-label><?php esc_html_e( 'Custom code on this page', 'advanced-header-footer-code-manager' ); ?></span>
								</label>
								<label class="ahfcm-switch">
									<input type="hidden" name="post[suppress_global]" value="0">
									<input type="checkbox" role="switch" name="post[suppress_global]" value="1">
									<span class="ahfcm-switch__track" aria-hidden="true"></span>
									<span class="ahfcm-switch__label"><?php esc_html_e( 'Skip global code', 'advanced-header-footer-code-manager' ); ?></span>
								</label>
								<span class="ahfcm-metabox__note" data-ahfcm-bd-doc></span>
							</div>
							<p class="ahfcm-master-off" role="status"><?php esc_html_e( 'Switched OFF: none of the code below is loaded. Turn on the first switch to load it.', 'advanced-header-footer-code-manager' ); ?></p>
							<div class="ahfcm-grid">
								<?php
								foreach ( Settings::SLOTS as $ahfcm_slot ) {
									UI::render_field( 'ahfcm-bd-post-' . $ahfcm_slot, 'post[slots][' . $ahfcm_slot . ']', $ahfcm_slot, $empty['slots'][ $ahfcm_slot ], __( 'This page only', 'advanced-header-footer-code-manager' ) );
								}
								?>
							</div>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $can_global ) : ?>
					<section class="ahfcm-bd__pane" data-ahfcm-bd-pane="global" role="tabpanel" hidden>
						<div data-ahfcm-bd-form>
							<div class="ahfcm-metabox__bar">
								<label class="ahfcm-switch ahfcm-master-toggle">
									<input type="hidden" name="global[enabled]" value="0">
									<input type="checkbox" role="switch" name="global[enabled]" value="1" checked>
									<span class="ahfcm-switch__track" aria-hidden="true"></span>
									<span class="ahfcm-switch__label"><?php esc_html_e( 'Global code enabled', 'advanced-header-footer-code-manager' ); ?></span>
								</label>
								<span class="ahfcm-metabox__note">
									<?php esc_html_e( 'Runs on every page of the site.', 'advanced-header-footer-code-manager' ); ?>
									<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ahfcm' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'All settings ↗', 'advanced-header-footer-code-manager' ); ?></a>
								</span>
							</div>
							<p class="ahfcm-master-off" role="status"><?php esc_html_e( 'Switched OFF: none of the code below is loaded. Turn on the first switch to load it.', 'advanced-header-footer-code-manager' ); ?></p>
							<div class="ahfcm-grid">
								<?php
								foreach ( Settings::SLOTS as $ahfcm_slot ) {
									UI::render_field( 'ahfcm-bd-global-' . $ahfcm_slot, 'global[slots][' . $ahfcm_slot . ']', $ahfcm_slot, $empty['slots'][ $ahfcm_slot ], __( 'Site-wide', 'advanced-header-footer-code-manager' ) );
								}
								?>
							</div>
						</div>
					</section>
				<?php endif; ?>
			</div>

			<footer class="ahfcm-bd__footer">
				<svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="currentColor" d="M12 2 3 6v6c0 5 3.8 9.7 9 10 5.2-.3 9-5 9-10V6l-9-4Zm-1 6h2v6h-2V8Zm0 8h2v2h-2v-2Z"/></svg>
				<?php esc_html_e( 'Code runs on the live site exactly as written. Only paste code you trust.', 'advanced-header-footer-code-manager' ); ?>
			</footer>
		</div>
	</div>
</template>
