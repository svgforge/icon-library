<?php

/**
 * WordPress 7.1 native icon API integration.
 *
 * On sites running WordPress >= 7.1, every <symbol> from the configured sprite
 * is registered as an icon in the "icon-library" collection. The same sprite
 * therefore also powers the core Icon block picker, the wp/v2 REST endpoints
 * and wp_get_icon(). The SVG Icon block stays the primary, full-fidelity
 * experience; this integration is a companion, not a replacement.
 *
 * Registration is lazy and gated by the "icon_library_native" setting
 * (default 'off'): icons are only registered when the mode is 'on', and only
 * when something actually consumes them -- on any REST request
 * (rest_api_init) and right before a core Icon block (core/icon) renders.
 * A plain page that uses neither stays independent of the sprite size -- the
 * sprite is merely parsed once and cached until the source changes. In
 * 'no_block' mode the core Icon block is deregistered instead.
 *
 * Icons are limited to the <svg>/<path>/<polygon> subset and the attributes
 * that survive WordPress core's icon allowlist (no stroke, no inline styles).
 * Symbols without at least one <path> or <polygon> are skipped, because they
 * would render blank through the native API.
 *
 * @package icon-library
 */
defined('ABSPATH') || exit;

/**
 * Option key for the cached, parsed sprite icons.
 *
 * Stored with autoload disabled (the data is only needed during init).
 */
const ICON_LIBRARY_ICONS_OPTION = 'icon_library_icons';

/**
 * Option key for the native WordPress icon integration mode.
 *
 * Allowed values:
 *  - 'off'      Default. The plugin does not touch the WordPress icon API.
 *  - 'on'       Every sprite symbol is registered with the native icon API.
 *  - 'no_block' Like 'off', but the core Icon block is deregistered in the
 *               block editor and on the frontend.
 */
const ICON_LIBRARY_NATIVE_OPTION = 'icon_library_native';

/**
 * Returns the configured native icon integration mode.
 *
 * @return string 'off', 'on' or 'no_block'.
 */
function icon_library_native_setting(): string
{
    $value = get_option(ICON_LIBRARY_NATIVE_OPTION, 'off');

    return in_array($value, ['off', 'on', 'no_block'], true) ? $value : 'off';
}

/**
 * Normalizes a sprite symbol id into a valid WordPress icon name part.
 *
 * Core only accepts names that start and end with a lowercase letter or digit
 * and otherwise contain lowercase letters, digits, hyphens and underscores.
 *
 * @param string $id Symbol id from the sprite.
 * @return string Valid icon name suffix, or '' when nothing usable remains.
 */
function icon_library_icon_slug(string $id): string
{
    $slug = strtolower((string) $id);
    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? $slug;

    return trim($slug, '-_');
}

/**
 * Resolves the currently configured sprite source to a local file path.
 *
 * Source order mirrors icon_library_sprite_url(): filter, upload, fallback.
 * Remote filter URLs that cannot be mapped to a local file return ''.
 *
 * @return string Absolute path, or '' when the source is not a local file.
 */
function icon_library_sprite_source_path(): string
{
    return icon_library_current_sprite()['path'];
}

/**
 * Maps a URL to a local filesystem path when WordPress serves the file itself.
 *
 * @param string $url Sprite URL.
 * @return string Local path, or '' when the URL points elsewhere (e.g. a CDN).
 */
