<?php
/**
 * Section: Integrations (block editor, Breakdance, Oxygen) + toolbar button
 * visibility.
 *
 * @var array                                     $settings     Global settings.
 * @var string                                    $ahfcm_opt    Option name.
 * @var \AHFCM\Integration\IntegrationInterface[] $integrations All integrations.
 * @var \AHFCM\Integration\Breakdance\Main|null   $builder      Running builder integration.
 * @var array<string,string>                      $roles        Eligible roles.
 * @var \WP_User[]                                $users        Eligible users.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Core\Settings;
use AHFCM\Integration\Breakdance\Main as BuilderIntegration;

/**
 * Prints a switch (hidden 0 + checkbox 1).
 *
 * @param string $name       Field name.
 * @param bool   $checked    Checked.
 * @param string $label      Accessible label.
 * @param string $extra_attr Extra attributes.
 */
$ahfcm_switch = static function ( $name, $checked, $label, $extra_attr = '' ) {
	?>
	<label class="ahfcm-switch">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
		<input type="checkbox" role="switch" aria-label="<?php echo esc_attr( $label ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> <?php echo $extra_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute string. ?>>
		<span class="ahfcm-switch__track" aria-hidden="true"></span>
	</label>
	<?php
};
?>
<section class="ahfcm-panel" id="ahfcm-section-integrations" data-ahfcm-panel="integrations" role="tabpanel" hidden>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Integrations', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'Detected automatically. Switch an integration off to stop all of its features without uninstalling anything.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-integrations">
		<?php foreach ( $integrations as $ahfcm_integration ) : ?>
			<?php
			$ahfcm_name    = $ahfcm_integration->get_name();
			$ahfcm_active  = $ahfcm_integration->is_active();
			$ahfcm_on      = Settings::integration_enabled( $ahfcm_name );
			$ahfcm_builder = $ahfcm_integration instanceof BuilderIntegration;
			?>
			<article class="ahfcm-card ahfcm-integration<?php echo $ahfcm_active ? '' : ' is-missing'; ?>" data-integration="<?php echo esc_attr( $ahfcm_name ); ?>">
				<header class="ahfcm-integration__head">
					<span class="ahfcm-integration__logo ahfcm-integration__logo--<?php echo esc_attr( $ahfcm_name ); ?>" aria-hidden="true">
						<?php if ( $ahfcm_integration->get_icon_url() ) : ?>
							<img src="<?php echo esc_url( $ahfcm_integration->get_icon_url() ); ?>" alt="" width="24" height="24">
						<?php else : ?>
							<span class="dashicons dashicons-wordpress-alt"></span>
						<?php endif; ?>
					</span>
					<div class="ahfcm-integration__title">
						<h3><?php echo esc_html( $ahfcm_integration->get_label() ); ?></h3>
						<span class="ahfcm-badge <?php echo $ahfcm_active ? 'is-ok' : 'is-off'; ?>">
							<?php echo $ahfcm_active ? esc_html__( 'Detected', 'advanced-header-footer-code-manager' ) : esc_html__( 'Not installed', 'advanced-header-footer-code-manager' ); ?>
						</span>
					</div>
					<?php $ahfcm_switch( $ahfcm_opt . '[integrations][' . $ahfcm_name . ']', $ahfcm_on, $ahfcm_integration->get_label() ); ?>
				</header>
				<p class="ahfcm-integration__desc"><?php echo esc_html( $ahfcm_integration->get_description() ); ?></p>

				<?php if ( $ahfcm_active && 'gutenberg' === $ahfcm_name ) : ?>
					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Toolbar icon', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'Code icon in the editor top bar. While shown, it replaces the "Header & Body Code Embed" box in the block editor (the box stays in the Classic editor).', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control"><?php $ahfcm_switch( $ahfcm_opt . '[editor_button]', $settings['editor_button'], __( 'Toolbar icon', 'advanced-header-footer-code-manager' ), 'data-ahfcm-button-toggle' ); ?></div>
					</div>
				<?php endif; ?>

				<?php if ( $ahfcm_active && $ahfcm_builder ) : ?>
					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Builder button', 'advanced-header-footer-code-manager' ); ?></span>
							<p>
								<?php
								if ( 'oxygen' === $ahfcm_name ) {
									esc_html_e( 'Code button before the ⋯ settings button in the Oxygen top bar.', 'advanced-header-footer-code-manager' );
								} else {
									/* translators: %s: builder name. */
									printf( esc_html__( 'Code button after the properties button in the %s top bar.', 'advanced-header-footer-code-manager' ), esc_html( $ahfcm_integration->get_label() ) );
								}
								?>
							</p>
						</div>
						<div class="ahfcm-row__control"><?php $ahfcm_switch( $ahfcm_opt . '[builder_button]', $settings['builder_button'], __( 'Builder button', 'advanced-header-footer-code-manager' ), 'data-ahfcm-button-toggle' ); ?></div>
					</div>

					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Code on', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'Each item gets its own code, edited from the builder. It runs on every page that uses the template, header, footer, popup or global block.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="">
							<div class="ahfcm-checks">
								<?php foreach ( $ahfcm_integration->get_template_types() as $ahfcm_type => $ahfcm_type_label ) : ?>
									<label class="ahfcm-check">
										<input type="checkbox" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="<?php echo esc_attr( $ahfcm_type ); ?>" <?php checked( null === $settings['breakdance_types'] || in_array( $ahfcm_type, $settings['breakdance_types'], true ) ); ?>>
										<span><?php echo esc_html( $ahfcm_type_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Run code in the builder preview', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'Off by default so analytics and chat widgets do not fire while you design.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control"><?php $ahfcm_switch( $ahfcm_opt . '[run_in_builder_preview]', $settings['run_in_builder_preview'], __( 'Run code in the builder preview', 'advanced-header-footer-code-manager' ) ); ?></div>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( ! $builder ) : ?>
		<?php // No builder running: keep its settings as they are. ?>
		<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button]" value="<?php echo $settings['builder_button'] ? '1' : '0'; ?>">
		<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[run_in_builder_preview]" value="<?php echo $settings['run_in_builder_preview'] ? '1' : '0'; ?>">
		<?php if ( null !== $settings['breakdance_types'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="">
			<?php foreach ( $settings['breakdance_types'] as $ahfcm_type ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="<?php echo esc_attr( $ahfcm_type ); ?>">
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>

	<div class="ahfcm-card ahfcm-settings">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Who sees the toolbar buttons', 'advanced-header-footer-code-manager' ); ?></h3>
		<p class="ahfcm-muted"><?php esc_html_e( 'Applies to the block editor icon and the builder button. Hiding a button never removes code access — that follows the user’s capabilities.', 'advanced-header-footer-code-manager' ); ?></p>

		<div class="ahfcm-row ahfcm-builder-cond">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'Show for these roles', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Only roles allowed to add raw code are listed.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button_roles][]" value="">
				<div class="ahfcm-checks">
					<?php foreach ( $roles as $ahfcm_role => $ahfcm_role_name ) : ?>
						<label class="ahfcm-check">
							<input type="checkbox" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button_roles][]" value="<?php echo esc_attr( $ahfcm_role ); ?>" <?php checked( null === $settings['builder_button_roles'] || in_array( $ahfcm_role, $settings['builder_button_roles'], true ) ); ?>>
							<span><?php echo esc_html( $ahfcm_role_name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="ahfcm-row ahfcm-builder-cond">
			<div class="ahfcm-row__label">
				<span><?php esc_html_e( 'Hide for these users', 'advanced-header-footer-code-manager' ); ?></span>
				<p><?php esc_html_e( 'Ticked users never see either button, whatever their role.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
			<div class="ahfcm-row__control">
				<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button_hide_users][]" value="">
				<?php if ( $users ) : ?>
					<div class="ahfcm-checks">
						<?php foreach ( $users as $ahfcm_user ) : ?>
							<label class="ahfcm-check">
								<input type="checkbox" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button_hide_users][]" value="<?php echo esc_attr( $ahfcm_user->ID ); ?>" <?php checked( in_array( (int) $ahfcm_user->ID, $settings['builder_button_hide_users'], true ) ); ?>>
								<span>
									<?php echo esc_html( $ahfcm_user->display_name ); ?>
									<?php if ( get_current_user_id() === (int) $ahfcm_user->ID ) : ?>
										<em class="ahfcm-muted"><?php esc_html_e( '(you)', 'advanced-header-footer-code-manager' ); ?></em>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<span class="ahfcm-muted"><?php esc_html_e( 'No users can add raw code.', 'advanced-header-footer-code-manager' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
