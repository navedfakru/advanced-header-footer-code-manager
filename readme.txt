=== Advanced Header & Footer Code Manager ===
Contributors: navedx
Tags: header, footer, code, breakdance, oxygen
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add HTML, CSS and JavaScript to the head and footer: site-wide, per page, or per Breakdance/Oxygen template, with a CodeMirror editor.

== Description ==

* Two locations, Header (wp_head) and Footer (wp_footer), each with HTML, CSS and JS tabs used together.
* Global code, per-page code, and code on Breakdance/Oxygen templates, headers, footers, popups and global blocks (runs on every page that uses them).
* Edit from a code icon in the block editor toolbar, a code button in the Breakdance/Oxygen top bar, the Classic editor box, or Settings → Header & Footer Code.
* CodeMirror editor (bundled with WordPress): autocomplete, linting, folding, search/replace, safe formatting, inline CSS color picker, full screen, dark/light themes.
* Only administrators can edit code by default; editors can be allowed for page code. PHP is never executed.
* Safe mode, a change log, and earlier versions of global and page code with one-click restore.
* Integrations: Gutenberg, Breakdance, Oxygen 6.

* Position per code card: just before </head> / </body>, or a custom wp_head / wp_footer priority. Page code can follow the global position.

= Output order =

Within the head and the footer, code prints in this order: global code, then the Breakdance/Oxygen header, template(s), footer, popups and global blocks used by the page, then the page's own code. Each snippet prints its CSS (inside <style>), then its HTML exactly as written, then its JavaScript (inside <script>).

= Security =

* Only administrators who can publish unfiltered HTML can add or change code (super admins on multisite). Editors can be allowed to edit page code (Output → "Editors can edit page code"); global code always stays administrator-only. Every save checks a nonce and the user's capabilities.
* Respects the constants hosts use to lock code changes: DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS and DISALLOW_UNFILTERED_HTML lock all code editing (settings page becomes read-only). Existing code keeps running.
* Safe mode stops all code from printing without deleting it: add ?ahfcm_safe_mode=1 to any URL as an administrator (that page only), or define( 'AHFCM_SAFE_MODE', true ) in wp-config.php (everyone, works even when you cannot log in).
* Change log: who changed global code, page code or settings, and when (last 200 changes). Earlier versions of global code and of each page's code (last 10 each) can be compared with the current code and restored.
* Code is stored exactly as typed and output only on normal front-end page views — never in feeds, REST/AJAX responses, embeds or builder previews (unless you allow previews).
* Nothing is sent to external services. To check for updates, WordPress asks the public GitHub API for the latest release of this plugin (at most every 12 hours; no site data is sent).

= For developers =

Filters: ahfcm_integrations, ahfcm_should_inject, ahfcm_editing_locked, ahfcm_snippet_output, ahfcm_post_types, ahfcm_builder_query_params, ahfcm_breakdance_button_visible, ahfcm_panel_replaces_meta_box. Full documentation and source: https://github.com/navedfakru/advanced-header-footer-code-manager

= Third-party code =

Bundles js-beautify 1.15.4 (MIT), unmodified: https://www.npmjs.com/package/js-beautify/v/1.15.4 — source at https://github.com/beautifier/js-beautify

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate it.
3. Go to Settings → Header & Footer Code.

== Frequently Asked Questions ==

= Is my code deleted when I deactivate? =

No. Code is only deleted on plugin deletion, and only if you enabled "Permanently delete all global and per-post code" in Settings.

= Who can edit the code? =

Administrators who have the unfiltered_html capability. If you turn on Output → "Editors can edit page code", users who can edit a page and publish unfiltered HTML (editors on single sites) can change that page's code too. Everyone else doesn't see the editors or the toolbar buttons, and the REST endpoints refuse them. DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS or DISALLOW_UNFILTERED_HTML lock editing for everyone.

= A snippet broke my site. What now? =

Open the page with ?ahfcm_safe_mode=1 added to the URL while logged in as an administrator: no header or footer code prints on that page. Then fix or turn off the snippet, or restore an earlier version under Settings → Header & Footer Code → Tools. If you cannot log in, add define( 'AHFCM_SAFE_MODE', true ); to wp-config.php.

= Can I see who changed the code? =

Yes. Tools → Change log lists every change to global code, page code and settings with the user and time. The log records what changed, not the code itself; earlier code is in the history.

= Does it need Breakdance or Oxygen? =

No. Global and per-page code work with any theme. Breakdance and Oxygen 6 integrations switch on automatically when the builder is active.

= Can I run PHP? =

No. This plugin outputs HTML, CSS and JavaScript only, and never evaluates PHP.

== Upgrade Notice ==

= 2.4.0 =
Editors can no longer edit page code unless you turn on Output → "Editors can edit page code". Adds safe mode, a change log and page code history.

== Screenshots ==

1. Code panel in the block editor, Breakdance and Oxygen: page and global code, each with a Position and HTML, CSS and JS tabs.
2. Integrations: block editor, Breakdance and Oxygen, with toolbar button visibility by role and user.
3. Output: content types with per-page code, global header and footer positions, HTML comment markers.

