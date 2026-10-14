/**
 * Unit tests for assets/js/core.js using the bundled js-beautify build.
 *
 *     node --test tests/js/
 */
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const core = require( '../../assets/js/core.js' );

// Load the vendored browser build exactly as WordPress serves it.
const sandbox = {};
sandbox.window = sandbox;
vm.createContext( sandbox );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../../assets/vendor/js-beautify/beautifier.min.js' ), 'utf8' ), sandbox );
const beautifier = sandbox.beautifier;

const SNIPPETS = {
	gtagHead: `<!-- Google tag (gtag.js) --><script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script><script>window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', 'G-XXXX');</script>`,
	gtmNoscript: `<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>`,
	jsonLd: `<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"A \\\\ B \\"Q\\"","url":"https://example.com"}</script>`,
	mixed: `<style>@media (max-width:600px){.a{color:red}}</style><div class="x"><span>Hi</span></div><script>const re=/\\d+\\.\\w*/g;const t=\`line \${1+1}\`;if(re.test("1.a")){console.log(t)}</script>`,
	pre: `<pre>  keep
     this   spacing</pre><p>x</p>`,
	css: `.a{color:red;background:url("data:image/svg+xml;utf8,<svg></svg>")}@media (min-width:600px){.b{margin:0 auto}}`,
	js: `(function(w,d){var s=d.createElement('script');s.src='https://x/y.js?a=1&b=2';d.head.appendChild(s);var re=/[a-z]+\\//i;w.x=\`\${re}\`})(window,document);`
};

test( 'formatter loads from the vendored file', () => {
	assert.equal( typeof beautifier.html, 'function' );
	assert.equal( typeof beautifier.css, 'function' );
	assert.equal( typeof beautifier.js, 'function' );
} );

for ( const [ name, code ] of Object.entries( SNIPPETS ) ) {
	const language = name === 'css' ? 'css' : name === 'js' ? 'javascript' : 'html';

	test( `format keeps ${ name } intact apart from whitespace`, () => {
		const result = core.format( code, language, beautifier );
		assert.ok( [ 'formatted', 'unchanged' ].includes( result.status ), `status ${ result.status }` );
		assert.equal( core.stripWhitespace( result.code ), core.stripWhitespace( code ) );
	} );

	test( `format is idempotent for ${ name }`, () => {
		const once = core.format( code, language, beautifier ).code;
		const twice = core.format( once, language, beautifier );
		assert.equal( twice.code, once );
	} );
}

test( 'format leaves <pre> content untouched', () => {
	const out = core.format( SNIPPETS.pre, 'html', beautifier ).code;
	assert.ok( out.includes( '  keep\n     this   spacing' ) );
} );

test( 'format keeps string, regex and template literal contents byte-exact', () => {
	const out = core.format( SNIPPETS.mixed, 'html', beautifier ).code;
	assert.ok( out.includes( '/\\d+\\.\\w*/g' ) );
	assert.ok( out.includes( '`line ${1+1}`' ) || out.includes( '`line ${1 + 1}`' ) );
} );

test( 'format refuses PHP', () => {
	const code = '<?php echo 1; ?><div></div>';
	const result = core.format( code, 'html', beautifier );
	assert.equal( result.status, 'php' );
	assert.equal( result.code, code );
} );

test( 'format refuses output that changes more than whitespace', () => {
	const fake = { html: () => '<div>changed</div>', css: () => '', js: () => '' };
	const result = core.format( '<div>original</div>', 'html', fake );
	assert.equal( result.status, 'unsafe' );
	assert.equal( result.code, '<div>original</div>' );
} );

test( 'format reports a missing formatter without changing code', () => {
	assert.equal( core.format( '<b>x</b>', 'html', undefined ).status, 'noFormatter' );
} );

test( 'format reports formatter exceptions without changing code', () => {
	const boom = { html: () => {
		throw new Error( 'boom' );
	} };
	const result = core.format( '<b>x</b>', 'html', boom );
	assert.equal( result.status, 'error' );
	assert.equal( result.error, 'boom' );
	assert.equal( result.code, '<b>x</b>' );
} );

test( 'format handles empty input', () => {
	assert.equal( core.format( '   \n', 'html', beautifier ).status, 'empty' );
} );

test( 'format handles a large snippet', () => {
	const big = SNIPPETS.mixed.repeat( 2000 ); // ~300 KB
	const result = core.format( big, 'html', beautifier );
	assert.equal( core.stripWhitespace( result.code ), core.stripWhitespace( big ) );
} );

test( 'detectLanguage', () => {
	assert.equal( core.detectLanguage( '' ), null );
	assert.equal( core.detectLanguage( '<script></script>' ), 'html' );
	assert.equal( core.detectLanguage( '.a { color: red }' ), 'css' );
	assert.equal( core.detectLanguage( '@media (max-width:1px){.a{b:c}}' ), 'css' );
	assert.equal( core.detectLanguage( 'console.log(1)' ), 'javascript' );
	assert.equal( core.detectLanguage( 'const a = { b: 1 }' ), 'javascript' );
} );

test( 'diagnose', () => {
	assert.deepEqual( core.diagnose( '<script>x</script>', 'javascript' ), [ 'wrappedTag' ] );
	assert.deepEqual( core.diagnose( '<?php echo 1 ?>', 'html' ), [ 'php' ] );
	assert.deepEqual( core.diagnose( '.a{color:red}', 'html' ), [ 'needsTags' ] );
	assert.deepEqual( core.diagnose( '<style>.a{}</style>', 'html' ), [] );
	assert.deepEqual( core.diagnose( '', 'css' ), [] );
} );

