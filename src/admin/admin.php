<?php

/**
 * Settings page: upload the SVG sprite (fragment library).
 *
 * @package wp-iconizer
 */
defined('ABSPATH') || exit;

/**
 * Options key for the uploaded SVG sprite file.
 */
const WP_ICONIZER_SPRITE_OPTION = 'wp_iconizer_sprite';

/**
 * Returns the stored data of the uploaded SVG sprite file.
 *
 * @return array{url: string, path: string, name: string, time: int, symbols: int}|array{}
 */
function wp_iconizer_uploaded_sprite_data()
{
    $data = get_option(WP_ICONIZER_SPRITE_OPTION, []);

    if (! is_array($data) || ! isset($data['url'], $data['path'])) {
        return [];
    }

    return $data;
}

/**
 * Returns the URL of the uploaded SVG sprite file ('' when none exists).
 *
 * @return string
 */
function wp_iconizer_uploaded_sprite_url()
{
    $data = wp_iconizer_uploaded_sprite_data();

    return $data['url'] ?? '';
}

/**
 * Registers the settings page under Settings → WP Iconizer.
 */
function wp_iconizer_register_settings_page()
{
    add_options_page(
        __('WP Iconizer', 'wp-iconizer'),
        __('WP Iconizer', 'wp-iconizer'),
        'manage_options',
        'wp-iconizer',
        'wp_iconizer_settings_page',
    );
}
add_action('admin_menu', 'wp_iconizer_register_settings_page');

/**
 * Redirects back to the settings page after an action.
 *
 * @param string $message Key of the message to display.
 */
function wp_iconizer_settings_redirect($message)
{
    $url = add_query_arg(
        ['page' => 'wp-iconizer', 'wp_iconizer_message' => $message],
        admin_url('options-general.php'),
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Strips dangerous markup from SVG content (scripts, event handlers, javascript: links).
 *
 * @param string $svg Raw SVG content.
 * @return string Sanitized SVG content, or '' when no valid <svg> element remains.
 */
function wp_iconizer_sanitize_svg($svg)
{
    $svg = (string) $svg;

    if (trim($svg) === '') {
        return '';
    }

    // Remove script block elements.
    $svg = preg_replace('#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $svg) ?? $svg;
    $svg = preg_replace('#<\s*script\b[^>]*/\s*>#is', '', $svg) ?? $svg;

    // Remove foreignObject (may contain arbitrary HTML).
    $svg = preg_replace('#<\s*foreignObject\b[^>]*>.*?<\s*/\s*foreignObject\s*>#is', '', $svg) ?? $svg;

    // Remove event handler attributes (onclick, etc.).
    $svg = preg_replace('#\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#is', '', $svg) ?? $svg;

    // Remove javascript: links in href/xlink:href.
    $svg = preg_replace(
        '#\s(?:xlink:)?href\s*=\s*(?:"javascript:[^"]*"|\'javascript:[^\']*\'|javascript:[^\s>]+)#is',
        '',
        $svg,
    ) ?? $svg;

    // A <svg> element must still be present.
    if (preg_match('#<\s*svg\b#i', $svg) !== 1) {
        return '';
    }

    return $svg;
}

/**
 * Handles the upload of the SVG sprite file.
 */
function wp_iconizer_handle_sprite_upload()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'wp-iconizer'));
    }

    check_admin_referer('wp_iconizer_upload_sprite');

    if (empty($_FILES['wp_iconizer_sprite']) || ! empty($_FILES['wp_iconizer_sprite']['error'])) {
        wp_iconizer_settings_redirect('error_upload');
    }

    $file = $_FILES['wp_iconizer_sprite'];
    $tmp = (string) $file['tmp_name'];

    if ($tmp === '' || ! is_readable($tmp)) {
        wp_iconizer_settings_redirect('error_upload');
    }

    $name = sanitize_file_name(wp_unslash((string) $file['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (! in_array($ext, ['svg', 'svgz'], true)) {
        wp_iconizer_settings_redirect('error_type');
    }

    $raw = (string) file_get_contents($tmp);

    if ($raw === '') {
        wp_iconizer_settings_redirect('error_upload');
    }

    if ($ext === 'svgz' && function_exists('gzdecode')) {
        $decompressed = gzdecode($raw);

        if ($decompressed !== false) {
            $raw = $decompressed;
        }
    }

    $svg = wp_iconizer_sanitize_svg($raw);

    if ($svg === '') {
        wp_iconizer_settings_redirect('error_invalid');
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        wp_iconizer_settings_redirect('error_write');
    }

    $dir = trailingslashit($uploads['basedir']) . 'wp-iconizer';

    if (! wp_mkdir_p($dir)) {
        wp_iconizer_settings_redirect('error_write');
    }

    $filename = 'ico.svg';
    $path = trailingslashit($dir) . $filename;

    // Remove the previous file (in case it used a different name).
    $old = wp_iconizer_uploaded_sprite_data();

    if (isset($old['path']) && $old['path'] !== $path && is_string($old['path']) && is_readable($old['path'])) {
        wp_delete_file($old['path']);
    }

    if (file_put_contents($path, $svg) === false) {
        wp_iconizer_settings_redirect('error_write');
    }

    $svg_count = preg_match_all('#<\s*symbol\b#i', $svg, $matches) ? count($matches[0]) : 0;

    update_option(WP_ICONIZER_SPRITE_OPTION, [
        'url' => trailingslashit($uploads['baseurl']) . 'wp-iconizer/' . $filename,
        'path' => $path,
        'name' => $name,
        'time' => time(),
        'symbols' => $svg_count,
    ]);

    wp_iconizer_settings_redirect('uploaded');
}
add_action('admin_post_wp_iconizer_upload_sprite', 'wp_iconizer_handle_sprite_upload');

/**
 * Deletes the uploaded SVG sprite file and resets the setting.
 */
function wp_iconizer_handle_sprite_delete()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'wp-iconizer'));
    }

    check_admin_referer('wp_iconizer_delete_sprite');

    $data = wp_iconizer_uploaded_sprite_data();

    if (isset($data['path']) && is_string($data['path']) && is_readable($data['path'])) {
        wp_delete_file($data['path']);
    }

    delete_option(WP_ICONIZER_SPRITE_OPTION);

    wp_iconizer_settings_redirect('deleted');
}
add_action('admin_post_wp_iconizer_delete_sprite', 'wp_iconizer_handle_sprite_delete');

