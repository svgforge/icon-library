=== Icon Library ===
Authors: wpdynamics
Contributors: wpdynamics
Tags: svg, icons, sprite, gutenberg
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SVG Icon Block: inserts icons from your own SVG sprite via <use>, can link them. For developers who maintain their sprite file with CLI tools.

== Description ==

The Icon Library Block loads a central SVG sprite file (by default the bundled `sprite.svg`), shows all contained `<symbol>` elements in a convenient picker and inserts the selected icon as `<svg><use href="/wp-content/plugins/icon-library/sprite.svg#symbol-id">` into your content.

The SVG fragment is rendered on the server, so visitors receive fast, static HTML with no extra requests and no JavaScript on the frontend. Since you own the sprite file, every icon stays small, cacheable and fully under your control — exactly the way a hand-built icon set should be maintained.

= Who is this plugin for? =

This plugin is primarily aimed at advanced theme and plugin developers. It does not give you a code-free icon manager: every icon must first exist as a `<symbol>` in a sprite file that you own, build and version.

Since WordPress 7.1, core ships its own icon system (`wp_register_icon_collection()`, `wp_register_icon()`, `wp_get_icon()`): icons then automatically appear in the native Icon block's picker and in the REST API, and can be rendered directly in PHP. If you only need a few icons and can register them in code, use core instead — this plugin is then unnecessary.

The fragment approach has advantages when you run a real sprite pipeline:

* Full SVG via `<use>`: stroke-based icons, gradients, `currentColor`, inline styles and custom `viewBox` values survive. WordPress 7.1's sanitizer only allows `<svg>`, `<path>` and `<polygon>` (no `stroke`, no inline styles) and therefore breaks many stroke-based icon sets.
* Reuse existing sprites: generate a sprite with svgforge-cli (sanitization + svgo optimization) and upload it — no per-icon PHP code required.
* One file: the sprite is a single cacheable file that lives in the theme repo and is versioned with Git.
* Control per block: fill and stroke colours, size (standard Dimensions panel with preset slider + custom input), links with `rel` handling and aria-labels — per icon instance, without touching a stylesheet.
* Also runs on WordPress versions before 7.1 (from 6.6).

Limitations you should know about:

* To add or change icons you have to rebuild the sprite file — typically with svgforge-cli, which handles sanitization and svgo optimization. There is no in-browser icon editor or management UI.
* The icons live in your content as a block. There is no replacement for the simple `wp_get_icon()` helper in theme PHP — that is what the native 7.1 approach is for.

= Features =

* Settings page (Settings → Icon Library) to upload the SVG sprite file — svgforge-cli handles full sanitization and svgo optimization, the plugin strips scripts, event handlers and `javascript:` links as a safety net.
* Theme override via the `icon_library_sprite_url` filter (e.g. `get_theme_file_uri()`) – the filter has priority over everything.
* Symbol picker in the editor with a live preview of all icons from the sprite; icons from sprite subdirectories (IDs like `directory--filename`) are grouped and selectable via a filter in the dropdown.
* Icons can be linked (new tab + rel attributes including noopener/noreferrer).
* Aria-label for screen readers; linked icons are automatically labelled via the link.
* Fill and stroke colour as well as size per block (square, preset slider + custom input with units).
* Fully dynamic server-side rendering (render.php) with `get_block_wrapper_attributes()`.
* Block supports: alignment, anchor, additional CSS classes.

= Configure the sprite file =

The SVG sprite file is resolved in this order (first existing source wins):

1. Filter `icon_library_sprite_url` – the canonical override (has priority).
2. Uploaded file from Settings → Icon Library (backend upload).
3. Fallback: `sprite.svg` in the plugin directory.

Override with a filter (recommended, versionable with the theme) in the theme's `functions.php`:

    add_filter( 'icon_library_sprite_url', fn () => get_theme_file_uri( 'assets/ico.svg' ) );

or point it at a CDN:

    add_filter( 'icon_library_sprite_url', fn () => 'https://cdn.example.com/icons/ico.svg' );

