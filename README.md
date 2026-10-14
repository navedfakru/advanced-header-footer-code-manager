# Advanced Header & Footer Code Manager

Adds trusted code to two places on the front end, site-wide or per post/page,
with a real code editor (CodeMirror) that has syntax highlighting, autocomplete,
linting and safe formatting.

| Field | Hook | Default priority | Where it ends up |
|---|---|---|---|
| **Header Code** | `wp_head` | 1000001 | end of `<head>`, after theme and Breakdance CSS |
| **Footer Code** | `wp_footer` | 1000001 | end of `<body>`, after footer scripts and Breakdance JS |

Priorities can be changed in **Settings → Header & Footer Code → Settings**.
Breakdance prints its CSS/JS at priority 1000000, so the default 1000001
makes your CSS override builder styles (same-specificity rules win by
order) and lets scripts use builder libraries. Sites upgraded from 1.0/1.1
with the old defaults (10/100) are moved automatically; custom values stay.

- Slug / text domain: `advanced-header-footer-code-manager`
- Requires: WordPress 6.4+, PHP 7.4+ (developed on WP 7.1.3, PHP 8.2.29)
- License: GPL-2.0-or-later. Bundles js-beautify 1.15.4 (MIT).

---

## Installation

1. Copy `advanced-header-footer-code-manager/` into `wp-content/plugins/`.
2. Activate it in **Plugins**.
3. Go to **Settings → Header & Footer Code** for site-wide code.
4. Open any post, page or public custom post type entry to find the
   **Header & Body Code Embed** box.

Activation only adds two options (`ahfcm_global_settings`, `ahfcm_version`).
It does not change any existing data.

## Global vs. per-page code

- **Global code** runs on every front-end page.
- **Per-page code** (the meta box) runs only on that post/page/CPT entry.
- When both apply, **both are printed — global first, then page code** — in
  each location. Page code never replaces global code by accident.
- A page can opt out deliberately with **"Do not load global code on this page"**.
- Every editor has its own **Enabled** switch, and there is a master switch for
  global code and one per page. Switching off keeps the code saved.

Execution order for a request to page 42:

```
<head>   … global Header Code → page 42 Header Code … </head>
<body>   … global Footer Code → page 42 Footer Code </body>
```

With **Output markers** on (default) each snippet is wrapped in
`<!-- AHFCM head (global) -->` / `<!-- AHFCM head (post 42) -->` comments, so
you can find it in View Source.

### Where code is *not* injected

Admin screens, AJAX, REST API, feeds, embeds, `robots.txt`, cron, XML-RPC, and
page-builder editor/preview requests (Breakdance `?breakdance=…` /
`?breakdance_iframe`, Oxygen, Elementor, Beaver Builder, Divi, Bricks). Builder
injection can be switched on in Settings. Use the `ahfcm_should_inject` filter
for custom rules.

## Why there is no "after `<body>`" field

Version 1.1.0 removed the body-start slot to keep the plugin focused on head
and footer code. Anything that used to go right after `<body>` (for example
a tag-manager `<noscript>` iframe) works in Footer Code. Body-start data saved
by 1.0.0 is dropped on upgrade and never printed.

## HTML, CSS and JS together

Every slot (Header Code, Footer Code) has three tabs that are **all used at
the same time**:

| Tab | What to put there | Output |
|---|---|---|
| **HTML** | tags, `<script src>`, `<link>`, `<meta>`, JSON-LD, noscript… | exactly as written |
| **CSS** | plain CSS rules | wrapped in `<style>` |
| **JS** | plain JavaScript | wrapped in `<script>` |

Each slot prints **CSS → HTML → JS**, so styles apply first and scripts run
after the markup. A dot on a tab shows it has code; empty tabs print nothing.
Format, full screen, lint, autocomplete and the color picker act on the open
tab. Data saved by 1.1.0 (one code box + language) is converted into the
matching tab automatically.

PHP is never executed (no `eval()`); PHP tags would be sent to the browser
as text, and the editor warns about it.

## Editor features

Built on the CodeMirror 5.65 bundled with WordPress (`wp_enqueue_code_editor`),
so no second CodeMirror copy and no CDN are loaded. Assets load only on the
settings page and on post edit screens of supported post types, and never on
the front end.

