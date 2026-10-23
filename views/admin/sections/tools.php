<?php
/**
 * Section: Tools (safe mode, change log, global and page code history, legacy
 * import). Outside the settings form: each tool posts to admin-post.php with
 * its own nonce. Shown read-only while code editing is locked.
 *
 * @var array      $settings      Global settings.
 * @var array      $history       Global code history.
 * @var array|null $report        Pending tool report.
 * @var int        $legacy_count  Posts with legacy code.
 * @var array[]    $change_log    Change log entries.
 * @var int[]      $page_history  Posts with earlier code versions.
 * @var bool       $safe_constant AHFCM_SAFE_MODE is set.
 * @var bool       $can_edit      Current user may edit global code.
 * @var string     $ahfcm_first   Tab shown first.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Admin\LegacyImport;
use AHFCM\Core\ChangeLog;
use AHFCM\Core\PostMeta;
use AHFCM\Core\SafeMode;
use AHFCM\Core\Security;
use AHFCM\Core\Settings;
use AHFCM\Editor\Fields;

$ahfcm_log_shown = 50;

/**
 * Size of the code in a history value, e.g. "1.2 KB".
 *
 * @param array $value Settings or post value with `slots`.
 * @return string
 */
$ahfcm_size = static function ( $value ) {
	$bytes = 0;
	foreach ( Settings::SLOTS as $slot ) {
		$bytes += isset( $value['slots'][ $slot ] ) && is_array( $value['slots'][ $slot ] ) ? Settings::slot_size( $value['slots'][ $slot ] ) : 0;
	}
	return $bytes ? size_format( $bytes ) : '0 B';
};

/**
 * Side-by-side diff of a history value against the current code, '' when equal.
 *
 * @param array $old     History value.
 * @param array $current Current value.
 * @return string Safe HTML from wp_text_diff().
 */
$ahfcm_diff = static function ( $old, $current ) {
	$slot_labels = array(
		'head'   => __( 'Header', 'advanced-header-footer-code-manager' ),
		'footer' => __( 'Footer', 'advanced-header-footer-code-manager' ),
	);
	$html = '';
	foreach ( Settings::SLOTS as $slot ) {
		$a = Settings::normalize_slot( isset( $old['slots'][ $slot ] ) && is_array( $old['slots'][ $slot ] ) ? $old['slots'][ $slot ] : array(), Settings::slot_defaults( $slot ) );
		$b = Settings::normalize_slot( isset( $current['slots'][ $slot ] ) && is_array( $current['slots'][ $slot ] ) ? $current['slots'][ $slot ] : array(), Settings::slot_defaults( $slot ) );
		foreach ( array( 'css' => 'CSS', 'html' => 'HTML', 'js' => 'JS' ) as $part => $label ) {
			$diff = wp_text_diff(
				$a[ $part ],
				$b[ $part ],
				array(
					'title'       => $slot_labels[ $slot ] . ' · ' . $label,
					'title_left'  => __( 'This version', 'advanced-header-footer-code-manager' ),
					'title_right' => __( 'Current', 'advanced-header-footer-code-manager' ),
				)
			);
			$html .= (string) $diff;
		}
	}
	return $html;
};

$ahfcm_when = static function ( $time ) {
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $time );
};

