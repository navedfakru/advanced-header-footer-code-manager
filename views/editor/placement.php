<?php
/**
 * Position control of one code slot (Editor\Fields::render_placement()).
 *
 * Contexts:
 *   post          Per-page / builder item: Same as global, Just before, Custom priority.
 *   global        Global slot, saved with the form (Output tab, builder panel).
 *   global_mirror Global slot shown on a Global code card: same options, no
 *                 name (not submitted); kept in sync with the Output tab by
 *                 editor.js through data-ahfcm-sync.
 *
 * @var string $id        Element ID prefix.
 * @var string $name_base Form name prefix.
 * @var string $slot      head|footer.
 * @var array  $data      Slot data (placement, priority).
 * @var string $context   post|global|global_mirror.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Core\Settings;

$ahfcm_is_global = 'post' !== $context;
$ahfcm_named     = 'global_mirror' !== $context;
$ahfcm_sync      = $ahfcm_is_global ? 'global-' . $slot : '';
$ahfcm_place     = isset( $data['placement'] ) ? $data['placement'] : ( $ahfcm_is_global ? Settings::PLACEMENT_PRIORITY : Settings::PLACEMENT_INHERIT );
if ( $ahfcm_is_global && Settings::PLACEMENT_INHERIT === $ahfcm_place ) {
	$ahfcm_place = Settings::PLACEMENT_PRIORITY;
}
$ahfcm_prio   = isset( $data['priority'] ) ? (int) $data['priority'] : Settings::DEFAULT_PRIORITY;
$ahfcm_hook   = 'head' === $slot ? 'wp_head' : 'wp_footer';
$ahfcm_close  = 'head' === $slot ? '</head>' : '</body>';
$ahfcm_places = array();
if ( ! $ahfcm_is_global ) {
	$ahfcm_places[ Settings::PLACEMENT_INHERIT ] = array(
		__( 'Same as global code', 'advanced-header-footer-code-manager' ),
		__( 'Uses the global position (Settings → Header & Footer Code → Output).', 'advanced-header-footer-code-manager' ),
	);
}
$ahfcm_places[ Settings::PLACEMENT_END ] = array(
	/* translators: %s: closing tag, </head> or </body>. */
	sprintf( __( 'Just before %s', 'advanced-header-footer-code-manager' ), $ahfcm_close ),
	/* translators: 1: hook name, 2: closing tag. */
	sprintf( __( 'Printed last in %1$s, right before %2$s — after every theme, plugin and builder tag.', 'advanced-header-footer-code-manager' ), $ahfcm_hook, $ahfcm_close ),
);
$ahfcm_places[ Settings::PLACEMENT_PRIORITY ] = array(
	/* translators: %s: hook name. */
	sprintf( __( 'Custom priority (%s)', 'advanced-header-footer-code-manager' ), $ahfcm_hook ),
	/* translators: %s: default priority. */
	sprintf( __( 'Lower runs earlier: 1 loads before most tags; %s prints right after the builder CSS/JS.', 'advanced-header-footer-code-manager' ), number_format_i18n( Settings::DEFAULT_PRIORITY ) ),
);
$ahfcm_name = static function ( $key ) use ( $ahfcm_named, $name_base ) {
	return $ahfcm_named ? ' name="' . esc_attr( $name_base . '[' . $key . ']' ) . '"' : '';
};
$ahfcm_sync_attr = $ahfcm_sync ? ' data-ahfcm-sync="' . esc_attr( $ahfcm_sync ) . '"' : '';
?>
<div class="ahfcm-place" data-ahfcm-place>
	<label class="ahfcm-place__label" for="<?php echo esc_attr( $id ); ?>-placement"><?php esc_html_e( 'Position', 'advanced-header-footer-code-manager' ); ?></label>
	<select class="ahfcm-place__select" id="<?php echo esc_attr( $id ); ?>-placement"<?php echo $ahfcm_name( 'placement' ) . $ahfcm_sync_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?> data-ahfcm-placement aria-describedby="<?php echo esc_attr( $id ); ?>-placement-hint">
		<?php foreach ( $ahfcm_places as $ahfcm_value => $ahfcm_option ) : ?>
			<option value="<?php echo esc_attr( $ahfcm_value ); ?>" data-hint="<?php echo esc_attr( $ahfcm_option[1] ); ?>" <?php selected( $ahfcm_place, $ahfcm_value ); ?>><?php echo esc_html( $ahfcm_option[0] ); ?></option>
		<?php endforeach; ?>
	</select>
	<span class="ahfcm-place__prio" data-ahfcm-priority-wrap <?php echo Settings::PLACEMENT_PRIORITY === $ahfcm_place ? '' : 'hidden'; ?>>
		<label class="ahfcm-sr" for="<?php echo esc_attr( $id ); ?>-priority"><?php esc_html_e( 'Priority', 'advanced-header-footer-code-manager' ); ?></label>
		<input type="number" class="ahfcm-place__number" id="<?php echo esc_attr( $id ); ?>-priority"<?php echo $ahfcm_name( 'priority' ) . $ahfcm_sync_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?> min="<?php echo esc_attr( -Settings::MAX_PRIORITY ); ?>" max="<?php echo esc_attr( Settings::MAX_PRIORITY ); ?>" step="1" value="<?php echo esc_attr( $ahfcm_prio ); ?>" data-ahfcm-priority>
	</span>
	<p class="ahfcm-place__hint" id="<?php echo esc_attr( $id ); ?>-placement-hint" data-ahfcm-place-hint><?php echo esc_html( $ahfcm_places[ $ahfcm_place ][1] ); ?></p>
</div>
