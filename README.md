<p align="center"><img src="assets/icon-128x128.png" width="96" height="96" alt="Advanced Header &amp; Footer Code Manager icon"></p>

# Advanced Header & Footer Code Manager

Add trusted **HTML, CSS and JavaScript** to `<head>` and before `</body>`, either site-wide,
per page, or per **Breakdance / Oxygen** template, header, footer, popup or global block.
You edit the code in CodeMirror, with autocomplete, linting, formatting and a color picker,
from the **block editor**, the **Breakdance / Oxygen builder**, or the settings app.

- Version 2.3.1, requires WordPress 6.4+ and PHP 7.4+ (developed on WP 7.1, PHP 8.2)
- Integrations: Gutenberg (block editor), Breakdance 2+, Oxygen 6 (Oxygen Classic 4.x is not supported)
- License: GPL-2.0-or-later. It bundles js-beautify 1.15.4 (MIT).

---

## Where code goes

| Slot | Hook | Default priority | Lands |
|---|---|---|---|
| **Header Code** | `wp_head` | 1000001 | end of `<head>`, after theme and builder CSS |
| **Footer Code** | `wp_footer` | 1000001 | end of `<body>`, after footer scripts and builder JS |

Breakdance and Oxygen print their CSS and JS at priority 1000000. The default 1000001
puts your code after them, so equally specific CSS rules win and your scripts can use
libraries that have already loaded.

### Position

Every code card (global, page, template, header, footer, popup, global block) has a
**Position** setting:

| Position | Prints |
|---|---|
| **Same as global code** (pages and builder items only, the default) | wherever the global code of that slot prints (set under **Settings → Header & Footer Code → Output**, or on the Global code cards) |
| **Just before `</head>`** / **Just before `</body>`** | last in `wp_head` / `wp_footer` (priority `PHP_INT_MAX`), after every theme, plugin and builder tag |
| **Custom priority** | at the `wp_head` / `wp_footer` priority you enter (−99999999 to 99999999; lower runs earlier) |

"Just before" relies on the theme or builder calling `wp_head()` / `wp_footer()` right
before the closing tag, as WordPress requires and Breakdance and Oxygen do. The page is
not output-buffered: Breakdance renders inside its own buffer, and a nested one would
break it. Snippets with the same priority keep the order global → header → templates →
footer → popups → global blocks → page.

Each slot has **HTML · CSS · JS** tabs, and all three are used together. A slot prints
its CSS first (inside `<style>`), then the HTML exactly as written, then the JS (inside
`<script>`).

On a page, code is output in this order:

```
global → builder header → template(s) → footer → popups → global blocks → the page itself
```

A page or template can **skip global code**. Every slot, page and template also has its
own on/off switch, so you can pause code without deleting it.

## Editing code

| Where | How |
|---|---|
| Site-wide | **Settings → Header & Footer Code → Global code** |
| Block editor | **</>** icon in the top toolbar (next to "Edit in Breakdance/Oxygen"). It opens the code panel. |
| Breakdance / Oxygen builder | Code button after the properties button in the top bar. It opens the same panel. |
| Classic editor | **Header & Body Code Embed** box |

The panel has two tabs: **This page** (or *This template*, *This header* …) and **Global**.
Press **Save** or **Ctrl+S** to save, and **Esc** to close.

## Builder documents (Breakdance & Oxygen)

These document types can have their own code, which runs on **every page that uses
them**:

| Type | Runs on |
|---|---|
| Template | pages rendered with it, including nested templates |
| Header / Footer | pages where the builder shows it |
| Popup | pages where its display rules match, **and** pages that open it (button/link/interaction/form), even when its rules are disabled |
| Global block | pages that embed it through the Global Block element, a loop or a menu custom area, including blocks nested in blocks |

Detection uses the builder engine's own functions (`ThemelessController`,
`_getTemplateForRequest`, `Breakdance\Data\get_tree`). Oxygen 6 runs on the same engine,
so both builders share one integration; only the builder URL (`?oxygen=builder`), the
post type names (`oxygen_*`) and the data meta key (`_oxygen_data`) differ, and all of
those come from the engine itself. If a future builder update changes these internals,
template code stops printing, but global and per-page code keep working.

## Settings app

**Settings → Header & Footer Code** opens a full-width app with a sidebar:

