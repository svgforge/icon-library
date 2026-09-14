<?php

/**
 * Short URL: rewrite /i.svg to serve the active sprite.
 *
 * Opt-in via the `icon_library_short_url` filter:
 *
 *     add_filter('icon_library_short_url', '__return_true');
 *
 * When enabled the plugin registers a custom rewrite rule so that /i.svg
 * always resolves to the current sprite file — regardless of its actual
 * location (upload directory, CDN, filter override).
 *
 * Apache picks up the rule from .htaccess after the rewrite rules were
 * flushed once (Settings → Permalinks → Save, or `wp rewrite flush`);
 * Nginx needs a one-line include or symlink (see docs/nginx.md).
 *
 * @package icon-library
 */
defined('ABSPATH') || exit;

/**
 * Returns whether the short-URL feature is enabled (filter opt-in).
 *
 * Filters are evaluated in memory only — this feature reads no option and
 * never touches the database.
 *
 * @return bool
 */
function icon_library_short_url_enabled(): bool
{
    return (bool) apply_filters('icon_library_short_url', false);
}

/**
 * Registers the /i.svg rewrite rule (only while enabled).
 *
 * The rewrite rules must be flushed once after the filter is enabled so that
 * .htaccess (Apache) contains the rule; disabling (removing the filter) then
 * removes it again after the next flush.
 */
function icon_library_short_url_init(): void
{
    if (icon_library_short_url_enabled()) {
        add_rewrite_rule('^i\.svg/?$', 'index.php?icon_library_svg=1', 'top');
    }
}
add_action('init', 'icon_library_short_url_init');

/**
 * Registers the custom query variable so WordPress passes it through.
 */
function icon_library_short_url_query_vars(array $vars): array
{
    $vars[] = 'icon_library_svg';

    return $vars;
}
add_filter('query_vars', 'icon_library_short_url_query_vars');

/**
 * Intercepts the request before WordPress resolves rewrite rules.
 *
 * For non-existent paths like /i.svg the rewrite rules are never loaded
 * from the database — this filter is the only hook that still fires.
 * Matches subdirectory installs (/blog/i.svg) and the optional trailing slash.
 *
 * The path check runs before the filter so regular requests never evaluate it.
 */
function icon_library_short_url_request(array $query): array
{
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    if (preg_match('#(^|/)i\.svg/?$#', (string) $path) !== 1) {
        return $query;
    }

    if (! icon_library_short_url_enabled()) {
        return $query;
    }

    $query['icon_library_svg'] = 1;

    return $query;
}
add_filter('request', 'icon_library_short_url_request');

/**
 * Serves the sprite file when the short-URL query variable is present.
 */
function icon_library_short_url_serve(): void
{
    if (! get_query_var('icon_library_svg')) {
        return;
    }

    if (! icon_library_short_url_enabled()) {
        return;
    }

    $sprite = icon_library_current_sprite();

    // Local file — most common case (uploads directory, bundled fallback).
    if ($sprite['path'] !== '' && is_readable($sprite['path'])) {
        $mtime = @filemtime($sprite['path']);

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Vary: Accept-Encoding');

        if ($mtime) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
            header('ETag: "' . md5($sprite['path'] . $mtime) . '"');
        }

        status_header(200);
        readfile($sprite['path']);
        exit;
    }

    // Remote sprite served through a filter — proxy with caching headers.
    if ('filter' === $sprite['source'] && $sprite['url'] !== '') {
        $response = wp_remote_get($sprite['url'], ['timeout' => 5]);

        if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);

            if ($body !== '') {
                header('Content-Type: image/svg+xml; charset=utf-8');
                header('Cache-Control: public, max-age=300');
                header('Vary: Accept-Encoding');
                status_header(200);
                echo $body;
                exit;
            }
        }
    }

    status_header(404);
    nocache_headers();
    exit;
}
add_action('template_redirect', 'icon_library_short_url_serve', 1);