Icon Library is a developer-focused Gutenberg block that arranges a curated set of SVG icons as one central sprite and reuses them everywhere in your content. It works with any symbol sprite produced by modern build tools, is fully translated, and gives you precise control over colours, size, links and accessibility on every single block instance. The plugin prefers simplicity and performance: no tracking, no external requests, no page-weight overhead, and no vendor lock-in to a particular icon pack or service.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New).
2. Activate the plugin under "Plugins".
3. Upload the sprite file (`ico.svg` with `<symbol id="...">` elements) via **Settings → Icon Library** or reference your own file with the `icon_library_sprite_url` filter.
4. In the editor, add the "SVG Icon" block and choose an icon.

== Frequently Asked Questions ==

= Where do the icons come from? =

From the central sprite file `ico.svg`. Each icon is a `<symbol id="my-icon" viewBox="0 0 24 24">…</symbol>` element. The file is rendered server-side and loaded via `fetch` in the editor.

= Why not simply use the native SVG icons of WordPress 7.1? =

WordPress 7.1 offers a native icon system with `wp_register_icon_collection()` / `wp_register_icon()` / `wp_get_icon()` — that is enough if you register a few icons directly in code. This plugin complements that where a central SVG sprite is used: full SVG freedom (including stroke icons), existing sprites without per-icon PHP code, a single cacheable file and per-instance block styling. Since 0.2 the plugin can also register every symbol of that sprite as an `icon-library` collection (experimental), so the same sprite feeds the native Icon block and `wp_get_icon()` too — see the "WordPress native icon integration" setting.

= Does it work without JS in the frontend? =

Yes. The frontend markup is generated server-side in `render.php`; the built JS is only needed in the editor.

= How do I disable the color options for the block? =

Put the block's color settings into your theme's `theme.json`:

[sourcecode]
{
    "version": 3,
    "settings": {
        "blocks": {
            "icon-library/svg-icon": {
                "color": { "custom": false, "palette": [] }
            }
        }
    }
}
[/sourcecode]

That removes the standard Gutenberg Color/Background panels for the SVG Icon block. As in the core Icon block, multi-color icons (e.g. Tango icon sets) keep their baked-in colors and are not affected by the color controls; monochrome icons follow the chosen color.

= How do I offer preset sizes like font sizes? =

The size is the standard Gutenberg Dimensions panel (`supports.dimensions.width`, like the core Icon block): the block is square, and size presets are standard theme.json `dimensionSizes` per block:

[sourcecode]
{
    "version": 3,
    "settings": {
        "blocks": {
            "icon-library/svg-icon": {
                "dimensions": {
                    "dimensionSizes": [
                        { "name": "S", "slug": "s", "size": "32px" },
                        { "name": "L", "slug": "l", "size": "64px" }
                    ],
                    "width": true
                }
            }
        }
    }
}
[/sourcecode]

With presets the panel shows a slider that moves across the preset sizes, like the core Icon block. A toggle next to it switches to a custom value input with a slider, using the allowed units (`spacing.units`). `dimensions.width: false` disables sizing entirely, so the block always renders at its default size.

== Screenshots ==

1. Symbol picker in the Gutenberg editor with a live preview of all icons from the sprite file.

== Changelog ==

= 0.3.0 =
* Short URL `/i.svg` for the sprite, opt-in via the `icon_library_short_url` filter.
* Stricter upload sanitization (allowlist via enshrined/svg-sanitize instead of regex); automated dev releases from `main`; the plugin ZIP ships its vendored dependencies.
* Type declarations across the source (PHP 8.3+); padding applies to the SVG element, not the wrapper.
* New `docs/api.md` API reference; settings page shows the short URL under "Active sprite file".

= 0.2.1 =
* wp.org compliance: escaped SVG output with an input allowlist, direct-access guard in the render template, readme and changelog cleanup.
* Remove the now-discouraged `load_plugin_textdomain()` call; WordPress loads translations for the plugin slug automatically.

= 0.2.0 =
* WordPress 7.1 native icon integration (experimental): symbols of the configured sprite are registered as an `icon-library` icon collection (setting "WordPress native icon integration", default Off).
* New setting mode "Off + disable core Icon block" deregisters the built-in Icon block in the editor (including in content) and its frontend rendering.
* Registration is lazy: it only runs when needed (core Icon block or REST), keeping page-load cost independent of the icon count.

= 0.1.0 =
* Initial release.
