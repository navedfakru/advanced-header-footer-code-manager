=== Advanced Header & Footer Code Manager ===
Contributors: naved
Tags: header, footer, code, breakdance, oxygen
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add trusted HTML, CSS and JavaScript to <head> and before </body> — site-wide, per page, or per Breakdance/Oxygen template — from the block editor, the builder or a modern settings app.

== Description ==

* Two locations, Header (wp_head) and Footer (wp_footer), each with HTML, CSS and JS tabs used together.
* Global code, per-page code, and code on Breakdance/Oxygen templates, headers, footers, popups and global blocks (runs on every page that uses them).
* Edit from a code icon in the block editor toolbar, a code button in the Breakdance/Oxygen top bar, the Classic editor box, or Settings → Header & Footer Code.
* CodeMirror editor (bundled with WordPress): autocomplete, linting, folding, search/replace, safe formatting, inline CSS color picker, full screen, dark/light themes.
* Only users with unfiltered_html can edit code. PHP is never executed.
* Integrations: Gutenberg, Breakdance, Oxygen 6.

See README.md for documentation, file structure, hooks and the security model.

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

= 2.2.0 =
* New WindPress-style code structure: namespaced classes in src/ (Core, Admin, Api, Editor, Integration) with a built-in autoloader; views/ for templates; assets split into editor, panel, settings app and integration files.
* Integrations layer (Gutenberg, Breakdance, Oxygen) with an Integrations screen: detection status and an on/off switch per integration. Add more via the ahfcm_integrations filter.
* Oxygen 6 support: builder button (?oxygen=builder), code on oxygen_template/header/footer/block and popups, global block and popup detection from _oxygen_data.
* Modern settings app: sticky top bar with status and Save, sidebar navigation (Global code, Integrations, Output, Editor, Tools, Help).
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
