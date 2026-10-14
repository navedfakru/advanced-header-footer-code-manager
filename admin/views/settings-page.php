<?php
/**
 * Settings → Header & Footer Code.
 *
 * @var array          $settings      Global settings.
 * @var array          $history       Global code history.
 * @var array|null     $report        Pending tool report.
 * @var bool           $legacy_active Whether the legacy snippet is loaded.
 * @var int            $legacy_count  Posts with legacy code.
 * @var WP_Post_Type[] $post_types    Public post types.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Admin;
use AHFCM\Migration;
use AHFCM\Settings;

$ahfcm_opt = Settings::OPTION;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice key.
$ahfcm_notice = isset( $_GET['ahfcm_notice'] ) ? sanitize_key( wp_unslash( $_GET['ahfcm_notice'] ) ) : '';

$ahfcm_live = $settings['enabled'] && Settings::has_code( $settings );
?>
<div class="wrap ahfcm-app ahfcm-theme-<?php echo esc_attr( $settings['editor_theme'] ); ?>" data-ahfcm-root data-ahfcm-context="global">

	<header class="ahfcm-hero">
		<span class="ahfcm-hero__logo" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="m8.6 16.6-4.6-4.6 4.6-4.6L7.2 6 1.2 12l6 6 1.4-1.4Zm6.8 0 4.6-4.6-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z"/></svg>
		</span>
		<div class="ahfcm-hero__text">
			<h1><?php esc_html_e( 'Header & Footer Code', 'advanced-header-footer-code-manager' ); ?></h1>
			<p><?php esc_html_e( 'Add scripts, styles and tags to every page — or to a single page from its editor.', 'advanced-header-footer-code-manager' ); ?></p>
		</div>
		<span class="ahfcm-status-pill <?php echo $ahfcm_live ? 'is-live' : 'is-idle'; ?>">
			<span class="ahfcm-status-pill__dot" aria-hidden="true"></span>
			<?php echo $ahfcm_live ? esc_html__( 'Live', 'advanced-header-footer-code-manager' ) : esc_html__( 'Not running', 'advanced-header-footer-code-manager' ); ?>
		</span>
	</header>

	<hr class="wp-header-end">
	<?php settings_errors(); ?>

	<?php if ( 'restored' === $ahfcm_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Previous version restored. The version it replaced was added to the history.', 'advanced-header-footer-code-manager' ); ?></p></div>
	<?php elseif ( 'restore_failed' === $ahfcm_notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'That history entry no longer exists.', 'advanced-header-footer-code-manager' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $legacy_active ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'The legacy "Page Header & Body Code Embed" snippet (WPCodeBox) is still active and keeps printing its own per-page code. Avoid enabling the same code in both places — see Tools.', 'advanced-header-footer-code-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="ahfcm-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Sections', 'advanced-header-footer-code-manager' ); ?>">
		<a href="#code" class="ahfcm-tab is-active" role="tab" data-ahfcm-tab="code" aria-selected="true"><?php esc_html_e( 'Code', 'advanced-header-footer-code-manager' ); ?></a>
		<a href="#settings" class="ahfcm-tab" role="tab" data-ahfcm-tab="settings" aria-selected="false"><?php esc_html_e( 'Settings', 'advanced-header-footer-code-manager' ); ?></a>
		<a href="#tools" class="ahfcm-tab" role="tab" data-ahfcm-tab="tools" aria-selected="false"><?php esc_html_e( 'Tools', 'advanced-header-footer-code-manager' ); ?></a>
		<a href="#help" class="ahfcm-tab" role="tab" data-ahfcm-tab="help" aria-selected="false"><?php esc_html_e( 'Help', 'advanced-header-footer-code-manager' ); ?></a>
	</nav>

	<form method="post" action="options.php" data-ahfcm-form>
		<?php settings_fields( Settings::GROUP ); ?>
		<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[_schema]" value="<?php echo esc_attr( Settings::SCHEMA_VERSION ); ?>">

		<section class="ahfcm-panel" data-ahfcm-panel="code" role="tabpanel">
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
					<?php esc_html_e( 'Runs on every page, exactly as written. Only paste code you trust.', 'advanced-header-footer-code-manager' ); ?>
				</p>
			</div>

			<p class="ahfcm-master-off" role="status"><?php esc_html_e( 'Global code is switched OFF, so none of the code below is loaded on any page. Turn on "Global code" above to load it.', 'advanced-header-footer-code-manager' ); ?></p>
			<div class="ahfcm-grid">
				<?php
				foreach ( Settings::SLOTS as $ahfcm_slot ) {
					Admin::render_field(
						'ahfcm-global-' . $ahfcm_slot,
						$ahfcm_opt . '[slots][' . $ahfcm_slot . ']',
						$ahfcm_slot,
						$settings['slots'][ $ahfcm_slot ],
						__( 'Site-wide', 'advanced-header-footer-code-manager' )
					);
				}
				?>
			</div>
		</section>

		<section class="ahfcm-panel" data-ahfcm-panel="settings" role="tabpanel" hidden>
			<div class="ahfcm-card ahfcm-settings">
				<h2 class="ahfcm-card__title"><?php esc_html_e( 'Editor', 'advanced-header-footer-code-manager' ); ?></h2>

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
						<p><?php esc_html_e( 'Drag the bottom edge of any editor to resize it.', 'advanced-header-footer-code-manager' ); ?></p>
					</div>
					<div class="ahfcm-row__control">
						<input type="number" id="ahfcm-height" class="small-text" min="120" max="2000" step="10" name="<?php echo esc_attr( $ahfcm_opt ); ?>[editor_height]" value="<?php echo esc_attr( $settings['editor_height'] ); ?>"> px
					</div>
				</div>
			</div>

			<div class="ahfcm-card ahfcm-settings">
				<h2 class="ahfcm-card__title"><?php esc_html_e( 'Output', 'advanced-header-footer-code-manager' ); ?></h2>

				<div class="ahfcm-row">
					<div class="ahfcm-row__label">
						<span><?php esc_html_e( 'Per-page code on', 'advanced-header-footer-code-manager' ); ?></span>
						<p><?php esc_html_e( 'Content types that get the "Header & Body Code Embed" box.', 'advanced-header-footer-code-manager' ); ?></p>
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

				<?php $ahfcm_bd_types = \AHFCM\Breakdance::template_types(); ?>
				<?php if ( $ahfcm_bd_types ) : ?>
					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Breakdance templates', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'Give these their own code, edited from the builder. It runs on every page that uses the template, header, footer or popup.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="">
							<div class="ahfcm-checks">
								<?php foreach ( $ahfcm_bd_types as $ahfcm_bd_type => $ahfcm_bd_label ) : ?>
									<label class="ahfcm-check">
										<input type="checkbox" name="<?php echo esc_attr( $ahfcm_opt ); ?>[breakdance_types][]" value="<?php echo esc_attr( $ahfcm_bd_type ); ?>" <?php checked( null === $settings['breakdance_types'] || in_array( $ahfcm_bd_type, $settings['breakdance_types'], true ) ); ?>>
										<span><?php echo esc_html( $ahfcm_bd_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="ahfcm-row">
					<div class="ahfcm-row__label">
						<span><?php esc_html_e( 'Hook priority', 'advanced-header-footer-code-manager' ); ?></span>
						<p><?php esc_html_e( 'Lower runs earlier. Default 1000001 puts your code at the very end of <head> and <body>, after theme and Breakdance CSS/JS, so your styles win and scripts can use loaded libraries. Use a low number (e.g. 1) for tags that must load first.', 'advanced-header-footer-code-manager' ); ?></p>
					</div>
					<div class="ahfcm-row__control ahfcm-inline-fields">
						<label><code>wp_head</code> <input type="number" class="regular-text ahfcm-prio" min="-99999999" max="99999999" name="<?php echo esc_attr( $ahfcm_opt ); ?>[slots][head][priority]" value="<?php echo esc_attr( $settings['slots']['head']['priority'] ); ?>"></label>
						<label><code>wp_footer</code> <input type="number" class="regular-text ahfcm-prio" min="-99999999" max="99999999" name="<?php echo esc_attr( $ahfcm_opt ); ?>[slots][footer][priority]" value="<?php echo esc_attr( $settings['slots']['footer']['priority'] ); ?>"></label>
					</div>
				</div>

				<?php
				$ahfcm_toggles = array(
					'run_in_builder_preview' => array(
						__( 'Run inside page builders', 'advanced-header-footer-code-manager' ),
						__( 'Off by default so analytics and chat widgets do not fire while you design in Breakdance, Oxygen or Elementor.', 'advanced-header-footer-code-manager' ),
					),
					'output_markers'         => array(
						__( 'HTML comment markers', 'advanced-header-footer-code-manager' ),
						__( 'Wraps each snippet in <!-- AHFCM … --> comments so you can find it in View Source.', 'advanced-header-footer-code-manager' ),
					),
				);
				foreach ( $ahfcm_toggles as $ahfcm_key => $ahfcm_text ) :
					?>
					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php echo esc_html( $ahfcm_text[0] ); ?></span>
							<p><?php echo esc_html( $ahfcm_text[1] ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<label class="ahfcm-switch">
								<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt . '[' . $ahfcm_key . ']' ); ?>" value="0">
								<input type="checkbox" role="switch" aria-label="<?php echo esc_attr( $ahfcm_text[0] ); ?>" name="<?php echo esc_attr( $ahfcm_opt . '[' . $ahfcm_key . ']' ); ?>" value="1" <?php checked( $settings[ $ahfcm_key ] ); ?>>
								<span class="ahfcm-switch__track" aria-hidden="true"></span>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			$ahfcm_roles = \AHFCM\Breakdance::eligible_roles();
			$ahfcm_users = \AHFCM\Breakdance::eligible_users();
			?>
				<div class="ahfcm-card ahfcm-settings">
					<h2 class="ahfcm-card__title"><?php esc_html_e( 'Toolbar buttons', 'advanced-header-footer-code-manager' ); ?></h2>

					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Show the button in the WordPress editor', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'A code icon in the block editor top bar (next to "Edit in Breakdance"). It jumps to this page\'s Header & Body Code Embed box.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<label class="ahfcm-switch">
								<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[editor_button]" value="0">
								<input type="checkbox" role="switch" aria-label="<?php esc_attr_e( 'Show the button in the WordPress editor', 'advanced-header-footer-code-manager' ); ?>" name="<?php echo esc_attr( $ahfcm_opt ); ?>[editor_button]" value="1" data-ahfcm-button-toggle <?php checked( $settings['editor_button'] ); ?>>
								<span class="ahfcm-switch__track" aria-hidden="true"></span>
							</label>
						</div>
					</div>

					<?php if ( defined( '__BREAKDANCE_VERSION' ) ) : ?>
					<div class="ahfcm-row">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Show the button in the builder', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'The code icon next to the properties button in the Breakdance top bar. Turning it off only hides the button — code keeps running and can still be edited here and in the WordPress editor.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<label class="ahfcm-switch">
								<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button]" value="0">
								<input type="checkbox" role="switch" aria-label="<?php esc_attr_e( 'Show the button in the builder', 'advanced-header-footer-code-manager' ); ?>" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button]" value="1" data-ahfcm-button-toggle <?php checked( $settings['builder_button'] ); ?>>
								<span class="ahfcm-switch__track" aria-hidden="true"></span>
							</label>
						</div>
					</div>

					<?php endif; ?>

					<div class="ahfcm-row ahfcm-builder-cond">
						<div class="ahfcm-row__label">
							<span><?php esc_html_e( 'Show for these roles', 'advanced-header-footer-code-manager' ); ?></span>
							<p><?php esc_html_e( 'Applies to both buttons. Only roles allowed to add raw code are listed; other roles never see them.', 'advanced-header-footer-code-manager' ); ?></p>
						</div>
						<div class="ahfcm-row__control">
							<input type="hidden" name="<?php echo esc_attr( $ahfcm_opt ); ?>[builder_button_roles][]" value="">
							<div class="ahfcm-checks">
								<?php foreach ( $ahfcm_roles as $ahfcm_role => $ahfcm_role_name ) : ?>
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
							<?php if ( $ahfcm_users ) : ?>
								<div class="ahfcm-checks">
									<?php foreach ( $ahfcm_users as $ahfcm_user ) : ?>
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

			<div class="ahfcm-card ahfcm-settings ahfcm-card--danger">
				<h2 class="ahfcm-card__title"><?php esc_html_e( 'Uninstall', 'advanced-header-footer-code-manager' ); ?></h2>
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

		<div class="ahfcm-savebar" data-ahfcm-submit>
			<span class="ahfcm-savebar__state" data-ahfcm-dirty hidden><span class="ahfcm-dot" aria-hidden="true"></span><?php esc_html_e( 'Unsaved changes', 'advanced-header-footer-code-manager' ); ?></span>
			<?php submit_button( __( 'Save changes', 'advanced-header-footer-code-manager' ), 'primary ahfcm-save', 'submit', false ); ?>
		</div>
	</form>

	<section class="ahfcm-panel" data-ahfcm-panel="tools" role="tabpanel" hidden>
		<div class="ahfcm-card">
			<h2 class="ahfcm-card__title"><?php esc_html_e( 'Import from "Page Header & Body Code Embed"', 'advanced-header-footer-code-manager' ); ?></h2>
			<p class="ahfcm-muted">
				<?php
				printf(
					/* translators: %d: number of posts. */
					esc_html( _n( '%d post or page has legacy code.', '%d posts or pages have legacy code.', $legacy_count, 'advanced-header-footer-code-manager' ) ),
					(int) $legacy_count
				);
				?>
				<?php esc_html_e( 'Header code goes to Header Code; the old "Body Code" goes to Footer Code, where it actually ran. Legacy data and the WPCodeBox snippet are never changed.', 'advanced-header-footer-code-manager' ); ?>
			</p>

			<?php if ( $report && 'migration' === $report['type'] ) : ?>
				<div class="notice <?php echo $report['dry_run'] ? 'notice-info' : 'notice-success'; ?> inline">
					<p><strong><?php echo $report['dry_run'] ? esc_html__( 'Dry run — nothing was changed.', 'advanced-header-footer-code-manager' ) : esc_html__( 'Import finished.', 'advanced-header-footer-code-manager' ); ?></strong></p>
				</div>
				<?php if ( empty( $report['rows'] ) ) : ?>
					<p><?php esc_html_e( 'No legacy code found.', 'advanced-header-footer-code-manager' ); ?></p>
				<?php else : ?>
					<table class="widefat striped ahfcm-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'Legacy field', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'Target', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'Result', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'advanced-header-footer-code-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $report['rows'] as $ahfcm_row ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( (string) get_edit_post_link( $ahfcm_row['post_id'] ) ); ?>"><?php echo esc_html( $ahfcm_row['title'] ? $ahfcm_row['title'] : '#' . $ahfcm_row['post_id'] ); ?></a></td>
									<td><code><?php echo esc_html( $ahfcm_row['field'] ); ?></code></td>
									<td><?php echo esc_html( Admin::slot_labels()[ $ahfcm_row['target'] ]['label'] ); ?></td>
									<td><?php echo esc_html( Migration::status_label( $ahfcm_row['status'] ) ); ?></td>
									<td><?php echo esc_html( implode( ' ', $ahfcm_row['notes'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ahfcm-tool-form">
				<input type="hidden" name="action" value="ahfcm_migrate">
				<?php wp_nonce_field( 'ahfcm_migrate' ); ?>
				<label class="ahfcm-check">
					<input type="checkbox" name="enable" value="1">
					<span>
						<?php esc_html_e( 'Enable imported code immediately', 'advanced-header-footer-code-manager' ); ?>
						<small><?php esc_html_e( 'Leave off while the legacy snippet is active, otherwise code prints twice. Turn off WPCodeBox snippet #7 first, then run again with this on.', 'advanced-header-footer-code-manager' ); ?></small>
					</span>
				</label>
				<div class="ahfcm-actions">
					<?php submit_button( __( 'Dry run', 'advanced-header-footer-code-manager' ), 'secondary', 'ahfcm_dry', false ); ?>
					<?php submit_button( __( 'Run import', 'advanced-header-footer-code-manager' ), 'primary', 'ahfcm_run', false ); ?>
				</div>
			</form>
		</div>

		<div class="ahfcm-card">
			<h2 class="ahfcm-card__title"><?php esc_html_e( 'Global code history', 'advanced-header-footer-code-manager' ); ?></h2>
			<?php if ( empty( $history ) ) : ?>
				<p class="ahfcm-muted"><?php esc_html_e( 'No earlier versions yet. Each time global code changes, the previous version is kept here (last 5).', 'advanced-header-footer-code-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped ahfcm-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Saved', 'advanced-header-footer-code-manager' ); ?></th>
							<th><?php esc_html_e( 'By', 'advanced-header-footer-code-manager' ); ?></th>
							<th><?php esc_html_e( 'Size', 'advanced-header-footer-code-manager' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $ahfcm_index => $ahfcm_entry ) : ?>
							<?php
							$ahfcm_user  = get_userdata( (int) $ahfcm_entry['user'] );
							$ahfcm_bytes = 0;
							foreach ( Settings::SLOTS as $ahfcm_slot ) {
								$ahfcm_bytes += isset( $ahfcm_entry['value']['slots'][ $ahfcm_slot ] ) && is_array( $ahfcm_entry['value']['slots'][ $ahfcm_slot ] ) ? Settings::slot_size( $ahfcm_entry['value']['slots'][ $ahfcm_slot ] ) : 0;
							}
							?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ahfcm_entry['time'] ) ); ?></td>
								<td><?php echo esc_html( $ahfcm_user ? $ahfcm_user->display_name : '—' ); ?></td>
								<td><?php echo esc_html( $ahfcm_bytes ? size_format( $ahfcm_bytes ) : '0 B' ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="ahfcm_restore">
										<input type="hidden" name="index" value="<?php echo esc_attr( $ahfcm_index ); ?>">
										<?php wp_nonce_field( 'ahfcm_restore' ); ?>
										<?php submit_button( __( 'Restore', 'advanced-header-footer-code-manager' ), 'small', 'submit', false ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</section>

	<section class="ahfcm-panel ahfcm-help" data-ahfcm-panel="help" role="tabpanel" hidden>
		<div class="ahfcm-grid">
			<div class="ahfcm-card">
				<h2 class="ahfcm-card__title"><?php esc_html_e( 'How it works', 'advanced-header-footer-code-manager' ); ?></h2>
				<ul class="ahfcm-list">
					<?php foreach ( Admin::slot_labels() as $ahfcm_slot_label ) : ?>
						<li><strong><?php echo esc_html( $ahfcm_slot_label['label'] ); ?></strong> <code class="ahfcm-chip"><?php echo esc_html( $ahfcm_slot_label['where'] ); ?></code><br><?php echo esc_html( $ahfcm_slot_label['description'] ); ?></li>
					<?php endforeach; ?>
					<li><?php esc_html_e( 'Each page has its own "Header & Body Code Embed" box. When both exist, global code prints first, then the page code. A page can skip global code.', 'advanced-header-footer-code-manager' ); ?></li>
					<li><?php esc_html_e( 'HTML mode outputs code exactly as written. CSS and JS modes wrap your code in <style> or <script> for you. PHP is never executed.', 'advanced-header-footer-code-manager' ); ?></li>
				</ul>
			</div>

			<div class="ahfcm-card">
				<h2 class="ahfcm-card__title"><?php esc_html_e( 'Keyboard shortcuts', 'advanced-header-footer-code-manager' ); ?></h2>
				<dl class="ahfcm-keys">
					<dt><kbd>Ctrl</kbd> <kbd>Space</kbd></dt><dd><?php esc_html_e( 'Autocomplete', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Ctrl</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Search', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Shift</kbd> <kbd>Ctrl</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Search and replace', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Alt</kbd> <kbd>G</kbd></dt><dd><?php esc_html_e( 'Jump to line', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Ctrl</kbd> <kbd>/</kbd></dt><dd><?php esc_html_e( 'Toggle comment', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Ctrl</kbd> <kbd>Q</kbd></dt><dd><?php esc_html_e( 'Fold / unfold block', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Shift</kbd> <kbd>Alt</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Format code', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>F11</kbd></dt><dd><?php esc_html_e( 'Full screen (Esc exits)', 'advanced-header-footer-code-manager' ); ?></dd>
					<dt><kbd>Esc</kbd> <kbd>Tab</kbd></dt><dd><?php esc_html_e( 'Move focus out of the editor', 'advanced-header-footer-code-manager' ); ?></dd>
				</dl>
				<p class="ahfcm-muted"><?php esc_html_e( 'Linting (HTMLHint, CSSLint, Espree) catches common mistakes but cannot prove code is correct or safe. Autocomplete suggests tags, attributes, properties and keywords — it is not full IntelliSense. Format only applies when the result differs in whitespace alone.', 'advanced-header-footer-code-manager' ); ?></p>
			</div>
		</div>
	</section>
</div>
