/**
 * Gutenberg integration: code icon in the block editor top toolbar (next to
 * "Edit in Breakdance" / "Edit in Oxygen").
 *
 * Clicking it opens the Header & Footer Code panel (panel.js, editor mode),
 * which replaces the "Header & Body Code Embed" meta box here. Without
 * the panel it scrolls to and opens the meta box instead.
 */
/* global wp */
( function () {
	'use strict';

	var config = window.AHFCM_GUTENBERG || {};
	var BUTTON_ID = 'ahfcm-toolbar-button';
	var PANEL = 'meta-box-' + config.boxId;

	var ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m8.6 16.6-4.6-4.6 4.6-4.6L7.2 6 1.2 12l6 6 1.4-1.4Zm6.8 0 4.6-4.6-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z"/></svg>';

	function box() {
		return document.getElementById( config.boxId );
	}

	/** Makes sure the meta box is enabled, its area is open, and the box is expanded. */
	function reveal() {
		var editPost = wp.data.select( 'core/edit-post' );
		var dispatch = wp.data.dispatch( 'core/edit-post' );

		// Hidden via Options → Preferences → Panels.
		if ( editPost && editPost.isEditorPanelEnabled && ! editPost.isEditorPanelEnabled( PANEL ) && dispatch.toggleEditorPanelEnabled ) {
			dispatch.toggleEditorPanelEnabled( PANEL );
		}

		var tries = 0;
		( function open() {
			var el = box();

			// The resizable "Meta Boxes" area at the bottom of the editor may be collapsed.
			var area = document.querySelector( '.edit-post-meta-boxes-main' );
			var areaToggle = area && area.querySelector( 'button[aria-expanded="false"]' );
			if ( areaToggle && ( ! el || ! el.offsetParent ) ) {
				areaToggle.click();
			}

			if ( ! el || ! el.offsetParent ) {
				if ( tries++ < 20 ) {
					setTimeout( open, 100 );
				}
				return;
			}

			// Expand the postbox if it is folded.
			if ( el.classList.contains( 'closed' ) ) {
				var handle = el.querySelector( '.handlediv, .postbox-header button[aria-expanded="false"]' );
				if ( handle ) {
					handle.click();
				} else {
					el.classList.remove( 'closed' );
				}
			}

			el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			el.classList.add( 'ahfcm-flash' );
			setTimeout( function () {
				el.classList.remove( 'ahfcm-flash' );
				var cm = el.querySelector( '.ahfcm-field:not([hidden]) .CodeMirror' );
				if ( cm && cm.CodeMirror ) {
					cm.CodeMirror.refresh();
					cm.CodeMirror.focus();
				}
			}, 450 );
		}() );
	}

	function buildButton() {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.id = BUTTON_ID;
		button.className = 'components-button components-toolbar__control is-compact has-icon ahfcm-toolbar-button';
		button.setAttribute( 'aria-label', config.label || '' );
		button.setAttribute( 'title', config.label || '' );
		button.setAttribute( 'data-toolbar-item', 'true' );
		button.innerHTML = ICON + ( config.hasCode ? '<span class="ahfcm-toolbar-dot" aria-hidden="true"></span>' : '' );
		button.setAttribute( 'aria-pressed', 'false' );
		button.addEventListener( 'click', function () {
			// Same panel as in the Breakdance builder; fall back to the meta box.
			if ( window.AHFCMPanel ) {
				window.AHFCMPanel.toggle();
			} else {
				reveal();
			}
		} );
		return button;
	}

	function mount() {
		if ( document.getElementById( BUTTON_ID ) ) {
			return;
		}
		// "Edit in Breakdance" / "Edit in Oxygen" launcher, when present.
		var launcher = document.querySelector( '[id^="breakdance-mode-switch-edit-with"], .breakdance-launcher-small-button' );
		var tools = document.querySelector( '.editor-document-tools' );
		if ( launcher && launcher.parentNode ) {
			launcher.parentNode.insertBefore( buildButton(), launcher.nextSibling );
		} else if ( tools ) {
			( tools.querySelector( '.editor-document-tools__left' ) || tools ).appendChild( buildButton() );
		}
	}

	function injectStyles() {
		var css =
			'.ahfcm-toolbar-button{position:relative;margin-left:6px}' +
			'.ahfcm-toolbar-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--wp-admin-theme-color,#3858e9);box-shadow:0 0 0 2px #fff}' +
			'#' + config.boxId + '.ahfcm-flash{box-shadow:0 0 0 2px var(--wp-admin-theme-color,#3858e9);transition:box-shadow .3s}';
		var style = document.createElement( 'style' );
		style.id = 'ahfcm-toolbar-css';
		style.textContent = css;
		document.head.appendChild( style );
	}

	wp.domReady( function () {
		injectStyles();
		// The editor header is rendered by React and can re-render; keep the button mounted.
		var timer;
		new MutationObserver( function () {
			clearTimeout( timer );
			timer = setTimeout( mount, 120 );
		} ).observe( document.body, { childList: true, subtree: true } );
		mount();
	} );
}() );
