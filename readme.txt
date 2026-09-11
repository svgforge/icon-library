=== Icon Library ===
Contributors: svgforge
Tags: svg, icons, sprite, gutenberg
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SVG Fragment Block: inserts icons from your own SVG sprite file (ico.svg) via <use> and can link them. For users who maintain the sprite file themselves with CLI tools such as svgforge and want full control over the rendered SVG.

== Description ==

The Icon Library Block loads a central SVG sprite file (by default the bundled `sprite.svg`), shows all contained `<symbol>` elements in a convenient picker and inserts the selected icon as `<svg><use href="/wp-content/plugins/icon-library/sprite.svg#symbol-id">` into your content.

= Who is this plugin for? =

This plugin is primarily aimed at advanced theme and plugin developers. It does not give you a code-free icon manager: every icon must first exist as a `<symbol>` in a sprite file that you own, build and version.

Since WordPress 7.1, core ships its own icon system (`wp_register_icon_collection()`, `wp_register_icon()`, `wp_get_icon()`): icons then automatically appear in the native Icon block's picker and in the REST API, and can be rendered directly in PHP. If you only need a few icons and can register them in code, use core instead — this plugin is then unnecessary.

The fragment approach has advantages when you run a real sprite pipeline:

* Full SVG via `<use>`: stroke-based icons, gradients, `currentColor`, inline styles and custom `viewBox` values survive. WordPress 7.1's sanitizer only allows `<svg>`, `<path>` and `<polygon>` (no `stroke`, no inline styles) and therefore breaks many stroke-based icon sets.
* Reuse existing sprites: upload a sprite (or generate one with a CLI tool like svgforge) — no per-icon PHP code required.
* One file: the sprite is a single cacheable file that lives in the theme repo and is versioned with Git.
* Control per block: fill and stroke colours, width/height with units, links with `rel` handling and aria-labels — per icon instance, without touching a stylesheet.
* Also runs on WordPress versions before 7.1 (from 6.6).

Limitations you should know about:

* To add or change icons you have to rebuild the sprite file — typically with a CLI tool such as svgforge. There is no in-browser icon editor or management UI.
* The icons live in your content as a block. There is no replacement for the simple `wp_get_icon()` helper in theme PHP — that is what the native 7.1 approach is for.

= Features =

* Settings page (Settings → Icon Library) to upload the SVG sprite file, including sanitization of scripts and event handlers.
* Theme file via `ICON_LIBRARY_SPRITE_FILE` as a versionable source – takes priority as long as the file exists; otherwise the backend upload is used.
* Symbol picker in the editor with a live preview of all icons from the sprite.
* Icons can be linked (new tab + rel attributes including noopener/noreferrer).
* Aria-label for screen readers; linked icons are automatically labelled via the link.
* Fill and stroke colour as well as width/height per block (px, em, rem, %).
* Fully dynamic server-side rendering (render.php) with `get_block_wrapper_attributes()`.
* Block supports: alignment, anchor, additional CSS classes.

= Configure the sprite file =

The SVG sprite file is resolved in this order (first existing source wins):

1. Theme file `ICON_LIBRARY_SPRITE_FILE` – when set and the file exists (has priority).
2. Uploaded file from Settings → Icon Library (backend upload).
3. Constant `ICON_LIBRARY_SPRITE_URL`.
4. Filter `icon_library_sprite_url`.
5. Fallback: `sprite.svg` in the plugin directory.

Theme file (recommended, versionable with the theme) in the theme's `functions.php`:

    define( 'ICON_LIBRARY_SPRITE_FILE', get_stylesheet_directory() . '/assets/ico.svg' );

The file must be readable and lie within `WP_CONTENT_DIR` or `ABSPATH`. If it does not exist, the next source is used automatically.

Further overrides:

    define( 'ICON_LIBRARY_SPRITE_URL', 'https://cdn.example.com/icons/ico.svg' );

or

    add_filter( 'icon_library_sprite_url', function () {
        return '/wp-content/themes/my-theme/assets/ico.svg';
    } );

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New).
2. Activate the plugin under "Plugins".
3. Upload the sprite file (`ico.svg` with `<symbol id="...">` elements) via **Settings → Icon Library** or place it as a theme file (`ICON_LIBRARY_SPRITE_FILE`) or at the configured URL.
4. In the editor, add the "SVG Fragment" block and choose an icon.

== Frequently Asked Questions ==

= Where do the icons come from? =

From the central sprite file `ico.svg`. Each icon is a `<symbol id="my-icon" viewBox="0 0 24 24">…</symbol>` element. The file is rendered server-side and loaded via `fetch` in the editor.

= Why not simply use the native SVG icons of WordPress 7.1? =

WordPress 7.1 offers a native icon system with `wp_register_icon_collection()` / `wp_register_icon()` / `wp_get_icon()` — that is enough if you register a few icons directly in code. This plugin complements that where a central SVG sprite is used: full SVG freedom (including stroke icons), existing sprites without per-icon PHP code, a single cacheable file and per-instance block styling. Integration with the native 7.1 approach is planned so that the same sprite can also feed the native Icon block in the future (see Changelog).

= Does it work without JS in the frontend? =

Yes. The frontend markup is generated server-side in `render.php`; the built JS is only needed in the editor.

== Screenshots ==

1. Symbol picker in the Gutenberg editor with a live preview of all icons from the sprite file.

== Changelog ==

= 0.1.0 =
* Initial release.