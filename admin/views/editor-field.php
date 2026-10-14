<?php
/**
 * One code editor card.
 *
 * @var string $id         Element ID.
 * @var string $name_base  Form name prefix.
 * @var string $slot       Slot key.
 * @var array  $data       Slot data.
 * @var string $scope_note Scope reminder.
 * @var array  $labels     Slot labels.
 * @var array  $languages  Language labels.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

$ahfcm_slot_info = $labels[ $slot ];
?>
<div class="ahfcm-card ahfcm-field<?php echo $data['enabled'] ? '' : ' is-disabled'; ?>" data-ahfcm-field data-language="<?php echo esc_attr( $data['language'] ); ?>">
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
				<label class="ahfcm-field__title" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $ahfcm_slot_info['label'] ); ?></label>
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

	<div class="ahfcm-field__toolbar">
		<div class="ahfcm-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Code language', 'advanced-header-footer-code-manager' ); ?>">
			<?php foreach ( $languages as $ahfcm_lang => $ahfcm_lang_label ) : ?>
				<label class="ahfcm-seg__item">
					<input type="radio" name="<?php echo esc_attr( $name_base ); ?>[language]" value="<?php echo esc_attr( $ahfcm_lang ); ?>" data-ahfcm-language <?php checked( $data['language'], $ahfcm_lang ); ?>>
					<span><?php echo esc_html( $ahfcm_lang_label ); ?></span>
				</label>
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

	<div class="ahfcm-field__editor" data-ahfcm-resize>
		<textarea
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name_base ); ?>[code]"
			class="ahfcm-textarea"
			rows="12"
			spellcheck="false"
			autocomplete="off"
			aria-describedby="<?php echo esc_attr( $id ); ?>-desc <?php echo esc_attr( $id ); ?>-status"
		><?php echo esc_textarea( $data['code'] ); ?></textarea>
	</div>

	<div class="ahfcm-statusbar">
		<span class="ahfcm-statusbar__lang" data-ahfcm-badge><?php echo esc_html( $languages[ $data['language'] ] ); ?></span>
		<span class="ahfcm-statusbar__status" id="<?php echo esc_attr( $id ); ?>-status" data-ahfcm-status aria-live="polite"></span>
		<span class="ahfcm-statusbar__cursor" data-ahfcm-cursor></span>
	</div>
	<p class="ahfcm-field__hint" data-ahfcm-hint></p>
	<p class="ahfcm-field__desc" id="<?php echo esc_attr( $id ); ?>-desc"><?php echo esc_html( $ahfcm_slot_info['description'] ); ?></p>
</div>
