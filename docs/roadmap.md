# Roadmap: WordPress 7.1 icon API integration

## Status

Planned (post 0.1.0). No code exists yet — this document records the design decisions so the work is scoped before implementation.

## Background

WordPress 7.1 ships a public SVG icon API:

- `wp_register_icon_collection()` / `wp_unregister_icon_collection()`
- `wp_register_icon()` / `wp_unregister_icon()`
- `wp_get_icon()` (server-side rendering)
- REST endpoints `GET /wp/v2/icon-collections` and `GET /wp/v2/icons`
- The core Icon block picker groups icons by collection and searches across them.

Registered icons appear in the core Icon block, over REST, and via `wp_get_icon()` — all media actually stored/used from one place.

## Goal

On sites running WordPress >= 7.1, register every `<symbol>` from the configured sprite as an `icon-library/<id>` icon, so the same sprite also powers the native core Icon block and `wp_get_icon()`. The SVG Fragment block stays the primary, full-fidelity experience; the native registration is a companion, not a replacement.

## Constraints and limitations of the native path

Core sanitizes every registered icon against a conservative allowlist:

- Only `<svg>`, `<path>` and `<polygon>` elements survive.
- Attributes are limited to a fixed set; `stroke` is not allowed at all and `fill` is kept only on shapes, not on the outer `<svg>`.
- Inline styles, scripts and event handlers are stripped.

Consequences for sprite symbols:

- Stroke-based icons (e.g. Feather/Lucide/heroicons outline) degrade to their fill shapes when consumed through the native path.
- Icons relying on gradients/`defs` or inline styles break.
- `wp_get_icon()` output does not carry `currentColor` by default.

These are core limitations (see Gutenberg PR #75550, which may broaden the allowlist in the future). The integration must therefore degrade gracefully: only symbols that survive sanitization should be registered, and the fragment block remains the recommended way for stroke-based sets.

## Design

### Detection

Feature-detect the API (WP >= 7.1) via `function_exists( 'wp_register_icon_collection' )` on `init`, alongside `icon_library_register_block()`.

### Registration source and caching

The plugin already reads and counts the sprite on upload (`icon_library_handle_sprite_upload`). Symbols should be extracted server-side once and cached (option key or a generated `icons/*.svg` file set), instead of parsing the sprite on every request:

- Option A: register each symbol with `content` (symbol inner markup wrapped in a sanitizer-friendly `<svg>`) from a cached extractor.
- Option B: extract each symbol into its own `.svg` file next to the upload and register via `file_path` (core reads lazily and sanitizes on first use).

Option A keeps everything in options (no extra files, survives uploads dir moves); Option B reuses core's lazy I/O and keeps registration cheap. Decide during implementation; A has fewer moving parts.

### Registration

```php
if ( function_exists( 'wp_register_icon_collection' ) ) {
    wp_register_icon_collection( 'icon-library', array(
        'label'       => __( 'Icon Library', 'icon-library' ),
        'description' => __( 'Icons from the configured SVG sprite.', 'icon-library' ),
    ) );

    foreach ( $symbols as $id => $content ) {
        wp_register_icon( 'icon-library/' . $id, array(
            'label'   => $id,
            'content' => $content,
        ) );
    }
}
```

Run on `init` at a priority after the collection registration. Skip symbols whose sanitized content is empty (guards against the stroke/allowlist problem). Labels for now use the symbol id; a keyword/alias mapping can follow when core supports keywords.

REST-visible collections have no extra capability beyond `edit_posts`, so nothing site-facing leaks.

### Interaction with the settings page

- New upload/deletion of the sprite invalidates and rebuilds the cached symbol set.
- If the native API works on the site, the settings page should mention that the icons are also available in the built-in Icon block.
- Deletion removes the collection's cached icons as well.

## Communication

The README and `readme.txt` already position the plugin honestly:

- The plugin is for advanced theme/plugin developers who maintain their own sprite (bold statement at the top of the README).
- WordPress 7.1's native icon API is described and pointed to as the simpler path for "a few icons registered in code".
- Fragment advantages (full SVG via `<use>`, reuse of existing sprites, single cacheable file, per-block styling, WP < 7.1 support) and honest limitations (sprite tooling required, no code-free manager, no `wp_get_icon()` helper) are listed.
- This document is linked from the README.

## Non-goals

- Replacing the SVG Fragment block with the native Icon block.
- Registering symbols on WP < 7.1 (API does not exist; the fragment block already covers that).
- Providing a code-free icon manager/editor (out of scope; tooling like svgforge stays the workflow).
- Supporting keywords/alias search before core does.