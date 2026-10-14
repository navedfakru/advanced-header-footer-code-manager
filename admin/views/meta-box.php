<?php
/**
 * Per-post meta box.
 *
 * @var WP_Post $post       Post.
 * @var array   $data       Per-post settings.
 * @var array   $global     Global settings.
 * @var string  $type_label Post type singular label.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Admin;
use AHFCM\Post_Meta;
use AHFCM\Security;
use AHFCM\Settings;

wp_nonce_field( Post_Meta::NONCE_PREFIX . $post->ID, Post_Meta::NONCE_FIELD );

$ahfcm_type = strtolower( $type_label );
/* translators: %s: post type name, e.g. "page". */
$ahfcm_scope = sprintf( __( 'This %s only', 'advanced-header-footer-code-manager' ), $ahfcm_type );
$ahfcm_field = Post_Meta::FORM_FIELD;
?>
<div class="ahfcm-app ahfcm-metabox ahfcm-theme-<?php echo esc_attr( $global['editor_theme'] ); ?>" data-ahfcm-root data-ahfcm-context="post">
	<div class="ahfcm-metabox__bar">
		<label class="ahfcm-switch">
			<input type="hidden" name="<?php echo esc_attr( $ahfcm_field ); ?>[enabled]" value="0">
			<input type="checkbox" role="switch" name="<?php echo esc_attr( $ahfcm_field ); ?>[enabled]" value="1" <?php checked( $data['enabled'] ); ?>>
			<span class="ahfcm-switch__track" aria-hidden="true"></span>
			<span class="ahfcm-switch__label">
				<?php
				/* translators: %s: post type name. */
				printf( esc_html__( 'Custom code on this %s', 'advanced-header-footer-code-manager' ), esc_html( $ahfcm_type ) );
				?>
			</span>
		</label>

		<label class="ahfcm-switch">
			<input type="hidden" name="<?php echo esc_attr( $ahfcm_field ); ?>[suppress_global]" value="0">
			<input type="checkbox" role="switch" name="<?php echo esc_attr( $ahfcm_field ); ?>[suppress_global]" value="1" <?php checked( $data['suppress_global'] ); ?>>
			<span class="ahfcm-switch__track" aria-hidden="true"></span>
			<span class="ahfcm-switch__label"><?php esc_html_e( 'Skip global code', 'advanced-header-footer-code-manager' ); ?></span>
		</label>

		<span class="ahfcm-metabox__note">
			<?php
			if ( ! $global['enabled'] ) {
				esc_html_e( 'Global code is paused site-wide.', 'advanced-header-footer-code-manager' );
			} else {
				esc_html_e( 'Runs after global code.', 'advanced-header-footer-code-manager' );
			}
			if ( Security::can_manage_global() ) {
				printf( ' <a href="%s">%s</a>', esc_url( Admin::page_url() ), esc_html__( 'Edit global code →', 'advanced-header-footer-code-manager' ) );
			}
			?>
		</span>
	</div>

	<div class="ahfcm-grid">
		<?php
		foreach ( Settings::SLOTS as $ahfcm_slot ) {
			Admin::render_field(
				'ahfcm-post-' . $ahfcm_slot,
				$ahfcm_field . '[slots][' . $ahfcm_slot . ']',
				$ahfcm_slot,
				$data['slots'][ $ahfcm_slot ],
				$ahfcm_scope
			);
		}
		?>
	</div>

	<p class="ahfcm-footnote">
		<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2 3 6v6c0 5 3.8 9.7 9 10 5.2-.3 9-5 9-10V6l-9-4Zm-1 6h2v6h-2V8Zm0 8h2v2h-2v-2Z"/></svg>
		<?php esc_html_e( 'Code is output exactly as written and runs for every visitor of this page. Only paste code you trust.', 'advanced-header-footer-code-manager' ); ?>
	</p>
</div>
