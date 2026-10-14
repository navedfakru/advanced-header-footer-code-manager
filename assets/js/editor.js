/**
 * Editor UI: CodeMirror setup, language switching, formatting, diagnostics,
 * full screen, resizing, tabs and unsaved-change tracking.
 *
 * Works on Settings → Header & Footer Code and in the post meta box (Classic
 * and block editor). Falls back to plain textareas when the user has syntax
 * highlighting turned off in their profile.
 */
/* global jQuery, wp, AHFCMCore */
( function ( $ ) {
	'use strict';

	var config = window.AHFCM_EDITOR || {};
	var i18n = config.i18n || {};
	var core = window.AHFCMCore;
	var fields = [];
	var submitting = false;

	function sprintf( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( match, pos ) {
			var index = pos ? parseInt( pos, 10 ) - 1 : i++;
			return args[ index ] !== undefined ? args[ index ] : match;
		} );
	}

	function speak( message ) {
		if ( wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message, 'polite' );
		}
	}

	/**
	 * One editor field.
	 *
	 * @param {HTMLElement} el
	 */
	function Field( el ) {
		this.el = el;
		this.$el = $( el );
		this.textarea = el.querySelector( 'textarea' );
		// Fields live inside a slot card (HTML/CSS/JS tabs); full screen and
		// the toolbar belong to the card.
		this.slotEl = el.closest( '[data-ahfcm-slot]' );
		this.fsEl = this.slotEl || el;
		this.cursor = el.querySelector( '[data-ahfcm-cursor]' );
		this.status = el.querySelector( '[data-ahfcm-status]' );
		this.hint = el.querySelector( '[data-ahfcm-hint]' );
		this.badge = el.querySelector( '[data-ahfcm-badge]' );
		this.label = el.querySelector( 'label' ) || ( this.slotEl && this.slotEl.querySelector( '.ahfcm-field__title' ) );
		this.cm = null;
		this.initial = this.textarea.value;
		this.lintSummary = '';

		this.mount();
		this.bind();
		this.updateDiagnostics();
	}

	Field.prototype.language = function () {
		return this.el.getAttribute( 'data-language' ) || 'html';
	};

	Field.prototype.value = function () {
		return this.cm ? this.cm.getValue() : this.textarea.value;
	};

	/**
	 * Creates the CodeMirror instance for the current language.
	 */
	Field.prototype.mount = function () {
		var self = this;
		var language = this.language();
		var editors = config.editors;

		if ( ! editors || ! editors[ language ] || ! wp || ! wp.codeEditor ) {
			this.el.classList.add( 'is-plain' );
			return;
		}

		var settings = $.extend( true, {}, editors[ language ] );
		settings.codemirror = settings.codemirror || {};
		settings.codemirror.screenReaderLabel = this.label ? this.label.textContent : '';
		settings.codemirror.extraKeys = $.extend( {}, settings.codemirror.extraKeys, {
			'Shift-Alt-F': function () {
				self.format();
			},
			// Ctrl-Q on every platform: Cmd-Q would quit the browser on macOS.
			'Ctrl-Q': function ( cm ) {
				cm.foldCode( cm.getCursor() );
			},
			F11: function () {
				self.toggleFullscreen();
			},
			Esc: function ( cm ) {
				if ( self.fsEl.classList.contains( 'is-fullscreen' ) ) {
					self.toggleFullscreen( false );
				} else {
					// Let core's tabbing helper see Escape (next Tab leaves the editor).
					return wp.CodeMirror.Pass;
				}
			}
		} );

		// Lint results feed the status line.
		var lint = settings.codemirror.lint;
		if ( lint ) {
			settings.codemirror.lint = $.extend( {}, typeof lint === 'object' ? lint : {}, {
				onUpdateLinting: function ( annotations ) {
					var counts = core.countIssues( annotations );
					self.lintSummary = counts.errors || counts.warnings ? sprintf( i18n.issues, counts.errors, counts.warnings ) : i18n.noIssues;
					self.el.classList.toggle( 'has-errors', counts.errors > 0 );
					self.renderStatus();
				}
			} );
		}

		// Esc then Tab moves focus out of the editor.
		settings.onTabNext = function () {
			focusSibling( self.cm.getWrapperElement(), 1 );
		};
		settings.onTabPrevious = function () {
			focusSibling( self.cm.getWrapperElement(), -1 );
		};

		var instance = wp.codeEditor.initialize( this.textarea, settings );
		this.cm = instance.codemirror;
		this.cm.setSize( null, config.height || 320 );

		// Keep the textarea in sync: the block editor serializes meta boxes
		// with FormData and never fires the form's submit event.
		this.cm.on( 'change', function () {
			self.cm.save();
			self.onChange();
		} );
		this.cm.on( 'cursorActivity', function () {
			self.renderCursor();
		} );
		this.renderCursor();

		// Inline color swatches (CSS mode and <style> blocks).
		this.swatches = [];
		this.cm.on( 'viewportChange', function () {
			self.scheduleColorize();
		} );
		this.scheduleColorize();

		this.observeVisibility();
	};

	/**
	 * Tears down CodeMirror and restores the textarea (value preserved).
	 */
	Field.prototype.unmount = function () {
		this.clearSwatches();
		if ( this.cm ) {
			this.cm.save();
			this.cm.toTextArea();
			this.cm = null;
		}
		if ( this.visibilityObserver ) {
			this.visibilityObserver.disconnect();
			this.visibilityObserver = null;
		}
		this.lintSummary = '';
		this.el.classList.remove( 'has-errors', 'is-plain' );
	};

	/**
	 * Refreshes CodeMirror when it becomes visible (collapsed meta boxes,
	 * inactive tabs and the block editor's meta box area start hidden).
	 */
	/**
	 * Fully removes the editor and every listener this Field added.
	 */
	Field.prototype.destroy = function () {
		this.unmount();
		this.$el.off();
		$( this.textarea ).off( 'input' );
		if ( this.resizeObserver ) {
			this.resizeObserver.disconnect();
			this.resizeObserver = null;
		}
		clearTimeout( this.diagTimer );
		clearTimeout( this.colorTimer );
		this.destroyed = true;
	};

	Field.prototype.observeVisibility = function () {
		var self = this;
		if ( ! window.IntersectionObserver ) {
			return;
		}
		this.visibilityObserver = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting && self.cm ) {
					self.cm.refresh();
				}
			} );
		} );
		this.visibilityObserver.observe( this.el );
	};

	Field.prototype.bind = function () {
		var self = this;

		if ( ! this.cm ) {
			$( this.textarea ).on( 'input', function () {
				self.onChange();
			} );
		}

		// Drag-to-resize: the wrapper uses CSS resize; mirror its height into CodeMirror.
		var resizable = this.el.querySelector( '[data-ahfcm-resize]' );
		if ( resizable && window.ResizeObserver ) {
			this.resizeObserver = new ResizeObserver( function () {
				if ( self.cm && ! self.fsEl.classList.contains( 'is-fullscreen' ) && resizable.style.height ) {
					self.cm.setSize( null, resizable.clientHeight );
				}
			} ).observe( resizable );
		}
	};

	/* ---------- Color swatches ---------- */

	var colorProbe = null;

	/**
	 * Resolves any CSS color (including named colors) to RGBA via the browser.
	 *
	 * @param {string} value
	 * @return {{r:number,g:number,b:number,a:number}|null}
	 */
	function resolveColor( value ) {
		if ( ! window.CSS || ! CSS.supports || ! CSS.supports( 'color', value ) ) {
			return null;
		}
		var parsed = core.parseColor( value );
		if ( parsed ) {
			return parsed;
		}
		if ( ! colorProbe ) {
			colorProbe = document.createElement( 'span' );
			colorProbe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
			document.body.appendChild( colorProbe );
		}
		colorProbe.style.color = '';
		colorProbe.style.color = value;
		return core.parseColor( getComputedStyle( colorProbe ).color );
	}

	Field.prototype.clearSwatches = function () {
		( this.swatches || [] ).forEach( function ( bookmark ) {
			bookmark.clear();
		} );
		this.swatches = [];
	};

	Field.prototype.scheduleColorize = function () {
		var self = this;
		clearTimeout( this.colorTimer );
		this.colorTimer = setTimeout( function () {
			self.colorize();
		}, 250 );
	};

	/**
	 * Adds a clickable swatch before every color in CSS context. Only the
	 * visible part of the document is scanned, so large snippets stay fast.
	 */
	Field.prototype.colorize = function () {
		var self = this;
		var cm = this.cm;
		if ( ! cm || this.picking ) {
			return;
		}
		this.clearSwatches();
		if ( this.language() === 'js' || this.language() === 'javascript' ) {
			return;
		}

		var viewport = cm.getViewport();
		cm.operation( function () {
			for ( var line = viewport.from; line < viewport.to; line++ ) {
				var text = cm.getLine( line );
				if ( ! text || text.length > 2000 ) {
					continue;
				}
				core.findColorTokens( text ).forEach( function ( found ) {
					var token = cm.getTokenAt( { line: line, ch: found.index + 1 } );
					var inner = wp.CodeMirror.innerMode( cm.getMode(), token.state ).mode.name;
					if ( inner !== 'css' || token.type === 'comment' || token.type === 'string' ) {
						return;
					}
					// Named colors are tagged "keyword" by the CSS mode; skip other words.
					if ( found.named && ! /keyword/.test( token.type || '' ) ) {
						return;
					}
					var rgba = resolveColor( found.value );
					if ( rgba ) {
						self.addSwatch( line, found.index, found.value, rgba );
					}
				} );
			}
		} );
	};

	/**
	 * Lightweight color picker popover (own UI, not the native dialog).
	 * Lives on <body>, outside CodeMirror, so editor focus and re-renders
	 * can never close it unexpectedly. Supports opacity.
	 */
	var Picker = {
		el: null,
		state: { h: 0, s: 0, v: 0, a: 1 },
		handlers: null,

		build: function () {
			var el = document.createElement( 'div' );
			el.className = 'ahfcm-picker';
			el.setAttribute( 'role', 'dialog' );
			el.setAttribute( 'aria-label', i18n.pickColor || 'Color picker' );
			el.hidden = true;
			el.innerHTML =
				'<div class="ahfcm-picker__sv" tabindex="0" aria-label="' + ( i18n.saturation || 'Saturation and brightness' ) + '"><span class="ahfcm-picker__thumb"></span></div>' +
				'<div class="ahfcm-picker__controls">' +
					'<span class="ahfcm-picker__preview"><span></span></span>' +
					'<div class="ahfcm-picker__sliders">' +
						'<input type="range" class="ahfcm-picker__hue" min="0" max="360" step="1" aria-label="' + ( i18n.hue || 'Hue' ) + '">' +
						'<input type="range" class="ahfcm-picker__alpha" min="0" max="100" step="1" aria-label="' + ( i18n.opacity || 'Opacity' ) + '">' +
					'</div>' +
				'</div>' +
				'<div class="ahfcm-picker__row">' +
					'<input type="text" class="ahfcm-picker__text" spellcheck="false" aria-label="' + ( i18n.colorValue || 'Color value' ) + '">' +
					( window.EyeDropper ? '<button type="button" class="ahfcm-btn ahfcm-btn--icon ahfcm-picker__drop" title="' + ( i18n.eyedropper || 'Pick from screen' ) + '" aria-label="' + ( i18n.eyedropper || 'Pick from screen' ) + '"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M20.7 5.6 18.4 3.3a1 1 0 0 0-1.4 0l-3.1 3.1-1.4-1.4-1.4 1.4 1.4 1.4-8.5 8.5V20h3.7l8.5-8.5 1.4 1.4 1.4-1.4-1.4-1.4 3.1-3.1a1 1 0 0 0 0-1.4ZM6.9 18H6v-.9l8.1-8.1.9.9L6.9 18Z"/></svg></button>' : '' ) +
				'</div>';
			document.body.appendChild( el );
			this.el = el;
			this.sv = el.querySelector( '.ahfcm-picker__sv' );
			this.thumb = el.querySelector( '.ahfcm-picker__thumb' );
			this.hue = el.querySelector( '.ahfcm-picker__hue' );
			this.alpha = el.querySelector( '.ahfcm-picker__alpha' );
			this.text = el.querySelector( '.ahfcm-picker__text' );
			this.preview = el.querySelector( '.ahfcm-picker__preview span' );
			this.bind();
		},

		bind: function () {
			var self = this;

			function fromPointer( e ) {
				var r = self.sv.getBoundingClientRect();
				self.state.s = Math.min( 1, Math.max( 0, ( e.clientX - r.left ) / r.width ) );
				self.state.v = 1 - Math.min( 1, Math.max( 0, ( e.clientY - r.top ) / r.height ) );
				self.emit();
			}
			this.sv.addEventListener( 'pointerdown', function ( e ) {
				e.preventDefault();
				self.sv.setPointerCapture( e.pointerId );
				self.sv.focus();
				fromPointer( e );
				self.sv.onpointermove = fromPointer;
			} );
			this.sv.addEventListener( 'pointerup', function () {
				self.sv.onpointermove = null;
			} );
			this.sv.addEventListener( 'keydown', function ( e ) {
				var step = e.shiftKey ? 0.1 : 0.01;
				var moves = { ArrowLeft: [ -step, 0 ], ArrowRight: [ step, 0 ], ArrowUp: [ 0, step ], ArrowDown: [ 0, -step ] };
				if ( moves[ e.key ] ) {
					e.preventDefault();
					self.state.s = Math.min( 1, Math.max( 0, self.state.s + moves[ e.key ][ 0 ] ) );
					self.state.v = Math.min( 1, Math.max( 0, self.state.v + moves[ e.key ][ 1 ] ) );
					self.emit();
				}
			} );
			this.hue.addEventListener( 'input', function () {
				self.state.h = parseFloat( self.hue.value );
				self.emit();
			} );
			this.alpha.addEventListener( 'input', function () {
				self.state.a = parseFloat( self.alpha.value ) / 100;
				self.emit();
			} );
			this.text.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					self.applyText();
				}
			} );
			this.text.addEventListener( 'change', function () {
				self.applyText();
			} );
			var drop = this.el.querySelector( '.ahfcm-picker__drop' );
			if ( drop ) {
				drop.addEventListener( 'click', function () {
					new window.EyeDropper().open().then( function ( result ) {
						self.setFromRgba( core.parseColor( result.sRGBHex ) || resolveColor( result.sRGBHex ), true );
						self.emit();
					} ).catch( function () {} );
				} );
			}

			// Close on outside click or Escape.
			document.addEventListener( 'pointerdown', function ( e ) {
				if ( ! self.el.hidden && ! self.el.contains( e.target ) && ( ! self.anchor || ! self.anchor.contains( e.target ) ) ) {
					self.close();
				}
			}, true );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && ! self.el.hidden ) {
					e.stopPropagation();
					self.close( true );
				}
			}, true );
			window.addEventListener( 'resize', function () {
				self.close();
			} );
		},

		setFromRgba: function ( rgba, keepAlpha ) {
			if ( ! rgba ) {
				return;
			}
			var hsv = core.rgbToHsv( rgba.r, rgba.g, rgba.b );
			// Keep the hue for greys so the square does not jump.
			this.state.h = hsv.s ? hsv.h : this.state.h;
			this.state.s = hsv.s;
			this.state.v = hsv.v;
			if ( ! keepAlpha ) {
				this.state.a = typeof rgba.a === 'number' ? rgba.a : 1;
			}
		},

		applyText: function () {
			var value = this.text.value.trim();
			var rgba = resolveColor( value );
			if ( ! rgba ) {
				this.text.classList.add( 'is-invalid' );
				return;
			}
			this.text.classList.remove( 'is-invalid' );
			this.setFromRgba( rgba );
			this.render( true );
			// Typed values are written exactly as typed.
			if ( this.handlers ) {
				this.handlers.onInput( value );
			}
		},

		current: function () {
			var rgb = core.hsvToRgb( this.state.h, this.state.s, this.state.v );
			rgb.a = Math.round( this.state.a * 100 ) / 100;
			return rgb;
		},

		render: function ( skipText ) {
			var c = this.current();
			var hex = core.toHex( c );
			this.sv.style.background = 'linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, hsl(' + this.state.h + ', 100%, 50%))';
			this.thumb.style.left = ( this.state.s * 100 ) + '%';
			this.thumb.style.top = ( ( 1 - this.state.v ) * 100 ) + '%';
			this.thumb.style.background = hex;
			this.hue.value = Math.round( this.state.h );
			this.alpha.value = Math.round( this.state.a * 100 );
			this.alpha.style.setProperty( '--ahfcm-alpha-color', hex );
			this.preview.style.background = 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + c.a + ')';
			if ( ! skipText && this.handlers ) {
				this.text.value = core.formatColorLike( this.handlers.original, hex, c.a );
				this.text.classList.remove( 'is-invalid' );
			}
		},

		emit: function () {
			this.render();
			if ( this.handlers ) {
				this.handlers.onInput( this.text.value );
			}
		},

		open: function ( anchor, value, rgba, handlers ) {
			if ( ! this.el ) {
				this.build();
			}
			this.close();
			this.anchor = anchor;
			this.handlers = handlers;
			this.setFromRgba( rgba );
			this.render( true );
			this.text.value = value;

			this.el.hidden = false;
			var r = anchor.getBoundingClientRect();
			var w = this.el.offsetWidth;
			var h = this.el.offsetHeight;
			var top = r.bottom + 6;
			if ( top + h > window.innerHeight - 8 ) {
				top = Math.max( 8, r.top - h - 6 );
			}
			this.el.style.top = top + 'px';
			this.el.style.left = Math.max( 8, Math.min( r.left - 8, window.innerWidth - w - 8 ) ) + 'px';
			this.sv.focus();
		},

		close: function ( refocus ) {
			if ( ! this.el || this.el.hidden ) {
				return;
			}
			this.el.hidden = true;
			var handlers = this.handlers;
			this.handlers = null;
			this.anchor = null;
			if ( handlers ) {
				handlers.onClose( refocus );
			}
		}
	};

	Field.prototype.addSwatch = function ( line, ch, value, rgba ) {
		var self = this;
		var cm = this.cm;
		var length = value.length;

		var widget = document.createElement( 'span' );
		widget.className = 'ahfcm-swatch';
		widget.setAttribute( 'role', 'button' );
		widget.setAttribute( 'aria-label', ( i18n.pickColor || '' ) + ' ' + value );
		widget.title = i18n.pickColor || '';
		var chip = document.createElement( 'span' );
		chip.style.background = value;
		widget.appendChild( chip );

		var bookmark = cm.setBookmark( { line: line, ch: ch }, { widget: widget, insertLeft: true } );
		this.swatches.push( bookmark );

		widget.addEventListener( 'mousedown', function ( e ) {
			// Keep CodeMirror from moving the cursor or stealing focus.
			e.preventDefault();
			e.stopPropagation();
		} );
		widget.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var pos = bookmark.find();
			if ( ! pos ) {
				return;
			}
			var current = cm.getRange( pos, { line: pos.line, ch: pos.ch + length } );
			var session = '*ahfcm-color-' + Date.now();
			self.picking = true;

			Picker.open( widget, current, resolveColor( current ) || rgba, {
				original: current,
				onInput: function ( next ) {
					var at = bookmark.find();
					if ( ! at || ! next ) {
						return;
					}
					// One undo step per picker session.
					cm.replaceRange( next, at, { line: at.line, ch: at.ch + length }, session );
					length = next.length;
					chip.style.background = next;
				},
				onClose: function ( refocus ) {
					self.picking = false;
					self.scheduleColorize();
					if ( refocus ) {
						cm.focus();
					}
				}
			} );
		} );
	};

	Field.prototype.onChange = function () {
		var self = this;
		this.scheduleColorize();
		if ( this.slot ) {
			this.slot.updateDots();
		}
		markDirty();
		clearTimeout( this.diagTimer );
		this.diagTimer = setTimeout( function () {
			self.updateDiagnostics();
		}, 400 );
	};

	/**
	 * Language-level warnings (PHP tags, tags in CSS/JS mode, bare code in HTML mode).
	 */
	Field.prototype.updateDiagnostics = function () {
		var warnings = core.diagnose( this.value(), this.language() );
		var messages = i18n.diagnostics || {};
		this.hint.textContent = warnings.map( function ( w ) {
			return messages[ w ];
		} ).join( ' ' );
		this.el.classList.toggle( 'has-warnings', warnings.length > 0 );
	};

	Field.prototype.renderCursor = function () {
		if ( ! this.cursor || ! this.cm ) {
			return;
		}
		var pos = this.cm.getCursor();
		this.cursor.textContent = sprintf( i18n.cursor, pos.line + 1, pos.ch + 1 ) + ' · ' + sprintf( i18n.lines, this.cm.lineCount() );
	};

	Field.prototype.renderStatus = function ( message ) {
		this.status.textContent = message ? message + ( this.lintSummary ? ' · ' + this.lintSummary : '' ) : this.lintSummary;
	};

	Field.prototype.format = function () {
		var result = core.format( this.value(), this.language(), window.beautifier );
		var messages = {
			formatted: i18n.formatted,
			unchanged: i18n.unchanged,
			empty: i18n.empty,
			noFormatter: i18n.noFormatter,
			php: i18n.phpNotFormatted,
			unsafe: i18n.unsafeFormat,
			error: sprintf( i18n.formatError, result.error || '' )
		};

		if ( result.status === 'formatted' ) {
			if ( this.cm ) {
				var cm = this.cm;
				var cursor = cm.getCursor();
				// replaceRange keeps the change in undo history (Ctrl+Z reverts it).
				cm.replaceRange( result.code, { line: cm.firstLine(), ch: 0 }, { line: cm.lastLine(), ch: cm.getLine( cm.lastLine() ).length }, '+format' );
				cm.setCursor( { line: Math.min( cursor.line, cm.lastLine() ), ch: 0 } );
			} else {
				this.textarea.value = result.code;
				this.onChange();
			}
		}

		this.renderStatus( messages[ result.status ] );
		speak( messages[ result.status ] );
	};

	Field.prototype.toggleFullscreen = function ( force ) {
		var on = typeof force === 'boolean' ? force : ! this.fsEl.classList.contains( 'is-fullscreen' );
		var button = this.fsEl.querySelector( '[data-ahfcm-fullscreen]' );
		this.fsEl.classList.toggle( 'is-fullscreen', on );
		document.documentElement.classList.toggle( 'ahfcm-has-fullscreen', on );
		if ( button ) {
			// Icon-only button: the CSS swaps the expand/collapse icon via aria-pressed.
			var label = on ? i18n.exitFullscreen : i18n.fullscreen;
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			button.setAttribute( 'aria-label', label );
			button.setAttribute( 'title', label + ( on ? ' (Esc)' : ' (F11)' ) );
		}
		if ( this.cm ) {
			var resizable = this.el.querySelector( '[data-ahfcm-resize]' );
			this.cm.setSize( null, on ? '100%' : ( resizable && resizable.style.height ? resizable.clientHeight : config.height || 320 ) );
			this.cm.refresh();
			this.cm.focus();
		}
	};

	Field.prototype.isDirty = function () {
		return this.value() !== this.initial;
	};

	Field.prototype.markClean = function () {
		this.initial = this.value();
	};

	/**
	 * A code slot card: HTML / CSS / JS tabs over three Fields, plus the
	 * card's Enabled switch, Format and Full screen buttons (acting on the
	 * visible tab). All three parts are saved together.
	 *
	 * @param {HTMLElement} el
	 */
	function Slot( el ) {
		var self = this;
		this.el = el;
		this.$el = $( el );
		this.fields = [];
		$( el ).find( '[data-ahfcm-field]' ).each( function () {
			if ( this.ahfcmField ) {
				this.ahfcmField.slot = self;
				self.fields.push( this.ahfcmField );
			}
		} );

		this.$el.on( 'click.ahfcmslot', '[data-ahfcm-part-tab]', function ( e ) {
			e.preventDefault();
			self.show( this.getAttribute( 'data-ahfcm-part-tab' ), true );
		} );
		this.$el.on( 'keydown.ahfcmslot', '[data-ahfcm-part-tab]', function ( e ) {
			var tabs = self.tabs();
			var i = tabs.indexOf( this );
			var next = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
			if ( next !== null ) {
				e.preventDefault();
				var tab = tabs[ ( next + tabs.length ) % tabs.length ];
				tab.focus();
				self.show( tab.getAttribute( 'data-ahfcm-part-tab' ) );
			}
		} );
		this.$el.on( 'change.ahfcmslot', '[data-ahfcm-enabled]', function () {
			self.el.classList.toggle( 'is-disabled', ! this.checked );
			markDirty();
		} );
		this.$el.on( 'click.ahfcmslot', '[data-ahfcm-format]', function ( e ) {
			e.preventDefault();
			var field = self.active();
			if ( field ) {
				field.format();
			}
		} );
		this.$el.on( 'click.ahfcmslot', '[data-ahfcm-fullscreen]', function ( e ) {
			e.preventDefault();
			var field = self.active();
			if ( field ) {
				field.toggleFullscreen();
			}
		} );

		this.show( el.getAttribute( 'data-active' ) || 'html' );
		this.updateDots();
	}

	Slot.prototype.tabs = function () {
		return Array.prototype.slice.call( this.el.querySelectorAll( '[data-ahfcm-part-tab]' ) );
	};

	Slot.prototype.active = function () {
		var name = this.el.getAttribute( 'data-active' ) || 'html';
		return this.fields.filter( function ( f ) {
			return f.language() === name;
		} )[ 0 ] || this.fields[ 0 ];
	};

	Slot.prototype.show = function ( name, focus ) {
		var fullscreen = this.el.classList.contains( 'is-fullscreen' );
		this.el.setAttribute( 'data-active', name );
		this.tabs().forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-ahfcm-part-tab' ) === name;
			tab.classList.toggle( 'is-active', on );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.tabIndex = on ? 0 : -1;
		} );
		this.fields.forEach( function ( field ) {
			var on = field.language() === name;
			field.el.hidden = ! on;
			if ( on && field.cm ) {
				if ( fullscreen ) {
					field.cm.setSize( null, '100%' );
				}
				field.cm.refresh();
				if ( focus ) {
					field.cm.focus();
				}
			}
		} );
	};

	/** Dot on each tab that has code. */
	Slot.prototype.updateDots = function () {
		var self = this;
		this.fields.forEach( function ( field ) {
			var dot = self.el.querySelector( '[data-ahfcm-part-tab="' + field.language() + '"] .ahfcm-part-dot' );
			if ( dot ) {
				dot.classList.toggle( 'has-code', '' !== field.value().trim() );
			}
		} );
	};

	Slot.prototype.destroy = function () {
		this.$el.off( '.ahfcmslot' );
		this.fields.forEach( function ( field ) {
			field.slot = null;
		} );
		this.el.ahfcmSlot = null;
	};

	/** Creates Slot controllers for cards inside root (after their Fields). */
	function mountSlots( root ) {
		$( root ).find( '[data-ahfcm-slot]' ).addBack( '[data-ahfcm-slot]' ).each( function () {
			if ( ! this.ahfcmSlot ) {
				this.ahfcmSlot = new Slot( this );
			}
		} );
	}

	/**
	 * Focuses the next/previous focusable element outside an editor.
	 */
	function focusSibling( from, direction ) {
		var focusable = $( 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' )
			.filter( ':visible' )
			.filter( function () {
				return ! from.contains( this );
			} )
			.toArray();
		var all = $( focusable.concat( [ from ] ) ).toArray().sort( function ( a, b ) {
			return a.compareDocumentPosition( b ) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
		} );
		var target = all[ all.indexOf( from ) + direction ];
		if ( target ) {
			target.focus();
		}
	}

	/* ---------- Unsaved changes ---------- */

	var formDirty = false;

	function markDirty() {
		formDirty = true;
		$( '[data-ahfcm-dirty]' ).prop( 'hidden', false );
	}

	function hasUnsaved() {
		return formDirty;
	}

	function markAllClean() {
		formDirty = false;
		fields.forEach( function ( f ) {
			f.markClean();
		} );
		$( '[data-ahfcm-dirty]' ).prop( 'hidden', true );
	}

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( submitting || ! hasUnsaved() ) {
			return;
		}
		e.preventDefault();
		e.returnValue = i18n.unsaved;
		return i18n.unsaved;
	} );

	/* ---------- Settings page tabs ---------- */

	function initTabs() {
		var $tabs = $( '[data-ahfcm-tab]' );
		if ( ! $tabs.length ) {
			return;
		}

		function show( name ) {
			if ( ! $( '[data-ahfcm-panel="' + name + '"]' ).length ) {
				name = 'code';
			}
			$tabs.each( function () {
				var active = this.getAttribute( 'data-ahfcm-tab' ) === name;
				this.classList.toggle( 'is-active', active );
				this.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			$( '[data-ahfcm-panel]' ).each( function () {
				this.hidden = this.getAttribute( 'data-ahfcm-panel' ) !== name;
			} );
			$( '[data-ahfcm-submit]' ).prop( 'hidden', name !== 'code' && name !== 'settings' );
			fields.forEach( function ( f ) {
				if ( f.cm ) {
					f.cm.refresh();
				}
			} );
		}

		$tabs.on( 'click', function ( e ) {
			e.preventDefault();
			var name = this.getAttribute( 'data-ahfcm-tab' );
			history.replaceState( null, '', '#' + name );
			show( name );
		} );

		show( ( window.location.hash || '#code' ).slice( 1 ) );
	}

	/* ---------- Boot ---------- */

	function trackDirtyInputs() {
		$( '[data-ahfcm-root]' ).on( 'change', 'input, select', function () {
			if ( ! $( this ).closest( '[data-ahfcm-field]' ).length ) {
				markDirty();
			}
		} );
	}

	function watchSaves() {
		// Classic editor and settings form.
		$( document ).on( 'submit', 'form', function () {
			fields.forEach( function ( f ) {
				if ( f.cm ) {
					f.cm.save();
				}
			} );
			submitting = true;
		} );

		// Block editor: meta boxes are saved after the post save completes.
		if ( wp && wp.data && wp.data.select( 'core/editor' ) ) {
			var wasSaving = false;
			wp.data.subscribe( function () {
				var editor = wp.data.select( 'core/editor' );
				var saving = editor.isSavingPost() && ! editor.isAutosavingPost();
				var metaSaving = wp.data.select( 'core/edit-post' ) && wp.data.select( 'core/edit-post' ).isSavingMetaBoxes && wp.data.select( 'core/edit-post' ).isSavingMetaBoxes();
				if ( wasSaving && ! saving && ! metaSaving ) {
					markAllClean();
				}
				wasSaving = saving || metaSaving;
			} );
		}

		// Collapsing/expanding a meta box.
		$( document ).on( 'postbox-toggled', function () {
			fields.forEach( function ( f ) {
				if ( f.cm ) {
					f.cm.refresh();
				}
			} );
		} );
	}

	$( function () {
		if ( ! core ) {
			return;
		}
		$( '[data-ahfcm-field]' ).each( function () {
			if ( ! this.ahfcmField ) {
				this.ahfcmField = new Field( this );
				fields.push( this.ahfcmField );
			}
		} );
		mountSlots( document.body );
		initTabs();
		trackDirtyInputs();
		watchSaves();
	} );

	/**
	 * Public API for dynamically rendered editors (Breakdance builder panel).
	 */
	window.AHFCMEditor = {
		/**
		 * Creates editors for every field inside root.
		 *
		 * @param {HTMLElement} root
		 * @return {Field[]}
		 */
		mount: function ( root ) {
			var created = [];
			$( root ).find( '[data-ahfcm-field]' ).each( function () {
				if ( ! this.ahfcmField ) {
					this.ahfcmField = new Field( this );
					created.push( this.ahfcmField );
					fields.push( this.ahfcmField );
				}
			} );
			mountSlots( root );
			return created;
		},
		/**
		 * Destroys editors created by mount(); textareas keep their values.
		 *
		 * @param {Field[]} list
		 */
		unmount: function ( list ) {
			( list || [] ).forEach( function ( field ) {
				if ( field.slot ) {
					field.slot.destroy();
				}
				field.destroy();
				field.el.ahfcmField = null;
				fields = fields.filter( function ( f ) {
					return f !== field;
				} );
			} );
		},
		/** @return {boolean} Whether any of the fields changed since mount/markClean. */
		isDirty: function ( list ) {
			return ( list || [] ).some( function ( field ) {
				return field.isDirty();
			} );
		},
		markClean: function ( list ) {
			( list || [] ).forEach( function ( field ) {
				field.markClean();
			} );
			// Clears the page-level unsaved flag used by the beforeunload guard.
			formDirty = false;
		},
		closePicker: function () {
			Picker.close();
		}
	};
}( jQuery ) );