| Feature | Notes |
|---|---|
| Syntax highlighting | HTML (mixed), CSS, JavaScript |
| Line numbers, active line, bracket and tag matching | |
| Auto-close brackets and HTML tags | |
| Code folding | gutter arrows, `Ctrl+Q` |
| Search / replace / jump to line | `Ctrl/Cmd+F`, `Shift+Ctrl+F` (`Cmd+Alt+F`), `Alt+G` |
| Toggle comment | `Ctrl/Cmd+/` |
| Autocomplete | opens while typing tags, attributes, CSS properties/values and JS keywords; `Ctrl+Space` forces it |
| Lint markers | HTMLHint (HTML), CSSLint (CSS), Espree (JS), summarized under each editor |
| Inline color picker | swatch before every color in CSS mode and `<style>` blocks (hex, `rgb()`, `hsl()`, named). Click it for a built-in picker: color square, hue and opacity sliders, text field, eyedropper (Chrome/Edge). Live update, keeps the original notation, Esc closes, one `Ctrl+Z` undoes a picker session |
| Format Code | button or `Shift+Alt+F`, via bundled js-beautify; undo with `Ctrl+Z` |
| Language warnings | PHP tags, tags inside CSS/JS mode, bare CSS/JS in HTML mode |
| Full screen | button or `F11`; `Esc` exits |
| Height | default in Settings; drag the bottom edge of any editor to resize |
| Themes | Dark (default) or Light |
| Accessibility | labelled editors, `aria-live` status, screen-reader announcements, `Esc` then `Tab` leaves the editor |
| Unsaved-changes warning | settings page and Classic editor; cleared after block-editor saves |

**Limitations, honestly stated:**

- Autocomplete is CodeMirror's hint addon (tag, attribute, property and keyword
  lists plus words from the current scope). It is not IntelliSense, type-aware
  completion or a language server.
- Linting catches common syntax mistakes. It does not prove a snippet is
  correct or safe.
- **Format Code only applies a result that differs from your code in
  whitespace alone.** Otherwise it cancels and leaves the code untouched. It
  refuses PHP, leaves `<pre>`, `<textarea>` and `<code>` contents alone, and
  never runs your code.
- If a user turns off "Syntax highlighting" in their profile, they get plain
  textareas. Formatting still works.

## Security model

Code is output **raw on purpose**. Escaping would break scripts. Instead,
access is restricted:

| Action | Required capability |
|---|---|
| Edit global code and settings | `manage_options` **and** `unfiltered_html` (meta-cap `ahfcm_manage_global`) |
| Edit a post's code | `edit_post` for that post **and** `unfiltered_html` (meta-cap `ahfcm_edit_post_code`) |

- Users without `unfiltered_html` (authors, contributors, and site admins on
  multisite) never see the meta box, and their submissions are ignored even
  with a valid nonce. `DISALLOW_UNFILTERED_HTML` disables editing for everyone.
- Every save is nonce-protected: the Settings API for global code, plus a
  per-post nonce in the meta box.
- Neither the option nor the post meta is exposed through the REST API.
- No `eval()`, no dynamic includes, and no direct SQL except one prepared
  read-only query in the migration tool.
- Only paste code from sources you trust. A snippet runs with full access to
  every visitor's session on the pages where it appears.

## Database storage

| Data | Where | Notes |
|---|---|---|
| Global code + settings | option `ahfcm_global_settings` | autoloaded (needed on every page view, so no extra query) |
| Last 5 global versions | option `ahfcm_global_history` | not autoloaded; restore from **Tools** |
| Plugin version | option `ahfcm_version` | drives upgrades |
| Per-post code | post meta `_ahfcm_settings` | protected key, one array per post; deleted when empty |

Per-post structure:

```php
[
  'version' => 1,
  'enabled' => true,          // master switch for this post
  'suppress_global' => false, // skip global code here
  'slots' => [
    'head'      => [ 'code' => '…', 'enabled' => true, 'language' => 'html' ],
    'footer'    => [ … ],
  ],
]
```

The meta is registered with `revisions_enabled`, so each post revision stores
the code it had. Restoring a revision restores the code too. The front end
reads it from the meta cache that the main query already primes, so it adds
no database queries.

No custom table is used. Two code fields per post and one option don't need one.

## Breakdance

- Breakdance's page shell calls `wp_head` and `wp_footer`, so both locations
  work on Breakdance-rendered pages (verified on Breakdance 3.0.0-rc.1).
- Code is not injected inside the builder preview by default, so analytics
  don't count your own design sessions (Settings → Page builders).

### Builder button (1.1.1)

Inside the Breakdance builder, a **code icon** sits in the top bar right after
the properties (pencil) button. It opens a **Header & Footer Code** panel with:

