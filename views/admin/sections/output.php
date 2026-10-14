<?php
/**
 * Section: Output (where per-page code is available, positions, markers).
 *
 * @var array           $settings   Global settings.
 * @var string          $ahfcm_opt  Option name.
 * @var \WP_Post_Type[] $post_types Public post types.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Editor\Fields;
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

		<?php
		$ahfcm_place_rows = array(
			'head'   => array(
				__( 'Global header code position', 'advanced-header-footer-code-manager' ),
				__( 'Where the site-wide Header Code prints in <head>. Pages and builder items set to "Same as global code" follow it.', 'advanced-header-footer-code-manager' ),
			),
			'footer' => array(
				__( 'Global footer code position', 'advanced-header-footer-code-manager' ),
				__( 'Where the site-wide Footer Code prints before </body>. Pages and builder items set to "Same as global code" follow it.', 'advanced-header-footer-code-manager' ),
			),
		);
		foreach ( $ahfcm_place_rows as $ahfcm_slot => $ahfcm_row ) :
			?>
			<div class="ahfcm-row">
				<div class="ahfcm-row__label">
					<span><?php echo esc_html( $ahfcm_row[0] ); ?></span>
					<p><?php echo esc_html( $ahfcm_row[1] ); ?></p>
				</div>
				<div class="ahfcm-row__control ahfcm-row__control--place">
					<?php Fields::render_placement( 'ahfcm-output-' . $ahfcm_slot, $ahfcm_opt . '[slots][' . $ahfcm_slot . ']', $ahfcm_slot, $settings['slots'][ $ahfcm_slot ], 'global' ); ?>
				</div>
			</div>
		<?php endforeach; ?>

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
