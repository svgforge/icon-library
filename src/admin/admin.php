<?php

/**
 * Settings page: upload the SVG sprite (fragment library).
 *
 * @package icon-library
 */
defined('ABSPATH') || exit;

/**
 * Options key for the uploaded SVG sprite file.
 */
const ICON_LIBRARY_SPRITE_OPTION = 'icon_library_sprite';

/**
 * Returns the stored data of the uploaded SVG sprite file.
 *
 * @return array{url: string, path: string, name: string, time: int, symbols: int}|array{}
 */
function icon_library_uploaded_sprite_data()
{
    $data = get_option(ICON_LIBRARY_SPRITE_OPTION, []);

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
function icon_library_uploaded_sprite_url()
{
    $data = icon_library_uploaded_sprite_data();

    return $data['url'] ?? '';
}

/**
 * Registers the settings page under Settings → Icon Library.
 */
function icon_library_register_settings_page()
{
    add_options_page(
        __('Icon Library', 'icon-library'),
        __('Icon Library', 'icon-library'),
        'manage_options',
        'icon-library',
        'icon_library_settings_page',
    );
}
add_action('admin_menu', 'icon_library_register_settings_page');

/**
 * Redirects back to the settings page after an action.
 *
 * @param string $message Key of the message to display.
 */
function icon_library_settings_redirect($message)
{
    $url = add_query_arg(
        ['page' => 'icon-library', 'icon_library_message' => $message],
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
function icon_library_sanitize_svg($svg)
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
function icon_library_handle_sprite_upload()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'icon-library'));
    }

    check_admin_referer('icon_library_upload_sprite');

    if ((string) apply_filters('icon_library_sprite_url', '') !== '') {
        icon_library_settings_redirect('error_filter_active');
    }

    if (empty($_FILES['icon_library_sprite']) || ! empty($_FILES['icon_library_sprite']['error'])) {
        icon_library_settings_redirect('error_upload');
    }

    $file = $_FILES['icon_library_sprite'];
    $tmp = (string) $file['tmp_name'];

    if ($tmp === '' || ! is_readable($tmp)) {
        icon_library_settings_redirect('error_upload');
    }

    $name = sanitize_file_name(wp_unslash((string) $file['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (! in_array($ext, ['svg', 'svgz'], true)) {
        icon_library_settings_redirect('error_type');
    }

    $raw = (string) file_get_contents($tmp);

    if ($raw === '') {
        icon_library_settings_redirect('error_upload');
    }

    if ($ext === 'svgz' && function_exists('gzdecode')) {
        $decompressed = gzdecode($raw);

        if ($decompressed !== false) {
            $raw = $decompressed;
        }
    }

    $svg = icon_library_sanitize_svg($raw);

    if ($svg === '') {
        icon_library_settings_redirect('error_invalid');
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        icon_library_settings_redirect('error_write');
    }

    $dir = trailingslashit($uploads['basedir']) . 'icon-library';

    if (! wp_mkdir_p($dir)) {
        icon_library_settings_redirect('error_write');
    }

    $filename = 'ico.svg';
    $path = trailingslashit($dir) . $filename;

    // Remove the previous file (in case it used a different name).
    $old = icon_library_uploaded_sprite_data();

    if (isset($old['path']) && $old['path'] !== $path && is_string($old['path']) && is_readable($old['path'])) {
        wp_delete_file($old['path']);
    }

    if (file_put_contents($path, $svg) === false) {
        icon_library_settings_redirect('error_write');
    }

    $svg_count = preg_match_all('#<\s*symbol\b#i', $svg, $matches) ? count($matches[0]) : 0;

    update_option(ICON_LIBRARY_SPRITE_OPTION, [
        'url' => trailingslashit($uploads['baseurl']) . 'icon-library/' . $filename,
        'path' => $path,
        'name' => $name,
        'time' => time(),
        'symbols' => $svg_count,
    ]);

    icon_library_invalidate_native_icons();

    icon_library_settings_redirect('uploaded');
}
add_action('admin_post_icon_library_upload_sprite', 'icon_library_handle_sprite_upload');

/**
 * Deletes the uploaded SVG sprite file and resets the setting.
 */
function icon_library_handle_sprite_delete()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'icon-library'));
    }

    check_admin_referer('icon_library_delete_sprite');

    $data = icon_library_uploaded_sprite_data();

    if (isset($data['path']) && is_string($data['path']) && is_readable($data['path'])) {
        wp_delete_file($data['path']);
    }

    delete_option(ICON_LIBRARY_SPRITE_OPTION);

    icon_library_invalidate_native_icons();

    icon_library_settings_redirect('deleted');
}
add_action('admin_post_icon_library_delete_sprite', 'icon_library_handle_sprite_delete');