$ahfcm_who = static function ( $user_id, $fallback = '' ) {
	$user = $user_id ? get_userdata( (int) $user_id ) : false;
	if ( $user ) {
		return $user->display_name;
	}
	if ( '' !== $fallback ) {
		return $fallback;
	}
	return $user_id ? __( 'Deleted user', 'advanced-header-footer-code-manager' ) : __( 'System (WP-CLI, cron or a plugin)', 'advanced-header-footer-code-manager' );
};
?>
<section class="ahfcm-panel" id="ahfcm-section-tools" data-ahfcm-panel="tools" role="tabpanel" aria-labelledby="ahfcm-tab-tools" tabindex="0"<?php echo 'tools' === $ahfcm_first ? '' : ' hidden'; ?>>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Tools', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'Safe mode, who changed what, earlier versions of global and page code, and the import from the old snippet.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-card" id="ahfcm-safe-mode">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Safe mode', 'advanced-header-footer-code-manager' ); ?></h3>
		<p class="ahfcm-muted"><?php esc_html_e( 'Stops all header and footer code (global, builder templates and pages) from printing, without changing or deleting any of it. Use it when a snippet breaks the site.', 'advanced-header-footer-code-manager' ); ?></p>
		<?php if ( $safe_constant ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Safe mode is on for everyone: AHFCM_SAFE_MODE is set in wp-config.php.', 'advanced-header-footer-code-manager' ); ?></p></div>
		<?php endif; ?>
		<ul class="ahfcm-list">
			<li>
				<strong><?php esc_html_e( 'Just for you, one page:', 'advanced-header-footer-code-manager' ); ?></strong>
				<?php
				printf(
					/* translators: %s: URL query argument. */
					esc_html__( 'add %s to any URL while logged in as an administrator.', 'advanced-header-footer-code-manager' ),
					'<code>?' . esc_html( SafeMode::QUERY_ARG ) . '=1</code>'
				);
				?>
				<a class="button button-small" href="<?php echo esc_url( SafeMode::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the home page in safe mode', 'advanced-header-footer-code-manager' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'For everyone:', 'advanced-header-footer-code-manager' ); ?></strong>
				<?php
				printf(
					/* translators: %s: PHP code. */
					esc_html__( 'add %s to wp-config.php. This also works when you cannot log in.', 'advanced-header-footer-code-manager' ),
					"<code>define( 'AHFCM_SAFE_MODE', true );</code>"
				);
				?>
			</li>
		</ul>
	</div>

	<div class="ahfcm-card" id="ahfcm-change-log">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Change log', 'advanced-header-footer-code-manager' ); ?></h3>
		<?php if ( empty( $change_log ) ) : ?>
			<p class="ahfcm-muted"><?php esc_html_e( 'No changes recorded yet. Every change to global code, page code and settings is listed here with who made it and when.', 'advanced-header-footer-code-manager' ); ?></p>
		<?php else : ?>
			<p class="ahfcm-muted">
				<?php
				printf(
					/* translators: 1: entries shown, 2: entries kept. */
					esc_html__( 'Newest first. Showing %1$d of the last %2$d changes; the log records what changed, not the code itself.', 'advanced-header-footer-code-manager' ),
					(int) min( $ahfcm_log_shown, count( $change_log ) ),
					(int) count( $change_log )
				);
				?>
			</p>
			<table class="widefat striped ahfcm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'Who', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'What', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'Where', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'Details', 'advanced-header-footer-code-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $change_log, 0, $ahfcm_log_shown ) as $ahfcm_entry ) : ?>
						<?php $ahfcm_post = $ahfcm_entry['object'] ? get_post( (int) $ahfcm_entry['object'] ) : null; ?>
						<tr>
							<td><?php echo esc_html( $ahfcm_when( $ahfcm_entry['time'] ) ); ?></td>
							<td><?php echo esc_html( $ahfcm_who( $ahfcm_entry['user'], isset( $ahfcm_entry['user_name'] ) ? (string) $ahfcm_entry['user_name'] : '' ) ); ?></td>
							<td><?php echo esc_html( ChangeLog::action_label( $ahfcm_entry['action'] ) ); ?></td>
							<td>
								<?php if ( ! $ahfcm_entry['object'] ) : ?>
									<?php esc_html_e( 'Global', 'advanced-header-footer-code-manager' ); ?>
								<?php elseif ( $ahfcm_post ) : ?>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $ahfcm_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $ahfcm_post ) ? get_the_title( $ahfcm_post ) : '#' . $ahfcm_post->ID ); ?></a>
								<?php else : ?>
									<?php
									/* translators: %d: post ID. */
									printf( esc_html__( '#%d (deleted)', 'advanced-header-footer-code-manager' ), (int) $ahfcm_entry['object'] );
									?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $ahfcm_entry['details'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="ahfcm-card" id="ahfcm-global-history">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Global code history', 'advanced-header-footer-code-manager' ); ?></h3>
		<?php if ( empty( $history ) ) : ?>
			<p class="ahfcm-muted">
				<?php
				printf(
					/* translators: %d: number of versions kept. */
					esc_html__( 'No earlier versions yet. Each time global code changes, the previous version is kept here (last %d).', 'advanced-header-footer-code-manager' ),
					(int) Settings::HISTORY_LIMIT
				);
				?>
			</p>
		<?php else : ?>
			<table class="widefat ahfcm-table ahfcm-history-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Replaced', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'By', 'advanced-header-footer-code-manager' ); ?></th>
						<th><?php esc_html_e( 'Size', 'advanced-header-footer-code-manager' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $ahfcm_index => $ahfcm_entry ) : ?>
						<?php $ahfcm_changes = $ahfcm_diff( $ahfcm_entry['value'], $settings ); ?>
						<tr>
							<td><?php echo esc_html( $ahfcm_when( $ahfcm_entry['time'] ) ); ?></td>
							<td><?php echo esc_html( $ahfcm_who( $ahfcm_entry['user'] ) ); ?></td>
							<td><?php echo esc_html( $ahfcm_size( $ahfcm_entry['value'] ) ); ?></td>
							<td>
								<?php if ( $can_edit ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="ahfcm_restore">
										<input type="hidden" name="index" value="<?php echo esc_attr( $ahfcm_index ); ?>">
										<?php wp_nonce_field( 'ahfcm_restore' ); ?>
										<?php submit_button( __( 'Restore', 'advanced-header-footer-code-manager' ), 'small', 'submit', false ); ?>
									</form>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="ahfcm-diff-row">
							<td colspan="4">
								<?php if ( '' === $ahfcm_changes ) : ?>
									<span class="ahfcm-muted"><?php esc_html_e( 'Same code as now (only switches or position differ).', 'advanced-header-footer-code-manager' ); ?></span>
								<?php else : ?>
									<details>
										<summary><?php esc_html_e( 'Show changes compared with the current code', 'advanced-header-footer-code-manager' ); ?></summary>
										<?php echo $ahfcm_changes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_text_diff() escapes the code it shows. ?>
									</details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="ahfcm-card" id="ahfcm-page-history">
		<h3 class="ahfcm-card__title"><?php esc_html_e( 'Page code history', 'advanced-header-footer-code-manager' ); ?></h3>
		<?php if ( empty( $page_history ) ) : ?>
			<p class="ahfcm-muted">
				<?php
				printf(
					/* translators: %d: number of versions kept. */
					esc_html__( 'No earlier versions yet. Each time the code of a page, post or builder template changes, its previous version is kept here (last %d per item).', 'advanced-header-footer-code-manager' ),
					(int) PostMeta::HISTORY_LIMIT
				);
				?>
			</p>
		<?php else : ?>
			<?php foreach ( $page_history as $ahfcm_post_id ) : ?>
				<?php
				$ahfcm_post     = get_post( $ahfcm_post_id );
				$ahfcm_entries  = PostMeta::get_history( $ahfcm_post_id );
				$ahfcm_current  = PostMeta::get( $ahfcm_post_id );
				$ahfcm_can_post = Security::can_edit_post_code( $ahfcm_post_id );
				$ahfcm_type     = $ahfcm_post ? get_post_type_object( $ahfcm_post->post_type ) : null;
				if ( ! $ahfcm_post ) {
					continue;
				}
				?>
				<details class="ahfcm-history-item">
					<summary>
						<strong><?php echo esc_html( get_the_title( $ahfcm_post ) ? get_the_title( $ahfcm_post ) : '#' . $ahfcm_post->ID ); ?></strong>
						<span class="ahfcm-muted">
							<?php
							printf(
								/* translators: 1: content type, 2: number of versions, 3: date. */
								esc_html( _n( '%1$s · %2$d earlier version · last change %3$s', '%1$s · %2$d earlier versions · last change %3$s', count( $ahfcm_entries ), 'advanced-header-footer-code-manager' ) ),
								esc_html( $ahfcm_type ? $ahfcm_type->labels->singular_name : $ahfcm_post->post_type ),
								(int) count( $ahfcm_entries ),
								esc_html( $ahfcm_when( $ahfcm_entries[0]['time'] ) )
							);
							?>
						</span>
					</summary>
					<table class="widefat ahfcm-table ahfcm-history-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Replaced', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'By', 'advanced-header-footer-code-manager' ); ?></th>
								<th><?php esc_html_e( 'Size', 'advanced-header-footer-code-manager' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $ahfcm_entries as $ahfcm_index => $ahfcm_entry ) : ?>
								<?php $ahfcm_changes = $ahfcm_diff( $ahfcm_entry['value'], $ahfcm_current ); ?>
								<tr>
									<td><?php echo esc_html( $ahfcm_when( $ahfcm_entry['time'] ) ); ?></td>
									<td><?php echo esc_html( $ahfcm_who( $ahfcm_entry['user'] ) ); ?></td>
									<td><?php echo esc_html( $ahfcm_size( $ahfcm_entry['value'] ) ); ?></td>
									<td>
										<?php if ( $ahfcm_can_post ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="ahfcm_restore_post">
												<input type="hidden" name="post_id" value="<?php echo esc_attr( $ahfcm_post_id ); ?>">
												<input type="hidden" name="index" value="<?php echo esc_attr( $ahfcm_index ); ?>">
												<?php wp_nonce_field( 'ahfcm_restore_post_' . $ahfcm_post_id ); ?>
												<?php submit_button( __( 'Restore', 'advanced-header-footer-code-manager' ), 'small', 'submit', false ); ?>
											</form>
										<?php endif; ?>
									</td>
								</tr>
								<tr class="ahfcm-diff-row">
									<td colspan="4">
										<?php if ( '' === $ahfcm_changes ) : ?>
											<span class="ahfcm-muted"><?php esc_html_e( 'Same code as now (only switches or position differ).', 'advanced-header-footer-code-manager' ); ?></span>
										<?php else : ?>
											<details>
												<summary><?php esc_html_e( 'Show changes compared with the current code', 'advanced-header-footer-code-manager' ); ?></summary>
												<?php echo $ahfcm_changes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_text_diff() escapes the code it shows. ?>
											</details>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php if ( $can_edit ) : ?>
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
	<?php endif; ?>
</section>