== Changelog ==

= 2.4.0 =
* Security: only administrators can edit code by default. New setting Output → "Editors can edit page code" (off) lets editors with unfiltered_html edit page code again; global code stays administrator-only.
* Security: DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS lock code editing for everyone, like DISALLOW_UNFILTERED_HTML. The settings page stays open read-only (change log, history, safe mode); existing code keeps running. Filter: ahfcm_editing_locked.
* New: safe mode. ?ahfcm_safe_mode=1 (administrators, that page only) or define( 'AHFCM_SAFE_MODE', true ) in wp-config.php stops all code from printing without deleting it. Shown in the admin bar.
* New: change log (Tools): who changed global code, page code or settings, and when — last 200 changes.
* New: page code history. The last 10 versions of each page's, post's or builder template's code are kept and can be restored from Tools. Global code keeps 10 versions (was 5).
* New: "Show changes" compares any earlier version with the current code before you restore it.
* New: Security card in Help.

= 2.3.1 =
* Updates from GitHub: new versions published as GitHub releases show up in Dashboard → Updates and on the Plugins screen, and install like any other update (automatic updates work too).
* "View details" shows the release notes.
* Translations are loaded by WordPress; the plugin no longer calls load_plugin_textdomain().

= 2.3.0 =
* New: Position setting on every code card — global code, pages, posts, templates, headers, footers, popups and global blocks. Choose "Just before </head>" / "Just before </body>" (printed last in wp_head / wp_footer) or a custom hook priority. Page and builder code can follow the global position (default).
* Each snippet now prints at its own priority; snippets at the same priority keep the order global → layout → page.
* Global positions are edited directly on the Output tab (also shown on the Global code cards, kept in sync).
* Schema 4: existing code prints exactly where it did before. Forms and builder panels opened before the update are refused instead of overwriting code.

= 2.2.1 =
* Brand System v1.0: new plugin icon (SVG + 128/256/512 PNG), one-colour icon and a compact toolbar glyph used by the block editor, Breakdance and Oxygen buttons.
* One set of semantic colour tokens (Electric Violet #5B35E8) shared by the settings app, editor, meta box and code panel; brand dark palette for the panel.
* Settings app: header with the plugin icon, version and description; underline tabs with arrow-key/Home/End navigation; 1200px content width; brand primary buttons and focus rings; save results announced to screen readers.
* Integration cards show "Detected · on", "Detected · turned off" or "Not installed" as text, not colour alone.
* Accessibility: AA contrast checked for every text/background pair, visible focus states, reduced-motion support, disabled states for buttons and switches.
* No changes to stored data, REST routes, hooks, option or meta names.

= 2.2.0 =
* New WindPress-style code structure: namespaced classes in src/ (Core, Admin, Api, Editor, Integration) with a built-in autoloader; views/ for templates; assets split into editor, panel, settings app and integration files.
* Integrations layer (Gutenberg, Breakdance, Oxygen) with an Integrations screen: detection status and an on/off switch per integration. Add more via the ahfcm_integrations filter.
* Oxygen 6 support: builder button (?oxygen=builder), code on oxygen_template/header/footer/block and popups, global block and popup detection from _oxygen_data.
* Modern settings app: sticky top bar with status and Save, top tab navigation (Global code, Integrations, Output, Editor, Tools, Help).
* No data changes; 1.1.x settings and code carry over.


= 1.1.1 =
* Breakdance integration: Header & Footer Code button in the builder top bar, with This page / Global panel.
* Private REST API (ahfcm/v1) for the builder panel, same permission checks as wp-admin.
* Code on Breakdance templates, headers, footers, popups and global blocks — runs on every page that uses them (Settings → Breakdance templates).
* Fix: code now prints after Breakdance's CSS/JS (default priority 1000001), so CSS overrides apply. Old defaults are migrated.
* Fix: global blocks placed with the Global Block element were not detected for head code.
* HTML, CSS and JS can now be used at the same time in each slot (three tabs). Existing code is moved to the matching tab.
* Fix: popup code now loads on pages that open the popup with a button/link, even when the popup's display rules are disabled.
* Block editor toolbar icon (next to “Edit in Breakdance”) that opens the same Header & Footer Code panel as the Breakdance builder, replacing the code box in the block editor (the box stays in the Classic editor).
* Settings → Toolbar buttons: switch the builder button off, show it only for chosen roles, or hide it for specific users.
* Clear warning when an item's "Custom code" switch is off. Saves from pages opened before an update are refused instead of overwriting code.

= 1.1.0 =
* Removed the "after <body>" slot; head and footer only.
* Inline CSS color picker (keeps hex/rgb/hsl notation and alpha).
* Redesigned admin UI: cards, switches, language pills, status bar, sticky save bar.

= 1.0.0 =
* Initial release.

== Third-party libraries ==

* js-beautify 1.15.4 — MIT — assets/vendor/js-beautify/