| Section | Contents |
|---|---|
| **Global code** | site-wide Header/Footer code and its master switch |
| **Integrations** | Block editor, Breakdance and Oxygen cards (detected/not installed, on/off), toolbar icon, builder button, which template types get code, whether code runs in the builder preview, and who sees the toolbar buttons (roles, hidden users) |
| **Output** | which content types get per-page code, hook priorities, HTML comment markers |
| **Editor** | theme (dark/light), editor height, delete data on uninstall |
| **Tools** | import from the old "Page Header & Body Code Embed" snippet, global code history (restore the last 5 versions) |
| **Help** | how placement works, keyboard shortcuts |

## Security

Code is output **raw on purpose**, because escaping it would break scripts. Instead,
access is restricted:

| Action | Requires |
|---|---|
| Edit global code and settings | `manage_options` + `unfiltered_html` (meta-cap `ahfcm_manage_global`) |
| Edit a post's code | `edit_post` for that post + `unfiltered_html` (meta-cap `ahfcm_edit_post_code`) |

- Every save is nonce-protected: the Settings API, a per-post meta box nonce, or the REST nonce.
- The private REST routes `ahfcm/v1/post/{id}` and `ahfcm/v1/global` use the same checks.
- Forms and panels opened before a plugin update refuse to save, instead of overwriting code.
- No `eval()`, and PHP is never executed.

## File structure

The plugin follows WindPress's structure: namespaced PSR-4 classes under `src/`, with
one folder per integration. A built-in autoloader means no Composer install is needed,
and there's no build step.

```
advanced-header-footer-code-manager/
├── advanced-header-footer-code-manager.php   bootstrap (header, autoloader, hooks)
├── constant.php                              AHFCM_VERSION, paths
├── uninstall.php
├── src/
│   ├── Plugin.php                            wires everything
│   ├── Autoloader.php                        AHFCM\ → src/
│   ├── Core/
│   │   ├── Settings.php                      option ahfcm_global_settings
│   │   ├── PostMeta.php                      meta _ahfcm_settings
│   │   ├── Injector.php                      front-end output
│   │   ├── Security.php                      capabilities
│   │   ├── Visibility.php                    who sees the toolbar buttons
│   │   └── Upgrade.php                       activation, upgrades, multisite
│   ├── Api/Rest.php                          ahfcm/v1 routes for the panel
│   ├── Admin/
│   │   ├── AdminPage.php                     settings app + tool actions
│   │   ├── MetaBox.php                       Classic editor box
│   │   └── LegacyImport.php                  importer for the old snippet
│   ├── Editor/
│   │   ├── Assets.php                        CodeMirror + formatter + config
│   │   ├── Fields.php                        HTML/CSS/JS slot cards
│   │   └── Panel.php                         code panel (block editor & builders)
│   └── Integration/
│       ├── IntegrationInterface.php
│       ├── Loader.php                        registry
│       ├── Gutenberg/{Main,Editor}.php
│       ├── Breakdance/{Main,Editor}.php      shared Breakdance-engine logic
│       └── Oxygen/Main.php                   Oxygen 6 (extends Breakdance)
├── views/
│   ├── admin/app.php + sections/*.php        settings app
│   ├── admin/meta-box.php
│   └── editor/{slot,panel}.php
├── assets/
│   ├── css/{admin,editor,panel}.css
│   ├── js/{core,editor,panel}.js
│   ├── js/integrations/gutenberg.js
│   ├── images/{ahfcm-icon,ahfcm-icon-mono,ahfcm-glyph}.svg, images/integrations/
│   ├── icon-{128x128,256x256,512x512}.png
│   └── vendor/js-beautify/
├── languages/
└── tests/{verify.php, js/core.test.js}
```

### Adding an integration

Implement `AHFCM\Integration\IntegrationInterface` (name, label, `is_active()`,
`register()`, template types, documents for the request) and add it with the
`ahfcm_integrations` filter.

## Brand

| Token | Light | Dark (code panel) | Use |
|---|---|---|---|
| `--ahfcm-brand-primary` | `#5B35E8` | `#A78BFA` | primary buttons, active tab, links, switches |
| `--ahfcm-brand-hover` | `#4827CC` | `#C4B5FD` | hover and active states |
| `--ahfcm-brand-deep` | `#3820A8` | `#C4B5FD` | emphasis |
| `--ahfcm-brand-tint` | `#F0EBFF` | `#30264D` | selected backgrounds |
| `--ahfcm-bg-surface` / `--ahfcm-bg-subtle` | `#FFFFFF` / `#F9FAFC` | `#191B24` / `#20232E` | surfaces |
| `--ahfcm-text-primary` / `--ahfcm-text-secondary` | `#171923` / `#5D6472` | `#F5F6FA` / `#B4B9C8` | text |
| `--ahfcm-border` / `--ahfcm-border-strong` | `#E2E5EC` / `#CBD0DC` | `#343847` / `#4A5062` | borders |

