# Icon Library – API Reference

Public PHP functions, filters, and constants exposed by the Icon Library plugin.

---

## Table of Contents

- [Sprite URL Resolution](#sprite-url-resolution)
- [Short URL (/i.svg)](#short-url-isvg)
- [Filters](#filters)
- [Block Attribute Resolvers](#block-attribute-resolvers)
- [Sprite Helpers](#sprite-helpers)
- [Native Icon API (WordPress 7.1+)](#native-icon-api-wordpress-71)
- [Constants](#constants)

---

## Sprite URL Resolution

### `sfim_current_sprite()`

**File:** `sf-icon-manager.php:64`

Resolves the active SVG sprite source. This is the core function that determines which sprite file is used across the entire plugin.

**Source priority:**

| Priority | Source | Condition |
|----------|--------|-----------|
| 1 | Filter `sfim_sprite_url` | Non-empty string returned |
| 2 | Uploaded file (Settings → Icon Library) | Valid `sfim_sprite` option |
| 3 | Bundled fallback `sprite.svg` | File exists in plugin root |
| 4 | No sprite | — |

**Return value:**

```php
[
    'url'    => string,  // Absolute sprite URL (with cache-busting `?m=` for uploads)
    'path'   => string,  // Local filesystem path, or '' for remote/CDN URLs
    'source' => string,  // 'filter' | 'upload' | 'default' | 'none'
    'data'   => array,   // Upload metadata when source is 'upload', otherwise []
]
```

**Example:**

```php
$sprite = sfim_current_sprite();

if ($sprite['source'] === 'none') {
    // No sprite configured
}

// Use $sprite['url'] to build <use> references:
// <use href="sprite.svg#icon-name">
```

---

### `sfim_sprite_url()`

**File:** `sf-icon-manager.php:121`

Convenience wrapper — returns the URL string used in the rendered HTML.

With the short URL enabled (see the `sfim_short_url` filter below) it
returns the root-relative `/i.svg` (independent of the actual sprite source).
Otherwise it returns the result of `sfim_current_sprite()`.

This helps if you want to use icons directly in your custom template code.

```php
$url = sfim_sprite_url();
// Short URL off:  "https://example.com/wp-content/uploads/sprite.svg?m=1694000000"
// Short URL on:   "/i.svg"
// Enable with: add_filter('sfim_short_url', '__return_true');
```

> Note: the short URL assumes a root-level WordPress install. For WordPress
> in a subdirectory, the rewrite lives inside that subdirectory and the short
> URL becomes `/sub/dir/i.svg`.

---

## Short URL (/i.svg)

Enabled via code — there is no settings toggle:

```php
add_filter('sfim_short_url', '__return_true');
```

While enabled, every rendered icon references `/i.svg#symbol-id` instead of
the full sprite URL. Apache picks up the rewrite rule from `.htaccess` after
the rewrite rules are flushed; Nginx requires a short config block or symlink
(see [Rewrite rule](#rewrite-rule)).

The rewrite works for **any** sprite source — upload, bundled fallback, and
filter-sourced remote URLs.

### Filter `sfim_short_url`

**File:** `src/short-url.php:17`

Return a truthy value from this filter to enable the short URL. The return
value does not matter — the filter is a pure on/off switch that is evaluated
in memory only (no option, no database access).

```php
add_filter('sfim_short_url', '__return_true');

$enabled = sfim_short_url_enabled(); // true
```

### `sfim_short_url_request( array $query ): array`

**File:** `src/short-url.php:57`

Hooked on `request`. Intercepts requests for `/i.svg` before WordPress
resolves rewrite rules and tells WordPress to serve the sprite instead of
returning a 404.

```php
add_filter('request', 'sfim_short_url_request');
```

Typically you plug into this via the `sfim_short_url` filter only;
there is no reason to call it manually.

### `sfim_short_url_serve()`

**File:** `src/short-url.php:79`

Hooked on `template_redirect`. Serves the sprite file when the
`sfim_svg` query variable is set. Sets cache headers optimized for
Varnish/HTTP caching:

| Header | Local file | Remote (proxied) |
|--------|------------|-------------------|
| `Content-Type` | `image/svg+xml; charset=utf-8` | `image/svg+xml; charset=utf-8` |
| `Cache-Control` | `public, no-cache, must-revalidate` | `public, max-age=300` |
| `ETag` | `md5(path + filemtime)` | — |
| `Last-Modified` | file mtime | — |
| `Vary` | `Accept-Encoding` | `Accept-Encoding` |

**Cache invalidation:** `/i.svg` is a stable pointer to the *active* sprite,
so it must revalidate on every request. The `ETag`/`Last-Modified` pair is
derived from the file's modification time; a matching `If-None-Match` /
`If-Modified-Since` request gets a `304 Not Modified`, and a new sprite
upload (which changes the mtime) is picked up automatically — no manual
purge is required. Serving `immutable` here would wrongly pin the sprite
for a year and show stale icons until a hard refresh.

**Error handling:** returns a `404` (with `nocache_headers()`) when no
sprite is available.

### Rewrite rule

The plugin registers a WordPress rewrite rule:

```php
add_rewrite_rule('^i\.svg/?$', 'index.php?sfim_svg=1', 'top');
```

**Apache:** flush the rewrite rules once after enabling the filter so `.htaccess`
contains the rule — *Settings → Permalinks → Save* or `wp rewrite flush --hard`.

**Nginx:** add a `location` block to your server config:

```nginx
# Root-level WordPress
location = /i.svg {
    try_files /wp-content/uploads/sf-icon-manager/ico.svg /index.php?sfim_svg=1;
}

# WordPress in a subdirectory
location = /blog/i.svg {
    try_files /blog/wp-content/uploads/sf-icon-manager/ico.svg /blog/index.php?sfim_svg=1;
}
```

Alternatively, symlink the document root:

```bash
ln -sfn wp-content/uploads/sf-icon-manager/ico.svg i.svg
```

---

## Filters

### `sfim_sprite_url`

**File:** `sf-icon-manager.php:73`

Override the sprite source. Return a URL string to point the plugin at any sprite file.

- Takes priority over admin uploads and the bundled fallback.
- Supports local paths, CDN URLs, and theme-bundled sprites.
- The URL is used as-is — no cache-busting is added by the plugin.

**Usage:**

```php
// Point to a theme-bundled sprite
add_filter('sfim_sprite_url', function () {
    return get_theme_file_uri('assets/icons.svg');
});

// Point to a CDN-hosted sprite
add_filter('sfim_sprite_url', function () {
    return 'https://cdn.example.com/icons/sprite.svg';
});

// Dynamic sprite based on context
add_filter('sfim_sprite_url', function ($url) {
    if (is_page_template('templates/landing.php')) {
        return get_theme_file_uri('assets/landing-icons.svg');
    }
    return $url;
});
```

---

### `sfim_register_native_icons`

**File:** `src/native/icons.php:391`

Opt-out filter for the WordPress 7.1 native icon API registration. Return `false` to prevent icons from being registered, even when the native mode is set to `'on'`.

```php
add_filter('sfim_register_native_icons', '__return_false');
```

---

## Block Attribute Resolvers

These functions resolve Gutenberg's internal value formats (preset references, CSS custom properties) into usable CSS values. They are used by the server-side render callback but are also available for custom rendering.

### `sfim_resolve_color( string $value ): string`

**File:** `sf-icon-manager.php:139`

Resolves a Gutenberg block color value to a usable CSS color.

**Input formats handled:**

| Input | Output |
|-------|--------|
| `var:preset\|color\|vivid-red` | `var(--wp--preset--color--vivid-red)` |
| `vivid-red` (slug) | `var(--wp--preset--color--vivid-red)` |
| `#ff0000` | `#ff0000` (pass-through) |
| `rgb(255,0,0)` | `rgb(255,0,0)` (pass-through) |
| `var(--custom)` | `var(--custom)` (pass-through) |

```php
$color = sfim_resolve_color('vivid-red');
// → "var(--wp--preset--color--vivid-red)"

$color = sfim_resolve_color('#ff0000');
// → "#ff0000"
```

---

### `sfim_resolve_dimension( string $value, ?array $presets = null ): string`

**File:** `sf-icon-manager.php:168`

Resolves a Gutenberg dimension value to a usable CSS length.

**Resolution order:**

1. `var:preset|dimension|<slug>` — looks up presets from `theme.json`:
   - Per-block: `settings.blocks.sf-icon-manager/svg-icon.dimensions.dimensionSizes`
   - Global fallback: `settings.dimensions.dimensionSizes`
2. Raw CSS length (e.g. `42px`, `2em`) — passed through unchanged.

```php
$size = sfim_resolve_dimension('var:preset|dimension|icon-large');
// → "64px" (if theme.json defines slug "icon-large" → "64px")

$size = sfim_resolve_dimension('48px');
// → "48px"
```

**Parameter `$presets`:** Injectable array of dimension presets for unit testing. When `null`, the function reads from `theme.json` at runtime.

---

### `sfim_resolve_spacing( string $value ): string`

**File:** `sf-icon-manager.php:217`

Resolves a block spacing value to a usable CSS length.

| Input | Output |
|-------|--------|
| `var:preset\|spacing\|30` | `var(--wp--preset--spacing--30)` |
| `16px` | `16px` (pass-through) |

```php
$spacing = sfim_resolve_spacing('var:preset|spacing|30');
// → "var(--wp--preset--spacing--30)"
```

---

## Sprite Helpers

### `sfim_uploaded_sprite_data()`

**File:** `src/sprite.php:20`

Returns the stored upload data from the `sfim_sprite` option.

```php
$data = sfim_uploaded_sprite_data();

// Returns:
[
    'url'     => 'https://example.com/wp-content/uploads/sf-icon-manager/sprite.svg',
    'path'    => '/var/www/html/wp-content/uploads/sf-icon-manager/sprite.svg',
    'name'    => 'sprite.svg',
    'time'    => 1694000000,
    'symbols' => 142,
]
// or [] when no upload exists
```

---

### `sfim_uploaded_sprite_url()`

**File:** `src/sprite.php:36`

Returns just the URL of the uploaded sprite (`''` when none exists).

```php
$url = sfim_uploaded_sprite_url();
```

---

### `sfim_sprite_source_path()`

**File:** `src/native/icons.php:84`

Returns the local filesystem path of the active sprite source. Mirrors the same priority chain as `sfim_current_sprite()`.

```php
$path = sfim_sprite_source_path();
// "/var/www/html/wp-content/uploads/sf-icon-manager/sprite.svg"
// or '' for remote/CDN URLs
```

---

### `sfim_url_to_path( string $url ): string`

**File:** `src/native/icons.php:95`

Maps a URL to a local filesystem path when WordPress serves the file itself. Checks against `WP_CONTENT_DIR` and `ABSPATH`. Returns `''` for cross-origin or CDN URLs.

```php
$path = sfim_url_to_path('https://example.com/wp-content/uploads/sprite.svg');
// → "/var/www/html/wp-content/uploads/sprite.svg"

$path = sfim_url_to_path('https://cdn.example.com/icons.svg');
// → '' (external)
```

---

### `sfim_sprite_content()`

**File:** `src/native/icons.php:133`

Returns the raw SVG markup of the active sprite. Reads from the local file when possible; fetches via `wp_remote_get` for remote filter-sourced URLs.

```php
$svg = sfim_sprite_content();
// '<svg xmlns="http://www.w3.org/2000/svg">…</svg>'
```

---

### `sfim_sprite_symbols()`

**File:** `src/native/icons.php:308`

Lists every `<symbol>` id from the active sprite. Unlike `sfim_sprite_icons()`, this returns the raw ids without applying core's shape allowlist — intended for `<use>` consumers where the browser resolves the full symbol.

```php
$ids = sfim_sprite_symbols();
// ['arrow-down', 'arrow-left', 'arrow-right', 'arrow-up', …]
```

---

## Native Icon API (WordPress 7.1+)

Functions for integrating with the WordPress 7.1+ native icon API (`wp_register_icon`, `wp_register_icon_collection`).

### `sfim_native_setting()`

**File:** `src/native/icons.php:52`

Returns the configured native icon integration mode.

```php
$mode = sfim_native_setting();
// 'off' | 'on' | 'no_block'
```

| Mode | Behavior |
|------|----------|
| `'off'` | Default. Plugin does not touch the WordPress icon API. |
| `'on'` | Every sprite symbol is registered with the native icon API. |
| `'no_block'` | Like `'off'`, but the `core/icon` block is deregistered. |

---

### `sfim_ensure_native_icons()`

**File:** `src/native/icons.php:430`

Public entry point: lazily ensures icons are registered with the native API. Safe to call from anywhere (themes, plugins, hooks). Does nothing on WordPress < 7.1.

```php
// Ensure icons are available before a custom REST endpoint
add_action('rest_api_init', function () {
    sfim_ensure_native_icons();
    // … register custom routes
});
```

---

### `sfim_sprite_icons()`

**File:** `src/native/icons.php:181`

Returns parsed sprite icons with signature-based caching. Icons are rebuilt when the sprite source changes (different URL, mtime, or filesize).

```php
$icons = sfim_sprite_icons();

foreach ($icons as $icon) {
    // $icon['name']    → 'sf-icon-manager/arrow-down'
    // $icon['label']   → 'arrow-down'
    // $icon['content'] → '<svg xmlns="http://www.w3.org/2000/svg" viewBox="…">…</svg>'
}
```

---

### `sfim_parse_sprite_icons( string $svg ): array`

**File:** `src/native/icons.php:214`

Parses raw SVG markup into native icon definitions. Extracts `<path>` and `<polygon>` shapes, prunes disallowed attributes, and wraps each symbol in an `<svg>` root.

```php
$icons = sfim_parse_sprite_icons($rawSvg);
// Same structure as sfim_sprite_icons()
```

---

### `sfim_icon_slug( string $id ): string`

**File:** `src/native/icons.php:68`

Normalizes a sprite symbol id into a valid WordPress icon name (lowercase, alphanumeric + hyphens/underscores).

```php
$slug = sfim_icon_slug('My_Icon-Name');
// → "my_icon-name"
```

---

### `sfim_invalidate_native_icons()`

**File:** `src/native/icons.php:202`

Clears the cached native icons option and resets the registration flag. Call this after replacing or modifying the sprite file.

```php
sfim_invalidate_native_icons();
// Icons will be re-parsed on next access
```

---

## Constants

| Constant | File | Value | Purpose |
|----------|------|-------|---------|
| `SFIM_PLUGIN_FILE` | `sf-icon-manager.php:17` | `__FILE__` | Path to the main plugin file |
| `SFIM_SPRITE_OPTION` | `src/sprite.php:13` | `'sfim_sprite'` | Option key for uploaded sprite data |
| `SFIM_ICONS_OPTION` | `src/native/icons.php:34` | `'sfim_icons'` | Option key for cached parsed icons |
| `SFIM_NATIVE_OPTION` | `src/native/icons.php:45` | `'sfim_native'` | Option key for native integration mode |