- **This page**: the open document's Header and Footer code, its on/off
  switch and "Skip global code" (same data as the WordPress edit screen's box).
- **Global**: site-wide code and the master switch (administrators only).

It has the same editors as wp-admin (autocomplete, lint, format, color picker,
full screen). Save with the **Save** button or `Ctrl/Cmd+S`; Esc or the ✕
closes it and warns about unsaved changes. Keystrokes inside the panel don't
trigger Breakdance shortcuts.

### Toolbar buttons (builder and block editor)

- **Breakdance builder**: code icon after the properties button; opens the
  Header & Footer Code panel.
- **Block editor**: code icon after “Edit in Breakdance” (or at the end of the
  toolbar); a dot means this page has code. It opens **the same panel as in
  the builder** (This page / Global tabs). The panel reads the page's current
  values from the **Header & Body Code Embed** box (including unsaved edits)
  and, after Save, writes them back into that box — so a later Update posts
  the same values and can never overwrite the panel's changes.
  In the block editor the meta box itself is not added (so the “Meta Boxes”
  area disappears when nothing else uses it); the panel saves directly. The
  box is still used in the Classic editor, and in the block editor when the
  editor icon is switched off or hidden for that user. Filter:
  `ahfcm_panel_replaces_meta_box( bool $replace, WP_Post $post )`.

Settings → Header & Footer Code → Settings → **Toolbar buttons**: a switch per
button, plus shared **Show for these roles** and **Hide for these users**
conditions. Hiding only affects the buttons — code keeps running and access
in wp-admin follows capabilities. Filter: `ahfcm_breakdance_button_visible(
bool $visible, WP_User $user, string $where )` (`builder` or `editor`).

### Templates, headers, footers, popups and global blocks

Breakdance's own documents can carry code too (Settings → **Breakdance
templates**; all on by default). Open one in the builder and the first tab
becomes **This template / header / footer / popup / global block**. Its code
runs on **every page that uses it**:

| Type | Runs on |
|---|---|
| Template | pages rendered with it (incl. nested templates) |
| Header / Footer | pages where Breakdance shows it |
| Popup | pages where its display rules match, **and** pages that open it (button/link/interaction/form “open popup” action) — even if its display rules are disabled |
| Global block | pages that embed it via the Global Block element, a loop or a menu custom area (nested blocks too) |

Output order in each location: global → header → template(s) → footer →
popups → global blocks → the page itself. Each document's own switches
(enabled, per-slot, "Skip global code") work as on pages.

Detection uses the same functions Breakdance's admin bar uses
(`ThemelessController`, `_getTemplateForRequest`) and reads global-block
references from the page's Breakdance data before `<head>` is printed; footer
output also includes blocks Breakdance actually rendered (shortcodes, dynamic
content). If a future Breakdance version changes these internals, this part
returns nothing and global/per-page code keep working. Override with the
`ahfcm_breakdance_active_documents` and `ahfcm_breakdance_global_blocks` filters.

How it works: assets are printed on Breakdance's builder footer action
(`unofficial_i_am_kevin_geary_master_of_all_things_css_and_html`, the same
hook WindPress uses). The button is mounted next to
`.open-properties-toolbar-button` and re-mounted if the toolbar re-renders.
Data is saved through private REST routes (`ahfcm/v1/post/{id}`,
`ahfcm/v1/global`) using the same capability checks as wp-admin. Disable the
integration with `add_filter( 'ahfcm_breakdance_integration', '__return_false' );`.

## Migration from "Page Header & Body Code Embed" v3.0 (WPCodeBox snippet #7)

How the old snippet worked (from its source):

| Old field | Meta key | Actually printed in |
|---|---|---|
| Header Code | `_page_header_code` | `wp_head`, priority 999 |
| Body Code ("after `<body>`") | `_page_body_code` | **`wp_footer`**, priority 10 — not after `<body>` |

It also ran `stripslashes()` on output, which deletes backslashes (for
example, `/\d+/` became `/d+/`), and had no global code.

**Import (Settings → Header & Footer Code → Tools):**

1. Click **Dry run**. You get a report of what would be imported, with nothing changed.
2. Click **Run import** with "Enable imported code" **unchecked**. The code is
   copied but switched off, so pages look exactly the same while the old
   snippet keeps running.
3. In WPCodeBox, switch off snippet #7 (`header_footer_code`).
4. Run the import again with **"Enable imported code immediately" checked**.
   This enables the slots imported in step 2.
5. Check a few pages in View Source (look for `<!-- AHFCM`).

The importer never edits or deletes the old meta. It only fills empty slots,
so re-running it never duplicates code; a slot that already holds different
code is reported as a conflict. It verifies each write and reports
Imported / Already imported / Skipped / Failed. Rows containing backslashes
are flagged, because the new plugin outputs them exactly as stored.