All tokens are defined once in `assets/css/editor.css` (scoped to `.ahfcm-app`); the
code panel swaps in the dark set in `assets/css/panel.css`. Icons live in
`assets/images/` (`ahfcm-icon.svg` full colour, `ahfcm-icon-mono.svg` one colour,
`ahfcm-glyph.svg` 24px toolbar glyph) and `assets/icon-{128,256,512}.png`.

## Storage

| Data | Where |
|---|---|
| Global code + settings | option `ahfcm_global_settings` (autoloaded, schema version 4) |
| Last 5 global versions | option `ahfcm_global_history` |
| Plugin version | option `ahfcm_version` |
| Per-post / per-template code | post meta `_ahfcm_settings` (protected; stored with revisions) |

2.3.0 adds `placement` (and a per-page `priority`) to each code slot (schema 4). Existing
code keeps printing where it did: global code keeps its priority and page code follows
the global position. Data from 1.x and 2.x carries over, and older formats are still
converted when read.

## Hooks

| Hook | Purpose |
|---|---|
| `ahfcm_integrations` | add or replace integrations |
| `ahfcm_should_inject( bool )` | allow or block output for the current request |
| `ahfcm_snippet_output( string $code, string $slot, string $source, int $post_id )` | change or drop one snippet |
| `ahfcm_post_types( string[] )` | post types with per-page code |
| `ahfcm_builder_query_params( string[] )` | query args that mark builder previews |
| `ahfcm_breakdance_active_documents( int[] )` | header/template/footer/popups for the request |
| `ahfcm_breakdance_global_blocks( int[], int[], string )` | global blocks for the request |
| `ahfcm_breakdance_referenced_popups( int[], int[], string )` | popups opened by the request's documents |
| `ahfcm_breakdance_integration( bool, string $builder )` | turn the builder button off |
| `ahfcm_breakdance_button_visible( bool, WP_User, string $where )` | toolbar button visibility |
| `ahfcm_panel_replaces_meta_box( bool, WP_Post )` | keep the meta box in the block editor too |

## Testing

```bash
wp eval-file wp-content/plugins/advanced-header-footer-code-manager/tests/verify.php
```

The suite runs 100+ checks against a live local or staging site. It creates temporary
content and removes it afterwards. It covers placement, the order of parts, scoping,
every switch, byte-exact output, exclusions (feeds, REST, AJAX, builder preview),
permissions, nonces, revisions, the stale-page guard, builder templates, global blocks,
popups, toolbar visibility, the meta box versus the panel, the legacy import and
deactivate/reactivate. Builder checks adapt to whichever builder is running
(Breakdance or Oxygen).

```bash
node --test tests/js/core.test.js
```

## Updates

The plugin is distributed through GitHub releases, not WordPress.org. The header
`Update URI: https://github.com/navedfakru/advanced-header-footer-code-manager` makes
WordPress ask `AHFCM\Core\Updater` (filter `update_plugins_github.com`) instead of
WordPress.org. It reads the latest release from the GitHub API (cached 12 hours; "Check
again" on Dashboard → Updates refreshes it) and offers the attached
`advanced-header-footer-code-manager-X.Y.Z.zip` as the update package.

To publish a version:

1. Bump the version in the plugin header, `constant.php` and readme.txt.
2. Build the zip. Its root folder must be `advanced-header-footer-code-manager/`
   (GitHub's automatic source zips are not used, because they are named after the tag).
3. Create a GitHub release with tag `vX.Y.Z` and attach the zip. Drafts and
   pre-releases are ignored.

## Uninstall

Deactivating never deletes anything. Deleting the plugin removes its data **only** if
**Editor → Delete data when the plugin is deleted** is on.

## Rollback

Version 1.1.1 is tagged in git history (commit `c53a23a`). Stored data is compatible in
both directions between 1.1.1 and 2.3.0; versions before 2.3.0 ignore the Position
setting and print everything at the global priority.
