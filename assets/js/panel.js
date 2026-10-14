/**
 * Header & Footer Code panel, used in two places:
 *
 * - mode "builder": Breakdance / Oxygen builder. A button is inserted right
 *   after the top-bar section holding the properties button (re-inserted if
 *   the Vue toolbar re-renders).
 * - mode "editor": block editor. Opened by the toolbar icon
 *   (integrations/gutenberg.js). If the "Header & Body Code Embed" meta box is
 *   also on the page, its values are read from and written back to it, so an
 *   Update can never overwrite the panel's changes.
 *
 * Code is saved through /ahfcm/v1.
 */
/* global AHFCMEditor */
( function () {
	'use strict';

	var config = window.AHFCM_PANEL || {};
	var i18n = config.i18n || {};

	var MODE = config.mode === 'editor' ? 'editor' : 'builder';
	var ANCHOR = '.open-properties-toolbar-button';
	var OXYGEN_ANCHOR = '.open-settings-toolbar-button';
	var BUTTON_ID = MODE === 'editor' ? 'ahfcm-toolbar-button' : 'ahfcm-bd-button';
	var ACTIVE = MODE === 'editor' ? 'is-pressed' : 'breakdance-toolbar-icon-button-active';
	var META_BOX = 'ahfcm_code';

	var panel = null; // Backdrop element once built.
	var fields = [];
	var paneDirty = { post: false, global: false };
	var loaded = { post: null, global: null };
	var saving = false;

	function sprintf( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var index = pos ? parseInt( pos, 10 ) - 1 : i++;
			return args[ index ] !== undefined ? args[ index ] : m;
		} );
	}

	/** Current document ID (the builder can switch documents without reloading). */
	function currentDocId() {
		if ( MODE === 'editor' ) {
			return config.docId || 0;
		}
		var fromUrl = parseInt( new URLSearchParams( window.location.search ).get( 'id' ), 10 );
		return fromUrl || config.docId || 0;
	}

	/** Editor mode: the toolbar icon shows a dot while the page has code. */
	function updateToolbarDot( data ) {
		var button = MODE === 'editor' && document.getElementById( BUTTON_ID );
		if ( ! button ) {
			return;
		}
		var has = !! data.enabled && Object.keys( data.slots || {} ).some( function ( slot ) {
			var s = data.slots[ slot ];
			return s.enabled && PARTS.some( function ( part ) {
				return ( s[ part ] || '' ).trim() !== '';
			} );
		} );
		var dot = button.querySelector( '.ahfcm-toolbar-dot' );
		if ( has && ! dot ) {
			dot = document.createElement( 'span' );
			dot.className = 'ahfcm-toolbar-dot';
			dot.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( dot );
		} else if ( ! has && dot ) {
			dot.remove();
		}
	}

	/* ---------- Meta box sync (editor mode) ---------- */

	function metaBox() {
		return MODE === 'editor' ? document.getElementById( META_BOX ) : null;
	}

	function boxInput( name ) {
		var box = metaBox();
		return box ? box.querySelector( '[name="ahfcm' + name + '"]:not([type="hidden"])' ) : null;
	}

	/** Current (possibly unsaved) values of the meta box, or null. */
	function readMetaBox( slots ) {
		if ( ! metaBox() || ! boxInput( '[enabled]' ) ) {
			return null;
		}
		var out = { enabled: boxInput( '[enabled]' ).checked, slots: {} };
		if ( boxInput( '[suppress_global]' ) ) {
			out.suppress_global = boxInput( '[suppress_global]' ).checked;
		}
		slots.forEach( function ( slot ) {
			var prefix = '[slots][' + slot + ']';
			var data = { enabled: boxInput( prefix + '[enabled]' ) ? boxInput( prefix + '[enabled]' ).checked : true };
			PARTS.forEach( function ( part ) {
				var input = boxInput( prefix + '[' + part + ']' );
				var cmField = input && input.closest( '[data-ahfcm-field]' );
				var editor = cmField && cmField.ahfcmField;
				data[ part ] = editor ? editor.value() : ( input ? input.value : '' );
			} );
			out.slots[ slot ] = data;
		} );
		return out;
	}

	/** Copies saved values into the meta box so the next Update posts the same data. */
	function writeMetaBox( data ) {
		if ( ! metaBox() || ! boxInput( '[enabled]' ) ) {
			return;
		}
		var setCheck = function ( input, value ) {
			if ( input && input.checked !== !! value ) {
				input.checked = !! value;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		};
		setCheck( boxInput( '[enabled]' ), data.enabled );
		setCheck( boxInput( '[suppress_global]' ), data.suppress_global );
		Object.keys( data.slots || {} ).forEach( function ( slot ) {
			var prefix = '[slots][' + slot + ']';
			setCheck( boxInput( prefix + '[enabled]' ), data.slots[ slot ].enabled );
			PARTS.forEach( function ( part ) {
				var input = boxInput( prefix + '[' + part + ']' );
				if ( ! input ) {
					return;
				}
				var value = data.slots[ slot ][ part ] || '';
				var cmField = input.closest( '[data-ahfcm-field]' );
				var editor = cmField && cmField.ahfcmField;
				if ( editor && editor.cm ) {
					if ( editor.cm.getValue() !== value ) {
						editor.cm.setValue( value );
					}
					editor.cm.save();
				} else {
					input.value = value;
				}
				if ( editor ) {
					editor.markClean();
				}
			} );
		} );
		var slotEls = metaBox().querySelectorAll( '[data-ahfcm-slot]' );
		Array.prototype.forEach.call( slotEls, function ( el ) {
			if ( el.ahfcmSlot ) {
				el.ahfcmSlot.updateDots();
			}
		} );
		AHFCMEditor.markClean( [] );
	}

	function api( path, method, body ) {
		return window.fetch( config.restUrl + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					throw new Error( ( json && json.message ) || response.statusText );
				}
				return json;
			} );
		} );
	}

	/* ---------- Top-bar button ---------- */

	function buildButton() {
		var section = document.createElement( 'div' );
		section.className = 'topbar-section topbar-section-br ahfcm-bd-topbar';
		section.innerHTML =
			'<div id="' + BUTTON_ID + '" class="breakdance-toolbar-icon-button" role="button" tabindex="0" aria-pressed="false">' +
				'<div class="breakdance-icon" style="width: 18px; height: 18px;">' +
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m8.6 16.6-4.6-4.6 4.6-4.6L7.2 6 1.2 12l6 6 1.4-1.4Zm6.8 0 4.6-4.6-4.6-4.6L16.8 6l6 6-6 6-1.4-1.4Z"/></svg>' +
				'</div>' +
			'</div>';
		var button = section.firstChild;
		button.setAttribute( 'title', i18n.button || '' );
		button.setAttribute( 'aria-label', i18n.button || '' );
		button.addEventListener( 'click', toggle );
		button.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				toggle();
			}
		} );
		return section;
	}

	function mountButton() {
		if ( document.getElementById( BUTTON_ID ) ) {
			return;
		}

		// Breakdance: its own top-bar section right after the properties button.
		var anchor = document.querySelector( ANCHOR );
		var section = anchor && anchor.closest( '.topbar-section' );
		if ( section && section.parentNode ) {
			section.parentNode.insertBefore( buildButton(), section.nextSibling );
			syncButton();
			return;
		}

		// Oxygen: no properties button; sit with the right-hand icons, before "⋯ Settings".
		var settings = document.querySelector( OXYGEN_ANCHOR );
		if ( settings && settings.parentNode ) {
			settings.parentNode.insertBefore( buildButton().firstChild, settings );
			syncButton();
		}
	}

	function syncButton() {
		var button = document.getElementById( BUTTON_ID );
		if ( button ) {
			var open = !! panel && ! panel.hidden;
			button.classList.toggle( ACTIVE, open );
			button.setAttribute( 'aria-pressed', open ? 'true' : 'false' );
		}
	}

	function watchToolbar() {
		var timer;
		new MutationObserver( function () {
			clearTimeout( timer );
			timer = setTimeout( mountButton, 150 );
		} ).observe( document.body, { childList: true, subtree: true } );
		mountButton();
	}

	/* ---------- Panel ---------- */

	function $( selector, root ) {
		return ( root || panel ).querySelector( selector );
	}

	function setState( text, kind ) {
		var el = $( '[data-ahfcm-bd-state]' );
		el.textContent = text || '';
		el.className = 'ahfcm-bd__state' + ( kind ? ' is-' + kind : '' );
	}

	function buildPanel() {
		var template = document.getElementById( 'ahfcm-bd-template' );
		panel = template.content.firstElementChild.cloneNode( true );
		panel.hidden = true;
		document.body.appendChild( panel );

		panel.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === panel ) {
				close();
			}
		} );
		$( '[data-ahfcm-bd-close]' ).addEventListener( 'click', close );
		$( '[data-ahfcm-bd-save]' ).addEventListener( 'click', save );
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-ahfcm-bd-tab]' ), function ( tab ) {
			tab.addEventListener( 'click', function () {
				showTab( tab.getAttribute( 'data-ahfcm-bd-tab' ) );
			} );
		} );

		// Switches, language pills and plain-textarea edits mark their pane dirty.
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-ahfcm-bd-pane]' ), function ( pane ) {
			var name = pane.getAttribute( 'data-ahfcm-bd-pane' );
			pane.addEventListener( 'change', function () {
				paneDirty[ name ] = true;
				refreshState();
			} );
			pane.addEventListener( 'input', function () {
				paneDirty[ name ] = true;
				refreshState();
			} );
		} );

		// Keep keystrokes inside the panel away from Breakdance's shortcuts
		// (Delete, Ctrl+Z, Ctrl+C on elements...). Ctrl/Cmd+S saves the code.
		[ 'keydown', 'keyup', 'keypress' ].forEach( function ( type ) {
			panel.addEventListener( type, function ( e ) {
				if ( type === 'keydown' && ( e.ctrlKey || e.metaKey ) && ( e.key === 's' || e.key === 'S' ) ) {
					e.preventDefault();
					save();
				}
				if ( type === 'keydown' && e.key === 'Escape' && ! e.defaultPrevented && ! panel.querySelector( '.ahfcm-slot.is-fullscreen, .ahfcm-field.is-fullscreen' ) && ! e.target.closest( '.CodeMirror' ) ) {
					close();
				}
				e.stopPropagation();
			} );
		} );

		// Live refresh of the dirty indicator for CodeMirror edits.
		setInterval( function () {
			if ( panel && ! panel.hidden ) {
				refreshState();
			}
		}, 800 );
	}

	function showTab( name ) {
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-ahfcm-bd-tab]' ), function ( tab ) {
			var active = tab.getAttribute( 'data-ahfcm-bd-tab' ) === name;
			tab.classList.toggle( 'is-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-ahfcm-bd-pane]' ), function ( pane ) {
			pane.hidden = pane.getAttribute( 'data-ahfcm-bd-pane' ) !== name;
		} );
		fields.forEach( function ( f ) {
			if ( f.cm ) {
				f.cm.refresh();
			}
		} );
	}

	function paneFields( name ) {
		var pane = $( '[data-ahfcm-bd-pane="' + name + '"]' );
		return fields.filter( function ( f ) {
			return pane && pane.contains( f.el );
		} );
	}

	function isDirty( name ) {
		return !! loaded[ name ] && ( paneDirty[ name ] || AHFCMEditor.isDirty( paneFields( name ) ) );
	}

	function anyDirty() {
		return isDirty( 'post' ) || isDirty( 'global' );
	}

	function refreshState() {
		if ( saving ) {
			return;
		}
		var el = $( '[data-ahfcm-bd-state]' );
		if ( anyDirty() ) {
			setState( i18n.unsaved, 'dirty' );
		} else if ( el.classList.contains( 'is-dirty' ) ) {
			setState( '' );
		}
	}

	/* ---------- Form <-> data ---------- */

	function field( name ) {
		return panel.querySelector( '[name="' + name + '"]:not([type="hidden"])' );
	}

	var PARTS = [ 'html', 'css', 'js' ];

	function fill( base, data ) {
		if ( 'enabled' in data ) {
			field( base + '[enabled]' ).checked = !! data.enabled;
		}
		if ( 'suppress_global' in data && field( base + '[suppress_global]' ) ) {
			field( base + '[suppress_global]' ).checked = !! data.suppress_global;
		}
		Object.keys( data.slots || {} ).forEach( function ( slot ) {
			var s = data.slots[ slot ];
			var prefix = base + '[slots][' + slot + ']';
			var card = null;
			var active = null;
			PARTS.forEach( function ( part ) {
				var textarea = field( prefix + '[' + part + ']' );
				if ( ! textarea ) {
					return;
				}
				textarea.value = s[ part ] || '';
				card = textarea.closest( '[data-ahfcm-slot]' );
				if ( ! active && textarea.value.trim() ) {
					active = part;
				}
			} );
			if ( ! card ) {
				return;
			}
			field( prefix + '[enabled]' ).checked = !! s.enabled;
			card.classList.toggle( 'is-disabled', ! s.enabled );
			// Open the first tab with code; the Slot controller applies it on mount.
			card.setAttribute( 'data-active', active || 'html' );
		} );
	}

	function collect( base, slots ) {
		var out = { slots: {} };
		out.enabled = field( base + '[enabled]' ).checked;
		if ( field( base + '[suppress_global]' ) ) {
			out.suppress_global = field( base + '[suppress_global]' ).checked;
		}
		slots.forEach( function ( slot ) {
			var prefix = base + '[slots][' + slot + ']';
			var data = { enabled: field( prefix + '[enabled]' ).checked };
			PARTS.forEach( function ( part ) {
				var textarea = field( prefix + '[' + part + ']' );
				data[ part ] = textarea ? textarea.value : '';
			} );
			out.slots[ slot ] = data;
		} );
		return out;
	}

	/** Names the first tab and scope notes after the document type. */
	function labelPostPane( res ) {
		var type = String( res.typeLabel || '' ).toLowerCase();
		var tab = $( '[data-ahfcm-bd-post-tab]' );
		var enable = $( '[data-ahfcm-bd-enable-label]' );
		if ( tab ) {
			tab.textContent = res.template ? sprintf( i18n.thisType, type ) : i18n.thisPage;
		}
		if ( enable ) {
			enable.textContent = sprintf( i18n.enableOn, res.template ? type : 'page' );
		}
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-ahfcm-bd-pane="post"] .ahfcm-field__meta span' ), function ( note ) {
			note.textContent = res.template ? sprintf( i18n.scopeType, type ) : i18n.scopePage;
		} );
	}

	/* ---------- Open / load / save / close ---------- */

	function open() {
		if ( ! panel ) {
			buildPanel();
		}
		panel.hidden = false;
		document.documentElement.classList.add( 'ahfcm-bd-open' );
		syncButton();
		load();
	}

	function load() {
		AHFCMEditor.unmount( fields );
		fields = [];
		paneDirty = { post: false, global: false };
		loaded = { post: null, global: null };
		setState( i18n.loading, 'busy' );

		var id = currentDocId();
		var hasPost = !! $( '[data-ahfcm-bd-pane="post"]' ) && !! id;
		var requests = [];

		if ( hasPost ) {
			requests.push( api( 'post/' + id ).then( function ( res ) {
				loaded.post = res;
				$( '[data-ahfcm-bd-doc]' ).textContent = sprintf( i18n.thisDoc, res.typeLabel, res.title || '#' + res.id );
				$( '[data-ahfcm-bd-unsupported]' ).hidden = res.supported;
				$( '[data-ahfcm-bd-pane="post"] [data-ahfcm-bd-form]' ).hidden = ! res.supported;
				var fromBox = readMetaBox( Object.keys( res.data.slots ) );
				if ( fromBox ) {
					res.data = fromBox;
				}
				fill( 'post', res.data );
				labelPostPane( res );
			} ) );
		}
		if ( config.canGlobal ) {
			requests.push( api( 'global' ).then( function ( res ) {
				loaded.global = res;
				fill( 'global', res.data );
			} ) );
		}

		Promise.all( requests ).then( function () {
			fields = AHFCMEditor.mount( panel );
			AHFCMEditor.markClean( fields );
			setState( '' );
			var first = hasPost && loaded.post && loaded.post.supported ? 'post' : ( config.canGlobal ? 'global' : 'post' );
			showTab( first );
		} ).catch( function ( err ) {
			setState( sprintf( i18n.loadError, err.message ), 'error' );
		} );
	}

	function save() {
		if ( saving ) {
			return;
		}
		var jobs = [];

		if ( loaded.post && loaded.post.supported && isDirty( 'post' ) ) {
			var postData = collect( 'post', Object.keys( loaded.post.data.slots ) );
			jobs.push( api( 'post/' + loaded.post.id, 'POST', { schema: config.schema, data: postData } ).then( function ( res ) {
				loaded.post = res;
				writeMetaBox( postData );
				updateToolbarDot( postData );
				paneDirty.post = false;
				AHFCMEditor.markClean( paneFields( 'post' ) );
			} ) );
		}
		if ( loaded.global && isDirty( 'global' ) ) {
			jobs.push( api( 'global', 'POST', { schema: config.schema, data: collect( 'global', Object.keys( loaded.global.data.slots ) ) } ).then( function ( res ) {
				loaded.global = res;
				paneDirty.global = false;
				AHFCMEditor.markClean( paneFields( 'global' ) );
			} ) );
		}

		if ( ! jobs.length ) {
			setState( i18n.saved, 'ok' );
			return;
		}

		saving = true;
		setState( i18n.saving, 'busy' );
		Promise.all( jobs ).then( function () {
			saving = false;
			setState( i18n.saved, 'ok' );
		} ).catch( function ( err ) {
			saving = false;
			setState( sprintf( i18n.error, err.message ), 'error' );
		} );
	}

	function close() {
		if ( ! panel || panel.hidden ) {
			return;
		}
		if ( anyDirty() && ! window.confirm( i18n.confirmLose ) ) {
			return;
		}
		AHFCMEditor.closePicker();
		AHFCMEditor.unmount( fields );
		AHFCMEditor.markClean( [] );
		fields = [];
		loaded = { post: null, global: null };
		panel.hidden = true;
		document.documentElement.classList.remove( 'ahfcm-bd-open' );
		syncButton();
		var button = document.getElementById( BUTTON_ID );
		if ( button ) {
			button.focus();
		}
	}

	function toggle() {
		if ( panel && ! panel.hidden ) {
			close();
		} else {
			open();
		}
	}

	if ( ! window.AHFCMEditor || ! document.getElementById( 'ahfcm-bd-template' ) ) {
		return;
	}

	window.AHFCMPanel = { open: open, close: close, toggle: toggle };

	if ( MODE === 'builder' ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', watchToolbar );
		} else {
			watchToolbar();
		}
	}
}() );
