/**
 * Pure helpers shared by the editor UI and the Node test suite.
 * No DOM or WordPress dependencies.
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.AHFCMCore = factory();
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var BEAUTIFY_OPTIONS = {
		indent_size: 2,
		indent_char: ' ',
		indent_with_tabs: false,
		preserve_newlines: true,
		max_preserve_newlines: 2,
		wrap_line_length: 0,
		end_with_newline: false,
		indent_inner_html: false,
		indent_scripts: 'keep',
		templating: [ 'auto' ],
		// Leave content whose whitespace is meaningful untouched.
		content_unformatted: [ 'pre', 'textarea', 'code' ]
	};

	/**
	 * Removes all whitespace so two versions of a snippet can be compared
	 * independently of formatting.
	 *
	 * @param {string} code
	 * @return {string}
	 */
	function stripWhitespace( code ) {
		return String( code ).replace( /\s+/g, '' );
	}

	/**
	 * @param {string} code
	 * @return {boolean}
	 */
	function containsPhp( code ) {
		return /<\?(php\b|=)/i.test( code );
	}

	/**
	 * Best-effort guess of a snippet's language.
	 *
	 * @param {string} code
	 * @return {'html'|'css'|'javascript'|null} null when empty.
	 */
	function detectLanguage( code ) {
		var src = String( code ).trim();
		if ( ! src ) {
			return null;
		}
		if ( /^</.test( src ) ) {
			return 'html';
		}
		// "selector { property: value }" or at-rules, without JS keywords.
		if (
			/^(@(media|import|font-face|keyframes|supports|layer|charset|container)\b|[.#:*\[\w-][^{};()=]*\{\s*[\w-]+\s*:)/i.test( src ) &&
			! /\b(function|var|let|const|return|=>|document\.|window\.)/.test( src )
		) {
			return 'css';
		}
		return 'javascript';
	}

	/**
	 * Warnings about a snippet in the selected language.
	 *
	 * @param {string} code
	 * @param {string} language html|css|javascript
	 * @return {string[]} Warning keys: php, wrappedTag, needsTags.
	 */
	function diagnose( code, language ) {
		var warnings = [];
		var src = String( code );
		if ( ! src.trim() ) {
			return warnings;
		}
		if ( containsPhp( src ) ) {
			warnings.push( 'php' );
		}
		if ( ( language === 'css' || language === 'javascript' || language === 'js' ) && /<\/?(script|style|div|meta|link|noscript|iframe)\b/i.test( src ) ) {
			warnings.push( 'wrappedTag' );
		}
		if ( language === 'html' && detectLanguage( src ) !== 'html' && ! /</.test( src ) ) {
			warnings.push( 'needsTags' );
		}
		return warnings;
	}

	/**
	 * Formats code with js-beautify, refusing any change beyond whitespace.
	 *
	 * @param {string} code
	 * @param {string} language html|css|javascript
	 * @param {{html:Function, css:Function, js:Function}|undefined} beautifier
	 * @return {{status:'formatted'|'unchanged'|'empty'|'noFormatter'|'php'|'unsafe'|'error', code:string, error?:string}}
	 */
	function format( code, language, beautifier ) {
		var src = String( code );
		if ( ! src.trim() ) {
			return { status: 'empty', code: src };
		}
		if ( containsPhp( src ) ) {
			return { status: 'php', code: src };
		}
		var isJs = language === 'javascript' || language === 'js';
		var fn = beautifier && ( language === 'css' ? beautifier.css : isJs ? beautifier.js : beautifier.html );
		if ( typeof fn !== 'function' ) {
			return { status: 'noFormatter', code: src };
		}

		var out;
		try {
			out = fn( src, BEAUTIFY_OPTIONS );
		} catch ( e ) {
			return { status: 'error', code: src, error: e && e.message ? e.message : String( e ) };
		}

		if ( typeof out !== 'string' || stripWhitespace( out ) !== stripWhitespace( src ) ) {
			return { status: 'unsafe', code: src };
		}
		if ( out === src ) {
			return { status: 'unchanged', code: src };
		}
		return { status: 'formatted', code: out };
	}

	/**
	 * Summarizes CodeMirror lint annotations.
	 *
	 * @param {Array<{severity:string}>} annotations
	 * @return {{errors:number, warnings:number}}
	 */
	function countIssues( annotations ) {
		var result = { errors: 0, warnings: 0 };
		( annotations || [] ).forEach( function ( a ) {
			if ( a && a.severity === 'error' ) {
				result.errors++;
			} else {
				result.warnings++;
			}
		} );
		return result;
	}

	/* ---------- Colors ---------- */

	/** Candidate color tokens: hex, rgb()/rgba()/hsl()/hsla(), or a bare word (named color). */
	var COLOR_TOKEN = /#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![0-9a-zA-Z_-])|\b(?:rgba?|hsla?)\([^()]*\)|\b[a-zA-Z]{3,20}\b/g;

	/** Keywords that are valid colors but have no single value to pick. */
	var NON_PICKABLE = [ 'transparent', 'currentcolor', 'inherit', 'initial', 'unset', 'revert', 'none' ];

	function clamp( n, min, max ) {
		return Math.min( max, Math.max( min, n ) );
	}

	function round( n, digits ) {
		var f = Math.pow( 10, digits || 0 );
		return Math.round( n * f ) / f;
	}

	/**
	 * Finds color-looking tokens in one line of text.
	 *
	 * @param {string} text
	 * @return {Array<{index:number, value:string, named:boolean}>}
	 */
	function findColorTokens( text ) {
		var out = [];
		var m;
		COLOR_TOKEN.lastIndex = 0;
		while ( ( m = COLOR_TOKEN.exec( text ) ) !== null ) {
			var named = /^[a-zA-Z]+$/.test( m[ 0 ] );
			if ( named && NON_PICKABLE.indexOf( m[ 0 ].toLowerCase() ) !== -1 ) {
				continue;
			}
			out.push( { index: m.index, value: m[ 0 ], named: named } );
		}
		return out;
	}

	/**
	 * Parses hex / rgb() / hsl() (comma or space syntax) into RGBA.
	 * Named colors return null (the browser resolves those).
	 *
	 * @param {string} str
	 * @return {{r:number,g:number,b:number,a:number}|null}
	 */
	function parseColor( str ) {
		var s = String( str ).trim().toLowerCase();
		var m;

		if ( ( m = /^#([0-9a-f]{3,8})$/.exec( s ) ) ) {
			var h = m[ 1 ];
			if ( h.length === 3 || h.length === 4 ) {
				h = h.split( '' ).map( function ( c ) {
					return c + c;
				} ).join( '' );
			}
			if ( h.length !== 6 && h.length !== 8 ) {
				return null;
			}
			return {
				r: parseInt( h.slice( 0, 2 ), 16 ),
				g: parseInt( h.slice( 2, 4 ), 16 ),
				b: parseInt( h.slice( 4, 6 ), 16 ),
				a: h.length === 8 ? round( parseInt( h.slice( 6, 8 ), 16 ) / 255, 3 ) : 1
			};
		}

		if ( ( m = /^(rgba?|hsla?)\(([^)]*)\)$/.exec( s ) ) ) {
			var parts = m[ 2 ].replace( /\//g, ' ' ).split( /[\s,]+/ ).filter( Boolean );
			if ( parts.length < 3 ) {
				return null;
			}
			var alpha = parts[ 3 ] === undefined ? 1 : ( /%$/.test( parts[ 3 ] ) ? parseFloat( parts[ 3 ] ) / 100 : parseFloat( parts[ 3 ] ) );
			if ( m[ 1 ].charAt( 0 ) === 'r' ) {
				var ch = parts.slice( 0, 3 ).map( function ( p ) {
					return /%$/.test( p ) ? parseFloat( p ) * 2.55 : parseFloat( p );
				} );
				if ( ch.some( isNaN ) ) {
					return null;
				}
				return { r: clamp( Math.round( ch[ 0 ] ), 0, 255 ), g: clamp( Math.round( ch[ 1 ] ), 0, 255 ), b: clamp( Math.round( ch[ 2 ] ), 0, 255 ), a: clamp( alpha, 0, 1 ) };
			}
			var rgb = hslToRgb( parseFloat( parts[ 0 ] ), parseFloat( parts[ 1 ] ), parseFloat( parts[ 2 ] ) );
			if ( ! rgb ) {
				return null;
			}
			rgb.a = clamp( alpha, 0, 1 );
			return rgb;
		}

		return null;
	}

	function hslToRgb( h, s, l ) {
		if ( [ h, s, l ].some( isNaN ) ) {
			return null;
		}
		h = ( ( h % 360 ) + 360 ) % 360 / 360;
		s = clamp( s, 0, 100 ) / 100;
		l = clamp( l, 0, 100 ) / 100;
		function f( n ) {
			var k = ( n + h * 12 ) % 12;
			var a = s * Math.min( l, 1 - l );
			return Math.round( 255 * ( l - a * Math.max( -1, Math.min( k - 3, 9 - k, 1 ) ) ) );
		}
		return { r: f( 0 ), g: f( 8 ), b: f( 4 ) };
	}

	function rgbToHsl( r, g, b ) {
		r /= 255;
		g /= 255;
		b /= 255;
		var max = Math.max( r, g, b );
		var min = Math.min( r, g, b );
		var l = ( max + min ) / 2;
		var h = 0;
		var s = 0;
		if ( max !== min ) {
			var d = max - min;
			s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );
			h = max === r ? ( g - b ) / d + ( g < b ? 6 : 0 ) : max === g ? ( b - r ) / d + 2 : ( r - g ) / d + 4;
			h *= 60;
		}
		return { h: Math.round( h ), s: Math.round( s * 100 ), l: Math.round( l * 100 ) };
	}

	/**
	 * @param {{r:number,g:number,b:number}} c
	 * @return {string} #rrggbb
	 */
	function toHex( c ) {
		return '#' + [ c.r, c.g, c.b ].map( function ( n ) {
			return ( '0' + clamp( Math.round( n ), 0, 255 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	/**
	 * Writes a picked #rrggbb color in the same notation as the original
	 * token, keeping its alpha.
	 *
	 * @param {string} original Original token, e.g. "rgba(0, 0, 0, .5)".
	 * @param {string} hex      Picked color, "#rrggbb".
	 * @param {number} [alpha]  New alpha 0–1; defaults to the original's alpha.
	 * @return {string}
	 */
	function formatColorLike( original, hex, alpha ) {
		var picked = parseColor( hex );
		if ( ! picked ) {
			return original;
		}
		var src = String( original ).trim();
		var prev = parseColor( src );
		var a = typeof alpha === 'number' && ! isNaN( alpha ) ? round( clamp( alpha, 0, 1 ), 3 ) : ( prev ? prev.a : 1 );
		var lower = src.toLowerCase();
		var commas = src.indexOf( ',' ) !== -1;
		var out;

		if ( lower.charAt( 0 ) === '#' ) {
			out = toHex( picked );
			if ( a < 1 || src.length === 5 || src.length === 9 ) {
				out += ( '0' + Math.round( a * 255 ).toString( 16 ) ).slice( -2 );
			}
			// Keep the author's letter case.
			return src === src.toUpperCase() && /[A-F]/.test( src ) ? out.toUpperCase() : out;
		}

		if ( /^rgba?\(/.test( lower ) ) {
			var rgb = [ picked.r, picked.g, picked.b ];
			if ( commas ) {
				out = a < 1 ? 'rgba(' + rgb.join( ', ' ) + ', ' + round( a, 3 ) + ')' : lower.indexOf( 'rgba' ) === 0 ? 'rgba(' + rgb.join( ', ' ) + ', 1)' : 'rgb(' + rgb.join( ', ' ) + ')';
			} else {
				out = 'rgb(' + rgb.join( ' ' ) + ( a < 1 ? ' / ' + round( a, 3 ) : '' ) + ')';
			}
			return out;
		}

		if ( /^hsla?\(/.test( lower ) ) {
			var hsl = rgbToHsl( picked.r, picked.g, picked.b );
			if ( commas ) {
				out = a < 1 || lower.indexOf( 'hsla' ) === 0 ? 'hsla(' + hsl.h + ', ' + hsl.s + '%, ' + hsl.l + '%, ' + round( a, 3 ) + ')' : 'hsl(' + hsl.h + ', ' + hsl.s + '%, ' + hsl.l + '%)';
			} else {
				out = 'hsl(' + hsl.h + ' ' + hsl.s + '% ' + hsl.l + '%' + ( a < 1 ? ' / ' + round( a, 3 ) : '' ) + ')';
			}
			return out;
		}

		// Named color: switch to hex (rgba when translucent).
		return a < 1 ? 'rgba(' + [ picked.r, picked.g, picked.b ].join( ', ' ) + ', ' + a + ')' : toHex( picked );
	}

	/**
	 * @param {number} h 0–360
	 * @param {number} s 0–1
	 * @param {number} v 0–1
	 * @return {{r:number,g:number,b:number}}
	 */
	function hsvToRgb( h, s, v ) {
		h = ( ( h % 360 ) + 360 ) % 360;
		function f( n ) {
			var k = ( n + h / 60 ) % 6;
			return Math.round( 255 * ( v - v * s * Math.max( 0, Math.min( k, 4 - k, 1 ) ) ) );
		}
		return { r: f( 5 ), g: f( 3 ), b: f( 1 ) };
	}

	/**
	 * @return {{h:number,s:number,v:number}} h 0–360, s/v 0–1
	 */
	function rgbToHsv( r, g, b ) {
		r /= 255;
		g /= 255;
		b /= 255;
		var max = Math.max( r, g, b );
		var d = max - Math.min( r, g, b );
		var h = 0;
		if ( d ) {
			h = max === r ? ( ( g - b ) / d ) % 6 : max === g ? ( b - r ) / d + 2 : ( r - g ) / d + 4;
			h = ( h * 60 + 360 ) % 360;
		}
		return { h: h, s: max ? d / max : 0, v: max };
	}

	return {
		hsvToRgb: hsvToRgb,
		rgbToHsv: rgbToHsv,
		findColorTokens: findColorTokens,
		parseColor: parseColor,
		toHex: toHex,
		formatColorLike: formatColorLike,
		BEAUTIFY_OPTIONS: BEAUTIFY_OPTIONS,
		stripWhitespace: stripWhitespace,
		containsPhp: containsPhp,
		detectLanguage: detectLanguage,
		diagnose: diagnose,
		format: format,
		countIssues: countIssues
	};
} ) );