/**
 * Handles the native icon integration mode selection.
 */
function icon_library_handle_native_update()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'icon-library'));
    }

    check_admin_referer('icon_library_update_native');

    $value = sanitize_key(wp_unslash((string) ($_POST['icon_library_native'] ?? 'off')));

    if (! in_array($value, ['off', 'on', 'no_block'], true)) {
        $value = 'off';
    }

    update_option(ICON_LIBRARY_NATIVE_OPTION, $value);

    icon_library_settings_redirect('native_updated');
}
add_action('admin_post_icon_library_update_native', 'icon_library_handle_native_update');

/**
 * Renders the settings page.
 */
function icon_library_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $sprite_url = icon_library_sprite_url();
    $uploaded = icon_library_uploaded_sprite_data();
    $filter_active = (string) apply_filters('icon_library_sprite_url', '') !== '';

    if ($filter_active) {
        $source_label = __('Filter icon_library_sprite_url', 'icon-library');
    } elseif ($uploaded !== []) {
        $source_label = __('Upload (Settings)', 'icon-library');
    } else {
        $source_label = __('Default (sprite.svg bundled with the plugin)', 'icon-library');
    }

    $messages = [
        'uploaded' => ['success', __('The SVG sprite file was uploaded and is now being used.', 'icon-library')],
        'deleted' => ['success', __('The uploaded SVG sprite file was removed.', 'icon-library')],
        'error_type' => ['error', __('Only .svg or .svgz files can be uploaded.', 'icon-library')],
        'error_upload' => ['error', __('The file could not be read.', 'icon-library')],
        'error_invalid' => ['error', __('The file is not a valid SVG file.', 'icon-library')],
        'error_write' => ['error', __('The file could not be written.', 'icon-library')],
        'error_filter_active' => ['error', __('Uploading is disabled because the sprite file is overridden by the icon_library_sprite_url filter.', 'icon-library')],
        'native_updated' => ['success', __('The native icon integration setting was saved.', 'icon-library')],
    ];

    $message = isset($_GET['icon_library_message'], $messages[$_GET['icon_library_message']])
        ? $messages[sanitize_key($_GET['icon_library_message'])]
        : null;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(__('Icon Library', 'icon-library')); ?></h1>

        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message[0]); ?> is-dismissible">
                <p><?php echo esc_html($message[1]); ?></p>
            </div>
        <?php endif; ?>
 <?php if ($filter_active) : ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html__('The sprite file is currently overridden by the icon_library_sprite_url filter. Uploading a sprite file is disabled while the filter is active.', 'icon-library'); ?></p>
            </div>
        <?php endif; ?>

        <h2 style="margin-bottom:0"><?php echo esc_html__('SVG fragment library', 'icon-library'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Upload an SVG sprite file that serves as the central icon library for the SVG Fragment block.', 'icon-library'); ?>
            <?php echo esc_html__('Each icon is a <symbol id="my-icon" viewBox="0 0 24 24">…</symbol> element.', 'icon-library'); ?>
        </p>
<?php if (function_exists('wp_register_icon_collection')) : ?>
            <?php $native_mode = icon_library_native_setting(); ?>
            <?php if ($native_mode === 'on') : ?>
                <?php $native_icon_count = count(icon_library_sprite_icons()); ?>
                <p class="description" style="margin-top:.5em">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: Number of symbols registered with the native WordPress icon API. */
                        _n(
                            'WordPress 7.1+ only: this symbol is also registered in the built-in Icon block and the wp/v2 icons REST API.',
                            'WordPress 7.1+ only: these %d symbols are also registered in the built-in Icon block and the wp/v2 icons REST API.',
                            $native_icon_count,
                            'icon-library',
                        ),
                        $native_icon_count,
                    ));
                ?>
                </p>
            <?php elseif ($native_mode === 'no_block') : ?>
                <p class="description" style="margin-top:.5em">
                    <?php echo esc_html__('WordPress 7.1+ only: the built-in Icon block is disabled. Use the SVG Fragment block instead.', 'icon-library'); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Active sprite file', 'icon-library'); ?></th>
                    <td>
                        <code><?php echo esc_html($sprite_url); ?></code>
                        <p class="description">
                            <?php
                    echo esc_html(sprintf(
                        /* translators: %s: Source of the sprite URL (filter, upload, default). */
                        __('Source: %s', 'icon-library'),
                        $source_label,
                    ));
    ?>
                        </p>
                    </td>
                </tr>
                <?php if ($uploaded !== []) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Uploaded file', 'icon-library'); ?></th>
                        <td>
                            <p style="margin:0">
                                <?php echo esc_html($uploaded['name']); ?>
                                <span class="description">
                                    <?php
            echo esc_html(sprintf(
                /* translators: %1$d: Number of symbol elements, %2$s: Date of the upload. */
                __('(%1$d symbols, uploaded on %2$s)', 'icon-library'),
                (int) $uploaded['symbols'],
                wp_date(get_option('date_format'), (int) $uploaded['time']),
            ));
                    ?>
                                </span>
                            </p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:.75em">
                                <?php wp_nonce_field('icon_library_delete_sprite'); ?>
                                <input type="hidden" name="action" value="icon_library_delete_sprite">
                                <button type="submit" class="button button-secondary">
                                    <?php echo esc_html__('Remove uploaded file', 'icon-library'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (function_exists('wp_register_icon_collection')) : ?>
        <h2 style="margin-bottom:0"><?php echo esc_html__('WordPress native icon integration (experimental)', 'icon-library'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Only relevant on WordPress 7.1+ which ships the built-in Icon block and the wp/v2 icons REST API. Experimental — the API and its behavior may change with core updates.', 'icon-library'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('icon_library_update_native'); ?>
            <input type="hidden" name="action" value="icon_library_update_native">
            <fieldset>
                <legend class="screen-reader-text"><?php echo esc_html__('WordPress native icon integration (experimental)', 'icon-library'); ?></legend>
                <?php $native_mode = icon_library_native_setting(); ?>
                <ul>
                    <li>
                        <label>
                            <input type="radio" name="icon_library_native" value="off" <?php checked('off', $native_mode); ?>>
                            <?php echo esc_html__('Off', 'icon-library'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Default. The plugin does not touch the WordPress icon API; the built-in Icon block stays as in core.', 'icon-library'); ?>
                        </p>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="icon_library_native" value="no_block" <?php checked('no_block', $native_mode); ?>>
                            <?php echo esc_html__('Off, and hide the built-in Icon block', 'icon-library'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Like Off, but the built-in core/icon block is deregistered in the block editor and on the frontend. Use the SVG Fragment block instead.', 'icon-library'); ?>
                        </p>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="icon_library_native" value="on" <?php checked('on', $native_mode); ?>>
                            <?php echo esc_html__('On', 'icon-library'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Every symbol of the configured sprite is registered as an icon in the icon-library collection — available in the built-in Icon block picker, the wp/v2 icons REST API and wp_get_icon().', 'icon-library'); ?>
                        </p>
                    </li>
                </ul>
            </fieldset>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Save native icon settings', 'icon-library'); ?>
                </button>
            </p>
        </form>
        <?php endif; ?>

        <h2 style="margin-bottom:0"><?php echo esc_html__('Upload new file', 'icon-library'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('An existing uploaded file is replaced by a new upload.', 'icon-library'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="icon-library-upload">
            <?php wp_nonce_field('icon_library_upload_sprite'); ?>
            <input type="hidden" name="action" value="icon_library_upload_sprite">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('SVG file (ico.svg)', 'icon-library'); ?></th>
                        <td>
                            <input type="file" name="icon_library_sprite" accept=".svg,.svgz,image/svg+xml" required <?php disabled($filter_active); ?>>
                            <p class="description">
                                <?php echo esc_html__('Only .svg and .svgz files are accepted. svgforge-cli handles full sanitization and svgo optimization; as a safety net, the plugin strips scripts, event handlers and javascript: links on upload.', 'icon-library'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary" <?php disabled($filter_active); ?>>
                    <?php echo esc_html__('Upload SVG sprite', 'icon-library'); ?>
                </button>
            </p>
        </form>

        <h2 style="margin-bottom:0"><?php echo esc_html__('Generate a sprite with svgforge-cli', 'icon-library'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Install svgforge-cli and create a symbol sprite from a folder of icons, then upload the generated SVG file.', 'icon-library'); ?>
        </p>
        <?php $cli_code = "npm install --global @svgforge/svgforge-cli\nsvgforge --symbol --dest=out 'assets/./**/*.svg'"; ?>
        <pre style="padding: 10px; overflow: auto"><code><?php echo esc_html($cli_code); ?></code></pre>
        <p class="description">
            <?php echo esc_html__('SVG files in subdirectories get IDs like directory--filename — each directory is then available as a filter in the icon picker.', 'icon-library'); ?>
        </p>
    </div>
    <?php
}
