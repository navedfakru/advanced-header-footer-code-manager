<?php
/**
 * One code slot card: HTML, CSS and JS editors in tabs (Editor\Fields::render_slot()).
 * All three parts are saved and output together.
 *
 * @var string $id         Element ID prefix.
 * @var string $name_base  Form name prefix.
 * @var string $slot       Slot key.
 * @var array  $data       Slot data (html, css, js, enabled).
 * @var string $scope_note Scope reminder.
 * @var string $context    global|global_mirror|post (see Fields::render_placement()).
 * @var array  $labels     Slot labels.
 * @var array  $languages  Part => label.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Editor\Fields;

$ahfcm_slot_info = $labels[ $slot ];

// Open the first part that has code (HTML when all are empty).
$ahfcm_active = 'html';
foreach ( array( 'html', 'css', 'js' ) as $ahfcm_part ) {
	if ( '' !== trim( $data[ $ahfcm_part ] ) ) {
		$ahfcm_active = $ahfcm_part;
		break;
	}
}
$ahfcm_part_help = array(
	'html' => __( 'HTML is output exactly as written: tags, <script src>, <link>, <meta>, JSON-LD…', 'advanced-header-footer-code-manager' ),
	'css'  => __( 'Plain CSS, wrapped in <style> for you. Printed before the HTML.', 'advanced-header-footer-code-manager' ),
	'js'   => __( 'Plain JavaScript, wrapped in <script> for you. Printed after the HTML.', 'advanced-header-footer-code-manager' ),
);
?>
<div class="ahfcm-card ahfcm-slot<?php echo $data['enabled'] ? '' : ' is-disabled'; ?>" data-ahfcm-slot data-active="<?php echo esc_attr( $ahfcm_active ); ?>">
	<div class="ahfcm-field__header">
		<div class="ahfcm-field__heading">
			<span class="ahfcm-field__icon ahfcm-field__icon--<?php echo esc_attr( $slot ); ?>" aria-hidden="true">
				<?php if ( 'head' === $slot ) : ?>
					<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v3h16V6H4Zm0 5v7h16v-7H4Z"/></svg>
				<?php else : ?>
					<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v7h16V6H4Zm0 9v3h16v-3H4Z"/></svg>
				<?php endif; ?>
			</span>
			<div>
				<span class="ahfcm-field__title" id="<?php echo esc_attr( $id ); ?>-title"><?php echo esc_html( $ahfcm_slot_info['label'] ); ?></span>
				<div class="ahfcm-field__meta">
					<code class="ahfcm-chip"><?php echo esc_html( $ahfcm_slot_info['where'] ); ?></code>
					<span><?php echo esc_html( $scope_note ); ?></span>
				</div>
			</div>
		</div>

		<label class="ahfcm-switch">
			<input type="hidden" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="0">
			<input type="checkbox" role="switch" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="1" data-ahfcm-enabled <?php checked( $data['enabled'] ); ?>>
			<span class="ahfcm-switch__track" aria-hidden="true"></span>
			<span class="ahfcm-switch__label"><?php esc_html_e( 'Enabled', 'advanced-header-footer-code-manager' ); ?></span>
		</label>
	</div>

	<?php Fields::render_placement( $id, $name_base, $slot, $data, $context ); ?>

	<div class="ahfcm-field__toolbar">
		<div class="ahfcm-seg ahfcm-parts" role="tablist" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
			<?php foreach ( $languages as $ahfcm_part => $ahfcm_label ) : ?>
				<button type="button" role="tab" class="ahfcm-seg__item ahfcm-part-tab<?php echo $ahfcm_part === $ahfcm_active ? ' is-active' : ''; ?>"
					id="<?php echo esc_attr( $id . '-' . $ahfcm_part ); ?>-tab"
					aria-controls="<?php echo esc_attr( $id . '-' . $ahfcm_part ); ?>-panel"
					aria-selected="<?php echo $ahfcm_part === $ahfcm_active ? 'true' : 'false'; ?>"
					data-ahfcm-part-tab="<?php echo esc_attr( $ahfcm_part ); ?>">
					<span><?php echo esc_html( $ahfcm_label ); ?><i class="ahfcm-part-dot<?php echo '' !== trim( $data[ $ahfcm_part ] ) ? ' has-code' : ''; ?>" aria-hidden="true"></i></span>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="ahfcm-field__tools">
			<button type="button" class="ahfcm-btn" data-ahfcm-format title="<?php esc_attr_e( 'Format code (Shift+Alt+F)', 'advanced-header-footer-code-manager' ); ?>">
				<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="m7.5 5.6 2.4-1.3L8.6 6.7 9.9 9 7.5 7.7 5.1 9l1.3-2.4L5.1 4.3l2.4 1.3Zm12 9.8 2.4-1.3-1.3 2.3 1.3 2.4-2.4-1.3-2.4 1.3 1.3-2.4-1.3-2.3 2.4 1.3ZM22 2l-1.3 2.4L22 6.7l-2.4-1.3-2.4 1.3 1.3-2.4L17.2 2l2.4 1.3L22 2Zm-8.6 7.3 2.4 2.4L5.8 21.7l-2.4-2.4L13.4 9.3Zm-1 3.6 1.4 1.4 1.6-1.6-1.4-1.4-1.6 1.6Z"/></svg>
				<span><?php esc_html_e( 'Format', 'advanced-header-footer-code-manager' ); ?></span>
			</button>
			<button type="button" class="ahfcm-btn ahfcm-btn--icon" data-ahfcm-fullscreen aria-pressed="false" aria-label="<?php esc_attr_e( 'Full screen', 'advanced-header-footer-code-manager' ); ?>" title="<?php esc_attr_e( 'Full screen (F11)', 'advanced-header-footer-code-manager' ); ?>">
				<svg class="ahfcm-icon-expand" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h6v2H6v4H4V4Zm10 0h6v6h-2V6h-4V4ZM4 14h2v4h4v2H4v-6Zm14 0h2v6h-6v-2h4v-4Z"/></svg>
				<svg class="ahfcm-icon-collapse" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 4h2v6H4V8h4V4Zm6 0h2v4h4v2h-6V4ZM4 14h6v6H8v-4H4v-2Zm10 0h6v2h-4v4h-2v-6Z"/></svg>
			</button>
		</div>
	</div>

	<?php foreach ( $languages as $ahfcm_part => $ahfcm_label ) : ?>
		<?php $ahfcm_pid = $id . '-' . $ahfcm_part; ?>
		<div class="ahfcm-field" data-ahfcm-field data-language="<?php echo esc_attr( $ahfcm_part ); ?>"
			id="<?php echo esc_attr( $ahfcm_pid ); ?>-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $ahfcm_pid ); ?>-tab"
			<?php echo $ahfcm_part === $ahfcm_active ? '' : 'hidden'; ?>>
			<div class="ahfcm-field__editor" data-ahfcm-resize>
				<label class="screen-reader-text ahfcm-sr" for="<?php echo esc_attr( $ahfcm_pid ); ?>"><?php echo esc_html( $ahfcm_slot_info['label'] . ' — ' . $ahfcm_label ); ?></label>
				<textarea
					id="<?php echo esc_attr( $ahfcm_pid ); ?>"
					name="<?php echo esc_attr( $name_base . '[' . $ahfcm_part . ']' ); ?>"
					class="ahfcm-textarea"
					rows="12"
					spellcheck="false"
					autocomplete="off"
					aria-describedby="<?php echo esc_attr( $ahfcm_pid ); ?>-help <?php echo esc_attr( $ahfcm_pid ); ?>-status"
				><?php echo esc_textarea( $data[ $ahfcm_part ] ); ?></textarea>
			</div>
			<div class="ahfcm-statusbar">
				<span class="ahfcm-statusbar__lang" data-ahfcm-badge><?php echo esc_html( $ahfcm_label ); ?></span>
				<span class="ahfcm-statusbar__status" id="<?php echo esc_attr( $ahfcm_pid ); ?>-status" data-ahfcm-status aria-live="polite"></span>
				<span class="ahfcm-statusbar__cursor" data-ahfcm-cursor></span>
			</div>
			<p class="ahfcm-field__hint" data-ahfcm-hint></p>
			<p class="ahfcm-field__desc" id="<?php echo esc_attr( $ahfcm_pid ); ?>-help"><?php echo esc_html( $ahfcm_part_help[ $ahfcm_part ] ); ?></p>
		</div>
	<?php endforeach; ?>

	<p class="ahfcm-field__desc ahfcm-slot__desc"><?php echo esc_html( $ahfcm_slot_info['description'] ); ?></p>
</div>
