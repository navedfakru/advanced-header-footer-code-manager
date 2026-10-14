=== Advanced Header & Footer Code Manager ===
Contributors: naved
Tags: header, footer, code, scripts, codemirror
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add trusted code to <head> and before </body> — site-wide or per post/page — with a CodeMirror editor.

== Description ==

* Two locations: Header (wp_head) and Footer (wp_footer).
* Global code in Settings → Header & Footer Code, plus a "Header & Body Code Embed" box on posts, pages and public custom post types.
* Global and page code are combined (global first). Each page can opt out of global code. Every editor has its own on/off switch.
* Editor: syntax highlighting, autocomplete, linting, folding, search/replace, auto-closing tags, inline CSS color picker, safe formatting, full screen, dark/light themes. Uses the CodeMirror bundled with WordPress.
* HTML, CSS (auto-wrapped in <style>) and JavaScript (auto-wrapped in <script>) modes. PHP is never executed.
* Only users with unfiltered_html can edit code. Nothing is exposed via the REST API.
* Revisions for per-post code, history for global code, optional import from "Page Header & Body Code Embed" v3.0.

See README.md for full documentation, the security model, migration and rollback.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate it.
3. Go to Settings → Header & Footer Code.

== Frequently Asked Questions ==

= Is my code deleted when I deactivate? =

No. Code is only deleted on plugin deletion, and only if you enabled "Permanently delete all global and per-post code" in Settings.

= Can I run PHP? =

No. This plugin outputs HTML, CSS and JavaScript only, and never evaluates PHP.

== Changelog ==

= 1.1.0 =
* Removed the "after <body>" slot; head and footer only.
* Inline CSS color picker (keeps hex/rgb/hsl notation and alpha).
* Redesigned admin UI: cards, switches, language pills, status bar, sticky save bar.

= 1.0.0 =
* Initial release.

== Third-party libraries ==

* js-beautify 1.15.4 — MIT — assets/vendor/js-beautify/
