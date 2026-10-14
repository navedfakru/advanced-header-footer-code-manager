<?php
/**
 * Section: Help.
 *
 * @package AHFCM
 */

defined( 'ABSPATH' ) || exit;

use AHFCM\Editor\Fields;
?>
<section class="ahfcm-panel ahfcm-help" id="ahfcm-section-help" data-ahfcm-panel="help" role="tabpanel" aria-labelledby="ahfcm-tab-help" tabindex="0" hidden>
	<header class="ahfcm-section-head">
		<h2><?php esc_html_e( 'Help', 'advanced-header-footer-code-manager' ); ?></h2>
		<p><?php esc_html_e( 'How code is placed, and the editor’s keyboard shortcuts.', 'advanced-header-footer-code-manager' ); ?></p>
	</header>

	<div class="ahfcm-grid">
		<div class="ahfcm-card">
			<h3 class="ahfcm-card__title"><?php esc_html_e( 'How it works', 'advanced-header-footer-code-manager' ); ?></h3>
			<ul class="ahfcm-list">
				<?php foreach ( Fields::slot_labels() as $ahfcm_slot_label ) : ?>
					<li><strong><?php echo esc_html( $ahfcm_slot_label['label'] ); ?></strong> <code class="ahfcm-chip"><?php echo esc_html( $ahfcm_slot_label['where'] ); ?></code><br><?php echo esc_html( $ahfcm_slot_label['description'] ); ?></li>
				<?php endforeach; ?>
				<li><?php esc_html_e( 'Each slot has HTML, CSS and JS tabs, all used together: CSS (in <style>), then HTML as written, then JS (in <script>). PHP is never executed.', 'advanced-header-footer-code-manager' ); ?></li>
				<li><?php esc_html_e( 'Order on a page: global code, then Breakdance/Oxygen header, template, footer, popups and global blocks, then the page itself. A page or template can skip global code.', 'advanced-header-footer-code-manager' ); ?></li>
				<li><?php esc_html_e( 'Edit page code from the code icon in the block editor toolbar, the code button in the Breakdance/Oxygen top bar, or the "Header & Body Code Embed" box in the Classic editor.', 'advanced-header-footer-code-manager' ); ?></li>
			</ul>
		</div>

		<div class="ahfcm-card">
			<h3 class="ahfcm-card__title"><?php esc_html_e( 'Keyboard shortcuts', 'advanced-header-footer-code-manager' ); ?></h3>
			<dl class="ahfcm-keys">
				<dt><kbd>Ctrl</kbd> <kbd>Space</kbd></dt><dd><?php esc_html_e( 'Autocomplete', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Ctrl</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Search', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Shift</kbd> <kbd>Ctrl</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Search and replace', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Alt</kbd> <kbd>G</kbd></dt><dd><?php esc_html_e( 'Jump to line', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Ctrl</kbd> <kbd>/</kbd></dt><dd><?php esc_html_e( 'Toggle comment', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Ctrl</kbd> <kbd>Q</kbd></dt><dd><?php esc_html_e( 'Fold / unfold block', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Shift</kbd> <kbd>Alt</kbd> <kbd>F</kbd></dt><dd><?php esc_html_e( 'Format code', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Ctrl</kbd> <kbd>S</kbd></dt><dd><?php esc_html_e( 'Save (in the panel)', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>F11</kbd></dt><dd><?php esc_html_e( 'Full screen (Esc exits)', 'advanced-header-footer-code-manager' ); ?></dd>
				<dt><kbd>Esc</kbd> <kbd>Tab</kbd></dt><dd><?php esc_html_e( 'Move focus out of the editor', 'advanced-header-footer-code-manager' ); ?></dd>
			</dl>
			<p class="ahfcm-muted"><?php esc_html_e( 'Linting (HTMLHint, CSSLint, Espree) catches common mistakes but cannot prove code is correct or safe. Autocomplete suggests tags, attributes, properties and keywords — it is not full IntelliSense. Format only applies when the result differs in whitespace alone.', 'advanced-header-footer-code-manager' ); ?></p>
		</div>
	</div>
</section>
