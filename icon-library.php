<?php

/**
 * Plugin Name:       Icon Library
 * Description:       Gutenberg block that inserts SVG icons from a sprite file (ico.svg) via <use> and links them.
 * Version:           0.2.1
 * Requires at least: 6.6
 * Requires PHP:      8.3
 * Author:            svgforge
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       icon-library
 * Domain Path:       /languages
 */
defined('ABSPATH') || exit;

defined('ICON_LIBRARY_PLUGIN_FILE') || define('ICON_LIBRARY_PLUGIN_FILE', __FILE__);

/**
 * Loads the settings page (SVG upload).
 */
require_once __DIR__ . '/src/admin/admin.php';

/**
 * Loads the WordPress 7.1 native icon API integration.
 */
require_once __DIR__ . '/src/native/icons.php';

/**
 * Resolves the active SVG sprite source.
 *
 * Source order:
 *  1. Filter icon_library_sprite_url (theme override, CDN) — has priority.
 *  2. Uploaded file from the settings (backend upload).
 *  3. Fallback: sprite.svg bundled with the plugin.
 *
 * The filter is the only supported way to override the sprite source and
 * therefore also wins over a backend upload.
 *
 * @return array{url: string, path: string, source: string, data: array}
 *               url:    Absolute sprite URL used by consumers (includes the
 *                       cache-busting m parameter for uploads).
 *               path:   Local filesystem path when WordPress can read the
 *                       source itself, otherwise ''.
 *               source: 'filter', 'upload', 'default' or 'none'.
 *               data:   Stored upload data (ICON_LIBRARY_SPRITE_OPTION) when
 *                       source is 'upload', otherwise [].
 */
function icon_library_current_sprite()
{
    $none = [
        'url'    => '',
        'path'   => '',
        'source' => 'none',
        'data'   => [],
    ];

    $filtered = (string) apply_filters('icon_library_sprite_url', '');

    if ($filtered !== '') {
        return [
            'url'    => $filtered,
            'path'   => function_exists('icon_library_url_to_path') ? icon_library_url_to_path($filtered) : '',
            'source' => 'filter',
            'data'   => [],
        ];
    }

    if (function_exists('icon_library_uploaded_sprite_data')) {
        $data = icon_library_uploaded_sprite_data();

        if ($data !== []) {
            $path = (string) ($data['path'] ?? '');

            if (! is_readable($path)) {
                $path = '';
            }

            return [
                'url'    => (int) ($data['time'] ?? 0) > 0
                    ? add_query_arg('m', (int) $data['time'], $data['url'])
                    : $data['url'],
                'path'   => $path,
                'source' => 'upload',
                'data'   => $data,
            ];
        }
    }

    $bundled_path = dirname(ICON_LIBRARY_PLUGIN_FILE) . '/sprite.svg';
    $readable    = is_readable($bundled_path);

    return $readable ? [
        'url'    => plugins_url('sprite.svg', __FILE__),
        'path'   => $bundled_path,
        'source' => 'default',
        'data'   => [],
    ] : $none;
}

/**
 * Returns the URL of the SVG sprite file.
 *
 * @return string
 */
function icon_library_sprite_url()
{
    return icon_library_current_sprite()['url'];
}

/**
 * Resolves a block color value to a usable CSS color.
 *
 * Handles the two shapes Gutenberg stores colors in:
 *  - a preset slug in the `textColor` / `backgroundColor` attributes (e.g. `vivid-red`)
 *  - a `var:preset|color|<slug>` value or a raw CSS color in `style.color.*`
 *
 * Preset slugs are mapped to their theme CSS custom property so the value
 * always matches the theme palette.
 *
 * @param string $value Raw color value from block attributes.
 * @return string
 */
function icon_library_resolve_color($value)
{
    $prefix = 'var:preset|color|';

    if (str_starts_with($value, $prefix)) {
        return 'var(--wp--preset--color--' . substr($value, strlen($prefix)) . ')';
    }

    if (str_starts_with($value, 'var(') || str_starts_with($value, '#') || str_starts_with($value, 'rgb')) {
        return $value;
    }

    return 'var(--wp--preset--color--' . $value . ')';
}

/**
 * Resolves a block dimension value to a usable CSS length.
 *
 * Handles the shapes Gutenberg stores dimension values in:
 *  - a `var:preset|dimension|<slug>` preset reference resolved against the
 *    theme's `settings.dimensions.dimensionSizes`
 *  - any raw CSS length (e.g. `42px`, `2em`)
 *
 * @param string       $value   Raw dimension value from block attributes.
 * @param array|null   $presets Optional dimension size presets (origin-keyed).
 *                              Defaults to the theme settings lookup. Injectable
 *                              for unit tests.
 * @return string The resolved size (e.g. `64px`) or an empty string when unknown.
 */
function icon_library_resolve_dimension($value, $presets = null)
{
    $prefix = 'var:preset|dimension|';

    if (str_starts_with($value, $prefix)) {
        $slug = substr($value, strlen($prefix));

        if (! is_array($presets)) {
            // Per-block dimensionSizes first (the usual place for these presets),
            // then the global settings as a fallback.
            $presets = wp_get_global_settings(['blocks', 'icon-library/svg-icon', 'dimensions', 'dimensionSizes']);
            if (! is_array($presets)) {
                $presets = wp_get_global_settings(['dimensions', 'dimensionSizes']);
            }
        }

        if (is_array($presets)) {
            foreach ($presets as $entries) {
                if (! is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (! is_array($entry) || ! isset($entry['slug'], $entry['size'])) {
                        continue;
                    }
                    if ($entry['slug'] === $slug) {
                        $size = is_array($entry['size']) ? reset($entry['size']) : $entry['size'];
                        return (string) $size;
                    }
                }
            }
        }

        return '';
    }

    return (string) $value;
}

/**
 * Registers the block from the block.json in /build/block.
 */
function icon_library_register_block()
{
    register_block_type(__DIR__ . '/build/block');
}
add_action('init', 'icon_library_register_block');

/**
 * Returns the kses-allowlist for the SVG markup the block renders.
 *
 * The render template composes the `<svg>`/`<use>` fragment (and the optional
 * link) from already escaped values and passes the final markup through
 * wp_kses() before output.
 *
 * @since 0.2.1
 * @return array<string, array<string, true>>
 */
function icon_library_allowed_svg_kses()
{
    $svg_attrs = [
        'aria-hidden' => true,
        'aria-label'  => true,
        'class'       => true,
        'focusable'   => true,
        'role'        => true,
        'style'       => true,
        'viewbox'     => true,
    ];

    return [
        'a'   => array_merge(
            [
                'href'   => true,
                'target' => true,
                'rel'    => true,
                'id'     => true,
                'style'  => true,
            ],
            $svg_attrs,
        ),
        'div' => [
            'class' => true,
            'id'    => true,
            'style' => true,
        ],
        'svg' => $svg_attrs,
        'use' => [
            'href'       => true,
            'xlink:href' => true,
            'xlink'      => true,
        ],
    ];
}

/**
 * Provides the sprite URL to the editor as window.iconLibrarySettings.spriteUrl.
 *
 * Runs on enqueue_block_editor_assets so that the editorScript generated by
 * register_block_type (handle: icon-library-svg-icon-editor-script)
 * is already registered.
 */
function icon_library_editor_assets()
{
    wp_localize_script(
        'icon-library-svg-icon-editor-script',
        'iconLibrarySettings',
        ['spriteUrl' => icon_library_sprite_url()],
    );
}
add_action('enqueue_block_editor_assets', 'icon_library_editor_assets');