test( 'countIssues', () => {
	assert.deepEqual( core.countIssues( [ { severity: 'error' }, { severity: 'warning' }, { severity: 'warning' } ] ), { errors: 1, warnings: 2 } );
	assert.deepEqual( core.countIssues( null ), { errors: 0, warnings: 0 } );
} );

/* ---------- Color picker helpers ---------- */

test( 'findColorTokens finds hex, functions and words; skips non-pickable keywords', () => {
	const found = core.findColorTokens( 'a{color:#fff;background:rgba(0, 0, 0, .5);border:1px solid red;fill:transparent;x:#12345}' );
	const values = found.map( ( f ) => f.value );
	assert.ok( values.includes( '#fff' ) );
	assert.ok( values.includes( 'rgba(0, 0, 0, .5)' ) );
	assert.ok( values.includes( 'red' ) );
	assert.ok( ! values.includes( 'transparent' ) );
	assert.ok( ! values.includes( '#12345' ), '5-digit hex is not a color' );
	assert.equal( found.find( ( f ) => f.value === 'red' ).named, true );
} );

test( 'parseColor handles hex, rgb, hsl (comma and space syntax)', () => {
	assert.deepEqual( core.parseColor( '#f00' ), { r: 255, g: 0, b: 0, a: 1 } );
	assert.deepEqual( core.parseColor( '#00ff0080' ), { r: 0, g: 255, b: 0, a: 0.502 } );
	assert.deepEqual( core.parseColor( 'rgba(10, 20, 30, .5)' ), { r: 10, g: 20, b: 30, a: 0.5 } );
	assert.deepEqual( core.parseColor( 'rgb(10 20 30 / 25%)' ), { r: 10, g: 20, b: 30, a: 0.25 } );
	assert.deepEqual( core.parseColor( 'hsl(0, 100%, 50%)' ), { r: 255, g: 0, b: 0, a: 1 } );
	assert.equal( core.parseColor( 'red' ), null );
	assert.equal( core.parseColor( 'rgb(1,2)' ), null );
} );

test( 'formatColorLike keeps the original notation and alpha', () => {
	assert.equal( core.formatColorLike( '#fff', '#3366ff' ), '#3366ff' );
	assert.equal( core.formatColorLike( '#FFF', '#3366ff' ), '#3366FF' );
	assert.equal( core.formatColorLike( '#ffffff80', '#3366ff' ), '#3366ff80' );
	assert.equal( core.formatColorLike( 'rgb(0, 0, 0)', '#3366ff' ), 'rgb(51, 102, 255)' );
	assert.equal( core.formatColorLike( 'rgba(0, 0, 0, .5)', '#3366ff' ), 'rgba(51, 102, 255, 0.5)' );
	assert.equal( core.formatColorLike( 'rgb(0 0 0 / 50%)', '#3366ff' ), 'rgb(51 102 255 / 0.5)' );
	assert.equal( core.formatColorLike( 'hsl(0, 0%, 0%)', '#ff0000' ), 'hsl(0, 100%, 50%)' );
	assert.equal( core.formatColorLike( 'hsla(0, 0%, 0%, .3)', '#ff0000' ), 'hsla(0, 100%, 50%, 0.3)' );
	assert.equal( core.formatColorLike( 'red', '#3366ff' ), '#3366ff' );
} );

test( 'formatColorLike round-trips hsl within rounding', () => {
	const out = core.formatColorLike( 'hsl(10, 10%, 10%)', '#4a7bd0' );
	assert.equal( core.toHex( core.parseColor( out ) ).length, 7 );
	const back = core.parseColor( out );
	const orig = core.parseColor( '#4a7bd0' );
	assert.ok( Math.abs( back.r - orig.r ) <= 3 && Math.abs( back.g - orig.g ) <= 3 && Math.abs( back.b - orig.b ) <= 3 );
} );

test( 'hsv <-> rgb round-trip', () => {
	for ( const hex of [ '#000000', '#ffffff', '#ff0000', '#4f46e5', '#77427b', '#b0b07d' ] ) {
		const c = core.parseColor( hex );
		const hsv = core.rgbToHsv( c.r, c.g, c.b );
		assert.equal( core.toHex( core.hsvToRgb( hsv.h, hsv.s, hsv.v ) ), hex );
	}
} );

test( 'formatColorLike applies a new alpha in every notation', () => {
	assert.equal( core.formatColorLike( '#77427b', '#77427b', 0.5 ), '#77427b80' );
	assert.equal( core.formatColorLike( '#77427b80', '#77427b', 1 ), '#77427bff' );
	assert.equal( core.formatColorLike( 'rgb(0, 0, 0)', '#000000', 0.25 ), 'rgba(0, 0, 0, 0.25)' );
	assert.equal( core.formatColorLike( 'hsl(0 0% 0%)', '#ff0000', 0.5 ), 'hsl(0 100% 50% / 0.5)' );
	assert.equal( core.formatColorLike( 'red', '#ff0000', 0.5 ), 'rgba(255, 0, 0, 0.5)' );
	assert.equal( core.formatColorLike( 'red', '#00ff00' ), '#00ff00' );
} );
