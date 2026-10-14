<?php
/**
 * Section: Tools (legacy import, global code history). Outside the settings
 * form: each tool posts to admin-post.php with its own nonce.
 *
 * @var array      $history      Global code history.
 * @var array|null $report       Pending tool report.
 * @var int        $legacy_count Posts with legacy code.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Admin\LegacyImport;
use AHFCM\Core\Settings;
use AHFCM\Editor\Fields;
?>
<section class="ahfcm-panel" id="ahfcm-section-tools" data-ahfcm-panel="tools" role="tabpanel" aria-labelledby="ahfcm-tab-tools" tabindex="0" hidden>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Tools', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'Import from the old snippet and restore earlier versions of the global code.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-card">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Import from "Page Header & Body Code Embed"', 'advanced-header-footer-code-manager' ); ?></h3>
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
								<td><?php echo esc_html( Fields::slot_labels()[ $ahfcm_row['target'] ]['label'] ); ?></td>
								<td><?php echo esc_html( LegacyImport::status_label( $ahfcm_row['status'] ) ); ?></td>
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
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Global code history', 'advanced-header-footer-code-manager' ); ?></h3>
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