/**
 * Renders the settings page.
 */
function wp_iconizer_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $sprite_url = wp_iconizer_sprite_url();
    $uploaded = wp_iconizer_uploaded_sprite_data();

    if ($uploaded !== []) {
        $source_label = __('Upload (Settings)', 'wp-iconizer');
    } elseif ((string) apply_filters('wp_iconizer_sprite_url', '') !== '') {
        $source_label = __('Filter wp_iconizer_sprite_url', 'wp-iconizer');
    } else {
        $source_label = __('Default (sprite.svg bundled with the plugin)', 'wp-iconizer');
    }

    $messages = [
        'uploaded' => ['success', __('The SVG sprite file was uploaded and is now being used.', 'wp-iconizer')],
        'deleted' => ['success', __('The uploaded SVG sprite file was removed.', 'wp-iconizer')],
        'error_type' => ['error', __('Only .svg or .svgz files can be uploaded.', 'wp-iconizer')],
        'error_upload' => ['error', __('The file could not be read.', 'wp-iconizer')],
        'error_invalid' => ['error', __('The file is not a valid SVG file.', 'wp-iconizer')],
        'error_write' => ['error', __('The file could not be written.', 'wp-iconizer')],
    ];

    $message = isset($_GET['wp_iconizer_message'], $messages[$_GET['wp_iconizer_message']])
        ? $messages[sanitize_key($_GET['wp_iconizer_message'])]
        : null;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(__('WP Iconizer', 'wp-iconizer')); ?></h1>

        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message[0]); ?> is-dismissible">
                <p><?php echo esc_html($message[1]); ?></p>
            </div>
        <?php endif; ?>

        <h2 style="margin-bottom:0"><?php echo esc_html__('SVG fragment library', 'wp-iconizer'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Upload an SVG sprite file that serves as the central icon library for the SVG Fragment block.', 'wp-iconizer'); ?>
            <?php echo esc_html__('Each icon is a <symbol id="my-icon" viewBox="0 0 24 24">…</symbol> element.', 'wp-iconizer'); ?>
        </p>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Active sprite file', 'wp-iconizer'); ?></th>
                    <td>
                        <code><?php echo esc_html($sprite_url); ?></code>
                        <p class="description">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: Source of the sprite URL (constant, filter, upload, default). */
                                __('Source: %s', 'wp-iconizer'),
                                $source_label,
                            ));
    ?>
                        </p>
                    </td>
                </tr>
                <?php if ($uploaded !== []) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Uploaded file', 'wp-iconizer'); ?></th>
                        <td>
                            <p style="margin:0">
                                <?php echo esc_html($uploaded['name']); ?>
                                <span class="description">
                                    <?php
            echo esc_html(sprintf(
                /* translators: %1$d: Number of symbol elements, %2$s: Date of the upload. */
                __('(%1$d symbols, uploaded on %2$s)', 'wp-iconizer'),
                (int) $uploaded['symbols'],
                wp_date(get_option('date_format'), (int) $uploaded['time']),
            ));
                    ?>
                                </span>
                            </p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:.75em">
                                <?php wp_nonce_field('wp_iconizer_delete_sprite'); ?>
                                <input type="hidden" name="action" value="wp_iconizer_delete_sprite">
                                <button type="submit" class="button button-secondary">
                                    <?php echo esc_html__('Remove uploaded file', 'wp-iconizer'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2 style="margin-bottom:0"><?php echo esc_html__('Upload new file', 'wp-iconizer'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('An existing uploaded file is replaced by a new upload.', 'wp-iconizer'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="wp-iconizer-upload">
            <?php wp_nonce_field('wp_iconizer_upload_sprite'); ?>
            <input type="hidden" name="action" value="wp_iconizer_upload_sprite">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('SVG file (ico.svg)', 'wp-iconizer'); ?></th>
                        <td>
                            <input type="file" name="wp_iconizer_sprite" accept=".svg,.svgz,image/svg+xml" required>
                            <p class="description">
                                <?php echo esc_html__('Only .svg and .svgz files are accepted. The content is cleaned of scripts, event handlers and javascript: links on upload.', 'wp-iconizer'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Upload SVG sprite', 'wp-iconizer'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}