function icon_library_url_to_path(string $url): string
{
    $url = (string) $url;

    if ($url === '') {
        return '';
    }

    $home   = wp_parse_url(home_url());
    $parsed = wp_parse_url($url);
    $host   = strtolower((string) ($parsed['host'] ?? ''));

    if ($host !== '' && strtolower((string) ($home['host'] ?? '')) !== $host) {
        return '';
    }

    $path = urldecode((string) ($parsed['path'] ?? ''));

    if ($path === '' || str_ends_with($path, '/')) {
        return '';
    }

    foreach ([untrailingslashit(WP_CONTENT_DIR), untrailingslashit(ABSPATH)] as $base) {
        $candidate = wp_normalize_path($base . '/' . ltrim($path, '/'));

        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Returns the raw SVG markup of the configured sprite.
 *
 * @return string Sprite markup, or '' when the sprite is not readable.
 */
function icon_library_sprite_content(): string
{
    $sprite = icon_library_current_sprite();

    if ($sprite['path'] !== '' && is_readable($sprite['path'])) {
        return (string) file_get_contents($sprite['path']);
    }

    if ('filter' === $sprite['source'] && $sprite['url'] !== '') {
        $response = wp_remote_get($sprite['url'], ['timeout' => 5]);

        if (! is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);

            if ($body !== '') {
                return $body;
            }
        }
    }

    return '';
}

/**
 * Computes a signature for the configured sprite source.
 *
 * The cached icon list is rebuilt whenever the signature changes (different
 * source URL, changed file mtime or size).
 *
 * @return string
 */
function icon_library_sprite_source_signature(): string
{
    $sprite = icon_library_current_sprite();
    $extra  = '';

    if ($sprite['path'] !== '' && is_readable($sprite['path'])) {
        $extra = (string) @filemtime($sprite['path']) . '|' . (string) @filesize($sprite['path']);
    }

    return md5($sprite['url'] . '|' . $extra);
}

/**
 * Returns the parsed sprite icons, cached in an option.
 *
 * @return array[] List of ['name' => string, 'label' => string, 'content' => string].
 */
function icon_library_sprite_icons(): array
{
    $signature = icon_library_sprite_source_signature();
    $cached    = get_option(ICON_LIBRARY_ICONS_OPTION, null);

    if (is_array($cached) && ($cached['signature'] ?? '') === $signature && is_array($cached['icons'] ?? null)) {
        return $cached['icons'];
    }

    $icons = icon_library_parse_sprite_icons(icon_library_sprite_content());

    update_option(ICON_LIBRARY_ICONS_OPTION, ['signature' => $signature, 'icons' => $icons], false);

    return $icons;
}

/**
 * Clears the cached native icons (e.g. after the sprite file changed).
 *
 * @return void
 */
function icon_library_invalidate_native_icons(): void
{
    $GLOBALS['icon_library_native_registered'] = false;
    delete_option(ICON_LIBRARY_ICONS_OPTION);
}

/**
 * Parses the symbols of a sprite file into native icon definitions.
 *
 * Defensively accepts any input (e.g. a failed sprite read); non-string values
 * or unparseable markup produce an empty list.
 *
 * @param mixed $svg Raw sprite markup.
 * @return array[] List of ['name' => string, 'label' => string, 'content' => string].
 */
function icon_library_parse_sprite_icons(mixed $svg): array
{
    $icons = [];

    if (! is_string($svg) || $svg === '' || ! class_exists('DOMDocument')) {
        return $icons;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded   = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (! $loaded) {
        return $icons;
    }

    $xpath   = new DOMXPath($document);
    $symbols = $xpath->query('//*[local-name()="symbol"]');

    if (false === $symbols) {
        return $icons;
    }

    $seen = [];

    foreach ($symbols as $symbol) {
        $id = (string) $symbol->getAttribute('id');

        if ($id === '') {
            continue;
        }

        $slug = icon_library_icon_slug($id);

        if ($slug === '') {
            continue;
        }

        $name = 'icon-library/' . $slug;

        if (isset($seen[$name])) {
            continue;
        }

        $shapes = $xpath->query('.//*[local-name()="path" or local-name()="polygon"]', $symbol);

        if (false === $shapes || $shapes->length === 0) {
            continue;
        }

        $seen[$name] = true;

        $inner = '';

        foreach ($shapes as $shape) {
            $inner .= $document->saveXML(icon_library_icon_shape($shape));
        }

        $viewbox = (string) $symbol->getAttribute('viewBox');

        if ($viewbox === '') {
            $viewbox = (string) $symbol->getAttribute('viewbox');
        }

        $content = '<svg xmlns="http://www.w3.org/2000/svg"';

        if ($viewbox !== '') {
            $content .= ' viewBox="' . esc_attr($viewbox) . '"';
        }

        $content .= '>' . $inner . '</svg>';

        $icons[] = [
            'name' => $name,
            'label' => $id,
            'content' => $content,
        ];
    }

    return $icons;
}

/**
 * Lists every symbol id of the active sprite.
 *
 * Unlike icon_library_sprite_icons(), this enumerates the raw sprite symbols
 * without applying core's shape allowlist — it is meant for consumers that
 * render fragments via <use href="sprite.svg#id">, where the browser resolves
 * the full, unrestricted symbol itself.
 *
 * @return string[] Symbol ids, sorted alphabetically.
 */
function icon_library_sprite_symbols(): array
{
    $svg = icon_library_sprite_content();

    if (! is_string($svg) || $svg === '' || ! class_exists('DOMDocument')) {
        return [];
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded   = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (! $loaded) {
        return [];
    }

    $xpath   = new DOMXPath($document);
    $symbols = $xpath->query('//*[local-name()="symbol"]');

    if (false === $symbols) {
        return [];
    }

    $ids = [];

    foreach ($symbols as $symbol) {
        $id = (string) $symbol->getAttribute('id');

        if ($id !== '') {
            $ids[$id] = true;
        }
    }

    $ids = array_keys($ids);
    sort($ids);

    return $ids;
}

/**
 * Reduces a shape element to the attributes that survive core's icon allowlist.
 *
 * @param DOMElement $shape <path> or <polygon> element.
 * @return DOMElement The same element, with disallowed attributes removed.
 */
function icon_library_icon_shape(DOMElement $shape): DOMElement
{
    $allowed = 'polygon' === $shape->localName
        ? ['points', 'fill', 'fill-rule', 'transform', 'focusable']
        : ['d', 'fill', 'fill-rule', 'transform'];

    foreach (iterator_to_array($shape->attributes) as $attribute) {
        if (! in_array(strtolower((string) $attribute->nodeName), $allowed, true)) {
            $shape->removeAttributeNode($attribute);
        }
    }

    return $shape;
}

/**
 * Registers the sprite symbols with the WordPress 7.1 icon API when WordPress
 * is new enough, the filters allow it, and the sprite has icons.
 *
 * Idempotent within the request: a second call (e.g. from a theme that also
 * wants the icons early) registers nothing and cannot trigger core's
 * "Icon is already registered" warning. Use icon_library_ensure_native_icons()
 * as the public entry point.
 *
 * @return void
 */
function icon_library_register_native_icons(): void
{
    if (! empty($GLOBALS['icon_library_native_registered'])) {
        return;
    }

    if (icon_library_native_setting() !== 'on') {
        return;
    }

    if (! apply_filters('icon_library_register_native_icons', true)) {
        return;
    }

    if (! function_exists('wp_register_icon_collection') || ! function_exists('wp_register_icon')) {
        return;
    }

    $icons = icon_library_sprite_icons();

    if ($icons === []) {
        $GLOBALS['icon_library_native_registered'] = true;

        return;
    }

    wp_register_icon_collection('icon-library', [
        'label' => __('Icon Library', 'svg-forge-icon-manager'),
        'description' => __('Icons from the configured SVG sprite.', 'svg-forge-icon-manager'),
    ]);

    foreach ($icons as $icon) {
        wp_register_icon($icon['name'], [
            'label' => $icon['label'],
            'content' => $icon['content'],
        ]);
    }

    $GLOBALS['icon_library_native_registered'] = true;
}

/**
 * Lazily ensures the sprite icons are registered with the native icon API.
 *
 * Safe to call from anywhere (themes, plugins, hooks). Does nothing on
 * WordPress < 7.1 and when the opt-out filter returns false.
 *
 * @return void
 */
function icon_library_ensure_native_icons(): void
{
    if (! function_exists('wp_register_icon')) {
        return;
    }

    icon_library_register_native_icons();
}

/**
 * Ensures the icons are registered right before a core Icon block renders.
 *
 * Fired via the render_block_data filter, which runs before the block's
 * render callback (and thus before wp_get_icon() inside it).
 *
 * @param array $parsed_block Parsed block data.
 * @return array Unmodified parsed block data.
 */
function icon_library_ensure_on_icon_block(array $parsed_block): array
{
    if (($parsed_block['blockName'] ?? '') === 'core/icon') {
        icon_library_ensure_native_icons();
    }

    return $parsed_block;
}

add_action('rest_api_init', 'icon_library_ensure_native_icons');
add_filter('render_block_data', 'icon_library_ensure_on_icon_block');

/**
 * Deregisters a block type when the "no_block" mode is active.
 *
 * Removal happens repeatedly because on WordPress 7.1+ the block metadata
 * collection of core registers blocks lazily: any registry lookup after an
 * unregister would re-add it. Running the unregister late in init, during
 * REST setup and right before the block editor renders covers every path.
 *
 * The parameter stays untyped on purpose: the function doubles as a hook
 * callback for rest_api_init, which passes a WP_REST_Server object (never a
 * block name). The is_string() guard filters such hook arguments.
 *
 * @param mixed $block_name Block type to deregister. Default 'core/icon'.
 */
function icon_library_deregister_native_icon_block($block_name = 'core/icon'): void
{
    // Hook callbacks receive argument values (e.g. rest_api_init passes the
    // WP_REST_Server); those are never block names.
    if (! is_string($block_name) || $block_name === '') {
        $block_name = 'core/icon';
    }

    if (icon_library_native_setting() !== 'no_block' || ! class_exists('WP_Block_Type_Registry')) {
        return;
    }

    $registry = WP_Block_Type_Registry::get_instance();

    if ($registry->is_registered($block_name)) {
        $registry->unregister($block_name);
    }
}
add_action('init', 'icon_library_deregister_native_icon_block', PHP_INT_MAX);
add_action('rest_api_init', 'icon_library_deregister_native_icon_block', PHP_INT_MAX);
add_action('admin_enqueue_scripts', 'icon_library_deregister_native_icon_block', PHP_INT_MAX);
add_action('enqueue_block_editor_assets', 'icon_library_deregister_native_icon_block', PHP_INT_MAX);

/**
 * Removes the core Icon block from the editor's allowed block types.
 *
 * Falls back to the full registry when no explicit allowlist was set so the
 * block never appears in the inserter, without losing the current selection.
 *
 * @param bool|array|null $allowed Current allowlist, or a boolean to
 *                                 enable/disable all block types.
 * @return bool|array|null
 */
function icon_library_deny_native_icon_block_types(mixed $allowed): bool|array|null
{
    if (icon_library_native_setting() !== 'no_block') {
        return is_bool($allowed) || is_array($allowed) ? $allowed : null;
    }

    $exclude = ['core/icon'];

    if (false === $allowed) {
        return false;
    }

    if (is_array($allowed)) {
        return array_values(array_diff($allowed, $exclude));
    }

    $all = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());

    return array_values(array_diff($all, $exclude));
}
add_filter('allowed_block_types_all', 'icon_library_deny_native_icon_block_types');

/**
 * Suppresses the rendered output of the core Icon block on the frontend.
 *
 * Acts as a safety net when the block type was added back by core's lazy
 * metadata registration between deprecation and rendering. Never removes the
 * stored block markup from the content.
 *
 * @param string $block_content Rendered block content.
 * @param array $block Block data.
 * @return string
 */
function icon_library_strip_native_icon_block(string $block_content, array $block): string
{
    if (icon_library_native_setting() !== 'no_block') {
        return $block_content;
    }

    if (($block['blockName'] ?? '') === 'core/icon') {
        return '';
    }

    return $block_content;
}
add_filter('render_block', 'icon_library_strip_native_icon_block', PHP_INT_MAX, 2);

/**
 * Unregisters the core Icon block in the block editor when "no_block" mode is
 * active.
 *
 * Registers the blocks' client-side types independently of the server-side
 * block registry, so an unregister_block_type() alone leaves blocks that are
 * already saved in content working in the editor. This script removes the
 * block type there as well; domReady runs after the core block library has
 * registered the client-side types.
 */
function icon_library_native_unregister_block_editor_assets(): void
{
    if (icon_library_native_setting() !== 'no_block') {
        return;
    }

    $path = dirname(ICON_LIBRARY_PLUGIN_FILE) . '/assets/js/unregister-icon-block.js';

    if (! is_file($path)) {
        return;
    }

    wp_enqueue_script(
        'icon-library-unregister-icon-block',
        plugins_url('assets/js/unregister-icon-block.js', ICON_LIBRARY_PLUGIN_FILE),
        ['wp-dom-ready', 'wp-blocks'],
        (string) @filemtime($path),
        true,
    );
}
add_action('enqueue_block_editor_assets', 'icon_library_native_unregister_block_editor_assets');
