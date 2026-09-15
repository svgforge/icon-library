<?php

/**
 * Short URL: rewrite /i.svg to serve the active sprite.
 *
 * Opt-in via the `sfim_short_url` filter:
 *
 *     add_filter('sfim_short_url', '__return_true');
 *
 * When enabled the plugin registers a custom rewrite rule so that /i.svg
 * always resolves to the current sprite file — regardless of its actual
 * location (upload directory, CDN, filter override).
 *
 * Apache picks up the rule from .htaccess after the rewrite rules were
 * flushed once (Settings → Permalinks → Save, or `wp rewrite flush`);
 * Nginx needs a one-line include or symlink (see docs/nginx.md).
 *
 * @package sf-icon-manager
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
function sfim_short_url_enabled(): bool
{
    return (bool) apply_filters('sfim_short_url', false);
}

/**
 * Registers the /i.svg rewrite rule (only while enabled).
 *
 * The rewrite rules must be flushed once after the filter is enabled so that
 * .htaccess (Apache) contains the rule; disabling (removing the filter) then
 * removes it again after the next flush.
 */
function sfim_short_url_init(): void
{
    if (sfim_short_url_enabled()) {
        add_rewrite_rule('^i\.svg/?$', 'index.php?sfim_svg=1', 'top');
    }
}
add_action('init', 'sfim_short_url_init');

/**
 * Registers the custom query variable so WordPress passes it through.
 */
function sfim_short_url_query_vars(array $vars): array
{
    $vars[] = 'sfim_svg';

    return $vars;
}
add_filter('query_vars', 'sfim_short_url_query_vars');

/**
 * Intercepts the request before WordPress resolves rewrite rules.
 *
 * For non-existent paths like /i.svg the rewrite rules are never loaded
 * from the database — this filter is the only hook that still fires.
 * Matches subdirectory installs (/blog/i.svg) and the optional trailing slash.
 *
 * The path check runs before the filter so regular requests never evaluate it.
 */
function sfim_short_url_request(array $query): array
{
    $path = wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH);

    if (preg_match('#(^|/)i\.svg/?$#', (string) $path) !== 1) {
        return $query;
    }

    if (! sfim_short_url_enabled()) {
        return $query;
    }

    $query['sfim_svg'] = 1;

    return $query;
}
add_filter('request', 'sfim_short_url_request');

/**
 * Returns whether the client's cached copy is still valid.
 *
 * Compares the `If-None-Match` ETag and the `If-Modified-Since` date against
 * the validators of the current sprite file. Accepts a weak `W/` prefix on the
 * ETag: browsers echo back whatever validator they last stored, and origins
 * weaken strong tags (e.g. nginx gzip turns `"…"` into `W/"…"`).
 */
function sfim_short_url_validator_hit(
    string $if_none_match,
    string $etag,
    string $if_modified,
    string $modified,
): bool {
    if ($if_modified !== '' && $if_modified === $modified) {
        return true;
    }

    if ($if_none_match === '') {
        return false;
    }

    return preg_replace('/^W\//', '', $if_none_match) === $etag;
}

/**
 * Serves the sprite file when the short-URL query variable is present.
 */
function sfim_short_url_serve(): void
{
    if (! get_query_var('sfim_svg')) {
        return;
    }

    if (! sfim_short_url_enabled()) {
        return;
    }

    $sprite = sfim_current_sprite();

    // Local file — most common case (uploads directory, bundled fallback).
    if ($sprite['path'] !== '' && is_readable($sprite['path'])) {
        $mtime = @filemtime($sprite['path']);

        // /i.svg is a stable pointer to the ACTIVE sprite: its content changes
        // whenever the source or file changes (upload, filter override, plugin
        // update). It must therefore be revalidated, not cached immutably — a
        // long-lived or immutable entry would keep serving a stale sprite
        // (blank previews, missing symbols) until a hard refresh. The
        // ETag/Last-Modified validators make each revalidation cheap (304).
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, no-cache, must-revalidate');
        header('Vary: Accept-Encoding');

        if ($mtime) {
            $etag     = '"' . md5($sprite['path'] . $mtime) . '"';
            $modified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

            header('ETag: ' . $etag);
            header('Last-Modified: ' . $modified);

            $if_none_match = (string) sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
            $if_modified   = (string) sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));

            if (sfim_short_url_validator_hit($if_none_match, $etag, $if_modified, $modified)) {
                status_header(304);
                exit;
            }
        }

        status_header(200);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the sprite file without buffering it into memory.
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
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw SVG document served with text/xml headers; escaping would corrupt the markup.
                echo $body;
                exit;
            }
        }
    }

    status_header(404);
    nocache_headers();
    exit;
}
add_action('template_redirect', 'sfim_short_url_serve', 1);