On this site, the dry run found 1 page (ID 81) with the old keys, both empty,
so there was nothing to import.

## Rollback

The old snippet and its data are untouched, so rollback is just switching:

1. Turn WPCodeBox snippet #7 back on (if you turned it off).
2. Deactivate **Advanced Header & Footer Code Manager**. Its data stays, so
   reactivating later restores everything.
3. If needed, restore the database snapshot taken before installation
   (`Local Sites/breakdance/pre-ahfcm-backup.sql` on the development machine).

## Uninstall

- **Deactivate**: nothing is deleted.
- **Delete** (Plugins → Delete): data is kept **unless** "Permanently delete
  all global and per-post code when the plugin is deleted" is checked in
  Settings. In that case `uninstall.php` removes the options and every
  `_ahfcm_settings` meta row, on every site in a multisite network. Legacy meta
  is never touched.

## Multisite

Network activation sets up every site, plus sites created later. Each site has
its own settings. Only super admins have `unfiltered_html` on multisite, so
only they can edit code. That is intentional.

## Hooks for developers

| Hook | Type | Purpose |
|---|---|---|
| `ahfcm_should_inject( bool $inject )` | filter | Allow or block output for the current request |
| `ahfcm_snippet_output( string $code, string $slot, string $source, int $post_id )` | filter | Change or drop one snippet (`$source` is `global` or `post`) |
| `ahfcm_post_types( string[] $types )` | filter | Post types with per-post code |
| `ahfcm_builder_query_params( string[] $params )` | filter | Query args that mark builder requests |
| `ahfcm_breakdance_integration( bool $enabled )` | filter | Turn the Breakdance builder button off |
| `ahfcm_breakdance_active_documents( int[] $ids )` | filter | Breakdance templates/header/footer/popups applied to the request |
| `ahfcm_breakdance_global_blocks( int[] $ids, int[] $docs, string $slot )` | filter | Global blocks applied to the request |
| `ahfcm_breakdance_referenced_popups( int[] $ids, int[] $docs, string $slot )` | filter | Popups opened by the request's documents |

## Testing

**Integration suite** (WP-CLI, creates and removes temporary content; run on
local/staging only):

```bash
wp eval-file wp-content/plugins/advanced-header-footer-code-manager/tests/verify.php
```

70 checks: placement of both locations in real HTTP responses, page/post/CPT
scoping, global + page combination and order, every enable/disable switch,
byte-exact storage and output of tricky code (backslashes, quotes, `<\/script>`,
JSON-LD, Unicode, CRLF), duplicate-output protection, feed/REST/AJAX/builder
exclusion, REST non-exposure, malformed data without PHP warnings, the
removal of the old body-start slot, capability checks (author, editor, subscriber, admin),
invalid nonces, revisions, the full migration flow, and deactivate/reactivate
persistence.

**JavaScript unit tests** (Node 18+):

```bash
node --test tests/js/core.test.js
```

32 tests: formatting real-world snippets (gtag, GTM noscript, JSON-LD, mixed
HTML/CSS/JS, `<pre>`, CSS, JS) changes whitespace only and is idempotent; PHP is
refused; a corrupting formatter is rejected; formatter errors leave code
untouched; a 300 KB snippet; language detection and diagnostics; color token detection, parsing and notation-preserving output.

**Manual browser checklist** (needs a logged-in admin):

1. Settings page: both editors initialize with highlighting and line
   numbers; tabs switch; the console has no errors.
2. Type `<di` → tag suggestions; in CSS mode type `col` → property suggestions.
3. Introduce an error (`<div` unclosed, `a{color:}`) → red gutter marker and
   status text.
4. Format Code on minified HTML → formatted; `Ctrl+Z` reverts it.
5. Switch language → the badge changes and the code is kept.
6. Change code, try to leave → unsaved-changes warning.
7. Save → reload → code persists.
8. Classic editor and block editor: the meta box editors render; save the post;
   reload; code persists; View Source shows it on that page only.

## Troubleshooting

- **Code doesn't appear**: check the master switch, the slot's Enabled switch,
  and (for page code) the page's "Enable code" box. Purge any page cache. Look
  for `<!-- AHFCM` in View Source. If you're viewing inside a page builder,
  that's excluded by default.
- **No meta box**: your role lacks `unfiltered_html`, the post type is
  unchecked in Settings, or it's hidden under "Screen Options" / the block
  editor's Preferences → Panels.
- **Plain textarea instead of an editor**: turn on "Syntax highlighting" in
  your user profile.
- **Code printed twice**: the same code is active both here and in the legacy
  WPCodeBox snippet. Turn one off.
