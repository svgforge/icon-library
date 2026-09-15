<?php

/**
 * Settings page: upload the SVG sprite (fragment library).
 *
 * @package sf-icon-manager
 */
defined('ABSPATH') || exit;

require_once dirname(__DIR__) . '/sprite.php';

/**
 * Registers the settings page under Settings → Icon Library.
 */
function sfim_register_settings_page(): void
{
    add_options_page(
        __('Icon Library', 'sf-icon-manager'),
        __('Icon Library', 'sf-icon-manager'),
        'manage_options',
        'sf-icon-manager',
        'sfim_settings_page',
    );
}
add_action('admin_menu', 'sfim_register_settings_page');

/**
 * Redirects back to the settings page after an action.
 *
 * @param string $message Key of the message to display.
 */
function sfim_settings_redirect(string $message): void
{
    $url = add_query_arg(
        ['page' => 'sf-icon-manager', 'sfim_message' => $message],
        admin_url('options-general.php'),
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Strips dangerous markup from SVG content via a strict allowlist.
 *
 * Delegates to enshrined/svg-sanitize (the same engine the safe-svg plugin
 * uses): the file is parsed as XML, everything that is not an explicitly
 * allowed element or attribute is removed (scripts, event handlers,
 * foreignObject, unknown tags) and link targets are limited to fragments,
 * relative URLs, http(s) and known raster data URIs. DOCTYPE/DTD and PHP
 * processing instructions are stripped before parsing, so entity-based and
 * defaulted-attribute attacks never reach libxml. The output is minified and
 * the XML declaration removed.
 *
 * @param string $svg Raw SVG content.
 * @return string Sanitized SVG content, or '' when no valid <svg> element remains.
 */
function sfim_sanitize_svg(string $svg): string
{
    $svg = (string) $svg;

    if (trim($svg) === '') {
        return '';
    }

    if (! class_exists('enshrined\svgSanitize\Sanitizer')) {
        return '';
    }

    $sanitizer = new enshrined\svgSanitize\Sanitizer();
    $sanitizer->minify(true);
    $sanitizer->removeXMLTag(true);

    try {
        $clean = $sanitizer->sanitize($svg);
    } catch (Throwable $exception) {
        // Malformed input without an <svg> root makes the library throw.
        return '';
    }

    if (false === $clean) {
        return '';
    }

    // The allowlist does not enforce a <svg> root element; require one so the
    // stored sprite always remains a well-formed SVG document.
    if (preg_match('#<\s*svg\b#i', $clean) !== 1) {
        return '';
    }

    return $clean;
}

/**
 * Handles the upload of the SVG sprite file.
 */
function sfim_handle_sprite_upload(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'sf-icon-manager'));
    }

    check_admin_referer('sfim_upload_sprite');

    if ((string) apply_filters('sfim_sprite_url', '') !== '') {
        sfim_settings_redirect('error_filter_active');
    }

    if (empty($_FILES['sfim_sprite']) || ! empty($_FILES['sfim_sprite']['error'])) {
        sfim_settings_redirect('error_upload');
    }

    $file = $_FILES['sfim_sprite'];
    $tmp = (string) $file['tmp_name'];

    if ($tmp === '' || ! is_readable($tmp)) {
        sfim_settings_redirect('error_upload');
    }

    $name = sanitize_file_name(wp_unslash((string) $file['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (! in_array($ext, ['svg', 'svgz'], true)) {
        sfim_settings_redirect('error_type');
    }

    $raw = (string) file_get_contents($tmp);

    if ($raw === '') {
        sfim_settings_redirect('error_upload');
    }

    if ($ext === 'svgz' && function_exists('gzdecode')) {
        $decompressed = gzdecode($raw);

        if ($decompressed !== false) {
            $raw = $decompressed;
        }
    }

    $svg = sfim_sanitize_svg($raw);

    if ($svg === '') {
        sfim_settings_redirect('error_invalid');
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        sfim_settings_redirect('error_write');
    }

    $dir = trailingslashit($uploads['basedir']) . 'sf-icon-manager';

    if (! wp_mkdir_p($dir)) {
        sfim_settings_redirect('error_write');
    }

    $filename = 'ico.svg';
    $path = trailingslashit($dir) . $filename;

    // Remove the previous file (in case it used a different name).
    $old = sfim_uploaded_sprite_data();

    if (isset($old['path']) && $old['path'] !== $path && is_string($old['path']) && is_readable($old['path'])) {
        wp_delete_file($old['path']);
    }

    if (file_put_contents($path, $svg) === false) {
        sfim_settings_redirect('error_write');
    }

    $svg_count = preg_match_all('#<\s*symbol\b#i', $svg, $matches) ? count($matches[0]) : 0;

    update_option(SFIM_SPRITE_OPTION, [
        'url' => trailingslashit($uploads['baseurl']) . 'sf-icon-manager/' . $filename,
        'path' => $path,
        'name' => $name,
        'time' => time(),
        'symbols' => $svg_count,
    ]);

    sfim_invalidate_native_icons();

    sfim_settings_redirect('uploaded');
}
add_action('admin_post_sfim_upload_sprite', 'sfim_handle_sprite_upload');

/**
 * Deletes the uploaded SVG sprite file and resets the setting.
 */
function sfim_handle_sprite_delete(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'sf-icon-manager'));
    }

    check_admin_referer('sfim_delete_sprite');

    $data = sfim_uploaded_sprite_data();

    if (isset($data['path']) && is_string($data['path']) && is_readable($data['path'])) {
        wp_delete_file($data['path']);
    }

    delete_option(SFIM_SPRITE_OPTION);

    sfim_invalidate_native_icons();

    sfim_settings_redirect('deleted');
}
add_action('admin_post_sfim_delete_sprite', 'sfim_handle_sprite_delete');

/**
 * Handles the native icon integration mode selection.
 */
function sfim_handle_native_update(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'sf-icon-manager'));
    }

    check_admin_referer('sfim_update_native');

    $value = sanitize_key(wp_unslash((string) ($_POST['sfim_native'] ?? 'off')));

    if (! in_array($value, ['off', 'on', 'no_block'], true)) {
        $value = 'off';
    }

    update_option(SFIM_NATIVE_OPTION, $value);

    sfim_settings_redirect('native_updated');
}
add_action('admin_post_sfim_update_native', 'sfim_handle_native_update');

/**
 * Renders the settings page with its tabs (settings and sprite preview).
 */
function sfim_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $tab = 'settings';

    if (isset($_GET['tab'])) {
        $requested = sanitize_key(wp_unslash($_GET['tab']));

        if ('preview' === $requested) {
            $tab = $requested;
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Icon Library', 'sf-icon-manager'); ?></h1>

        <nav class="nav-tab-wrapper">
            <a class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('options-general.php?page=sf-icon-manager')); ?>">
                <?php echo esc_html__('Settings', 'sf-icon-manager'); ?>
            </a>
            <a class="nav-tab<?php echo 'preview' === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('options-general.php?page=sf-icon-manager&tab=preview')); ?>">
                <?php echo esc_html__('Sprite preview', 'sf-icon-manager'); ?>
            </a>
        </nav>

        <?php
        if ('preview' === $tab) {
            sfim_sprite_preview_panel();
        } else {
            sfim_settings_panel();
        }
    ?>
    </div>
    <?php
}

/**
 * Renders the settings panel (sprite upload and native icon integration).
 */
function sfim_settings_panel(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $sprite        = sfim_current_sprite();
    $sprite_url    = $sprite['url'];
    $short_url     = function_exists('sfim_short_url_enabled') && sfim_short_url_enabled()
        ? sfim_sprite_url()
        : '';
    $uploaded      = $sprite['data'];
    $filter_active = 'filter' === $sprite['source'];

    switch ($sprite['source']) {
        case 'filter':
            $source_label = __('Filter sfim_sprite_url', 'sf-icon-manager');
            break;

        case 'upload':
            $source_label = __('Upload (Settings)', 'sf-icon-manager');
            break;

        case 'default':
        default:
            $source_label = __('Default (sprite.svg bundled with the plugin)', 'sf-icon-manager');
            break;
    }

    $messages = [
        'uploaded' => ['success', __('The SVG sprite file was uploaded and is now being used.', 'sf-icon-manager')],
        'deleted' => ['success', __('The uploaded SVG sprite file was removed.', 'sf-icon-manager')],
        'error_type' => ['error', __('Only .svg or .svgz files can be uploaded.', 'sf-icon-manager')],
        'error_upload' => ['error', __('The file could not be read.', 'sf-icon-manager')],
        'error_invalid' => ['error', __('The file is not a valid SVG file.', 'sf-icon-manager')],
        'error_write' => ['error', __('The file could not be written.', 'sf-icon-manager')],
        'error_filter_active' => ['error', __('Uploading is disabled because the sprite file is overridden by the sfim_sprite_url filter.', 'sf-icon-manager')],
        'native_updated' => ['success', __('The native icon integration setting was saved.', 'sf-icon-manager')],
    ];

    $message = isset($_GET['sfim_message'], $messages[$_GET['sfim_message']])
        ? $messages[sanitize_key($_GET['sfim_message'])]
        : null;
    ?>

        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message[0]); ?> is-dismissible">
                <p><?php echo esc_html($message[1]); ?></p>
            </div>
        <?php endif; ?>
 <?php if ($filter_active) : ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html__('The sprite file is currently overridden by the sfim_sprite_url filter. Uploading a sprite file is disabled while the filter is active.', 'sf-icon-manager'); ?></p>
            </div>
        <?php endif; ?>

        <h2 style="margin-bottom:0"><?php echo esc_html__('SVG fragment library', 'sf-icon-manager'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Upload an SVG sprite file that serves as the central icon library for the SVG Icon block.', 'sf-icon-manager'); ?>
            <?php echo esc_html__('Each icon is a <symbol id="my-icon" viewBox="0 0 24 24">…</symbol> element.', 'sf-icon-manager'); ?>
        </p>
<?php if (function_exists('wp_register_icon_collection')) : ?>
            <?php $native_mode = sfim_native_setting(); ?>
            <?php if ($native_mode === 'on') : ?>
                <?php $native_icon_count = count(sfim_sprite_icons()); ?>
                <p class="description" style="margin-top:.5em">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: Number of symbols registered with the native WordPress icon API. */
                        _n(
                            'WordPress 7.1+ only: this symbol is also registered in the built-in Icon block and the wp/v2 icons REST API.',
                            'WordPress 7.1+ only: these %d symbols are also registered in the built-in Icon block and the wp/v2 icons REST API.',
                            $native_icon_count,
                            'sf-icon-manager',
                        ),
                        $native_icon_count,
                    ));
                ?>
                </p>
            <?php elseif ($native_mode === 'no_block') : ?>
                <p class="description" style="margin-top:.5em">
                    <?php echo esc_html__('WordPress 7.1+ only: the built-in Icon block is disabled. Use the SVG Icon block instead.', 'sf-icon-manager'); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Active sprite file', 'sf-icon-manager'); ?></th>
                    <td>
                        <code><?php echo esc_html($sprite_url); ?></code>
                        <?php if ($short_url !== '') : ?>
                            <p class="description" style="margin-top:.5em">
                                <?php echo esc_html__('Short URL:', 'sf-icon-manager'); ?>
                                <code><?php echo esc_html($short_url); ?></code>
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: Source of the sprite URL (filter, upload, default). */
                                __('Source: %s', 'sf-icon-manager'),
                                $source_label,
                            ));
    ?>
                        </p>
                    </td>
                </tr>
                <?php if ($uploaded !== []) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Uploaded file', 'sf-icon-manager'); ?></th>
                        <td>
                            <p style="margin:0">
                                <?php echo esc_html($uploaded['name']); ?>
                                <span class="description">
                                    <?php
            echo esc_html(sprintf(
                /* translators: %1$d: Number of symbol elements, %2$s: Date of the upload. */
                __('(%1$d symbols, uploaded on %2$s)', 'sf-icon-manager'),
                (int) $uploaded['symbols'],
                wp_date(get_option('date_format'), (int) $uploaded['time']),
            ));
                    ?>
                                </span>
                            </p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:.75em">
                                <?php wp_nonce_field('sfim_delete_sprite'); ?>
                                <input type="hidden" name="action" value="sfim_delete_sprite">
                                <button type="submit" class="button button-secondary">
                                    <?php echo esc_html__('Remove uploaded file', 'sf-icon-manager'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
        <?php endif; ?>
        </tbody>
        </table>

        <?php if (function_exists('wp_register_icon_collection')) : ?>
        <h2 style="margin-bottom:0"><?php echo esc_html__('WordPress native icon integration (experimental)', 'sf-icon-manager'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Only relevant on WordPress 7.1+ which ships the built-in Icon block and the wp/v2 icons REST API. Experimental — the API and its behavior may change with core updates.', 'sf-icon-manager'); ?>
        </p>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('Note: the native path applies the core Icon block’s strict SVG sanitizer, so multi-color icon sets (e.g. Tango) may render incorrectly or not at all. The SVG Icon block renders sprite symbols without restrictions and is not affected.', 'sf-icon-manager'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sfim_update_native'); ?>
            <input type="hidden" name="action" value="sfim_update_native">
            <fieldset>
                <legend class="screen-reader-text"><?php echo esc_html__('WordPress native icon integration (experimental)', 'sf-icon-manager'); ?></legend>
                <?php $native_mode = sfim_native_setting(); ?>
                <ul>
                    <li>
                        <label>
                            <input type="radio" name="sfim_native" value="off" <?php checked('off', $native_mode); ?>>
                            <?php echo esc_html__('Off', 'sf-icon-manager'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Default. The plugin does not touch the WordPress icon API; the built-in Icon block stays as in core.', 'sf-icon-manager'); ?>
                        </p>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="sfim_native" value="no_block" <?php checked('no_block', $native_mode); ?>>
                            <?php echo esc_html__('Off, and hide WordPress core icon block', 'sf-icon-manager'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Like Off, but the built-in core/icon block is deregistered in the block editor and on the frontend. Use the SVG Icon block instead.', 'sf-icon-manager'); ?>
                        </p>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="sfim_native" value="on" <?php checked('on', $native_mode); ?>>
                            <?php echo esc_html__('On', 'sf-icon-manager'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Every symbol of the configured sprite is registered as an icon in the sf-icon-manager collection — available in the built-in Icon block picker, the wp/v2 icons REST API and wp_get_icon().', 'sf-icon-manager'); ?>
                        </p>
                    </li>
                </ul>
            </fieldset>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Save native icon settings', 'sf-icon-manager'); ?>
                </button>
            </p>
        </form>
        <?php endif; ?>

        <h2 style="margin-bottom:0"><?php echo esc_html__('Upload new file', 'sf-icon-manager'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php echo esc_html__('An existing uploaded file is replaced by a new upload.', 'sf-icon-manager'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="sf-icon-manager-upload">
            <?php wp_nonce_field('sfim_upload_sprite'); ?>
            <input type="hidden" name="action" value="sfim_upload_sprite">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('SVG file (ico.svg)', 'sf-icon-manager'); ?></th>
                        <td>
                            <input type="file" name="sfim_sprite" accept=".svg,.svgz,image/svg+xml" required <?php disabled($filter_active); ?>>
                            <p class="description">
                                <?php echo esc_html__('Only .svg and .svgz files are accepted. svgforge-cli handles full sanitization and svgo optimization; as a safety net, the plugin strips scripts, event handlers and javascript: links on upload.', 'sf-icon-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary" <?php disabled($filter_active); ?>>
                    <?php echo esc_html__('Upload SVG sprite', 'sf-icon-manager'); ?>
                </button>
            </p>
        </form>

        <h2 style="margin-bottom:0"><?php echo esc_html__('Generate a sprite with svgforge-cli', 'sf-icon-manager'); ?></h2>
        <p class="description" style="margin-top:.5em">
            <?php
            printf(
                /* translators: %1$s: opening link to the svgforge-cli repository; %2$s: closing link tag. */
                esc_html__('%1$sInstall svgforge-cli%2$s and create a symbol sprite from a folder of icons, then upload the generated SVG file.', 'sf-icon-manager'),
                '<a href="https://github.com/svgforge/svgforge-cli" target="_blank" rel="noopener noreferrer">',
                '</a>',
            );
    ?>
        </p>
        <?php $cli_code = "npm install --global @svgforge/svgforge-cli\nsvgforge --symbol --dest=out 'assets/./**/*.svg'"; ?>
        <pre style="padding: 10px; overflow: auto"><code><?php echo esc_html($cli_code); ?></code></pre>
        <p class="description">
            <?php echo esc_html__('SVG files in subdirectories get IDs like directory--filename — each directory is then available as a filter in the icon picker.', 'sf-icon-manager'); ?>
        </p>
        <p class="description" style="margin-top:.5em">
            <?php
            printf(
                /* translators: %1$s: opening link to the svgforge tutorials; %2$s: closing link tag; %3$s: opening link to the example icon set repository; %4$s: closing link tag. */
                esc_html__('Tutorials: %1$ssvgforge.github.io%2$s · Example icon set with generated sprite: %3$ssvgforge/default-icons%4$s', 'sf-icon-manager'),
                '<a href="https://svgforge.github.io" target="_blank" rel="noopener noreferrer">',
                '</a>',
                '<a href="https://github.com/svgforge/default-icons" target="_blank" rel="noopener noreferrer">',
                '</a>',
            );
    ?>
        </p>
    <?php
}

/**
 * Groups parsed sprite icons by their directory prefix (e.g. 'actions--add_circle' → 'actions').
 *
 * Icons whose ID does not contain the '--' separator form a trailing group with
 * an empty prefix (the group simply has no heading).
 *
 * @param array[] $icons Icons as returned by sfim_sprite_icons().
 * @return array[] List of ['prefix' => string, 'icons' => array[]].
 */
function sfim_sprite_preview_groups(array $icons): array
{
    $grouped   = [];
    $ungrouped = [];

    foreach ($icons as $icon) {
        $id = (string) ($icon['label'] ?? '');

        $prefix = '';

        if ($id !== '' && str_contains($id, '--')) {
            $prefix = substr($id, 0, (int) strpos($id, '--'));
        }

        if ($prefix === '') {
            $ungrouped[$id] = $icon;

            continue;
        }

        $grouped[$prefix][$id] = $icon;
    }

    ksort($grouped);
    ksort($ungrouped);

    $groups = [];

    foreach ($grouped as $prefix => $icons_in_group) {
        ksort($icons_in_group);

        $groups[] = [
            'prefix' => (string) $prefix,
            'icons' => array_values($icons_in_group),
        ];
    }

    if ($ungrouped !== []) {
        $groups[] = [
            'prefix' => '',
            'icons' => array_values($ungrouped),
        ];
    }

    return $groups;
}

/**
 * Renders the sprite preview panel: every symbol of the active sprite as a grid.
 */
function sfim_sprite_preview_panel(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $sprite     = sfim_current_sprite();
    $sprite_url = sfim_sprite_url();
    $symbols    = function_exists('sfim_sprite_symbols') ? sfim_sprite_symbols() : [];

    echo '<style>';
    echo '.sf-icon-manager-sprite__group-heading{margin-top:1.5em}';
    echo '.sf-icon-manager-sprite{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin:0;list-style:none}';
    echo '.sf-icon-manager-sprite li{display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px 8px;background:#fff;border:1px solid #dcdcde;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04)}';
    echo '.sf-icon-manager-sprite__icon{display:flex;align-items:center;justify-content:center;width:64px;height:64px}';
    echo '.sf-icon-manager-sprite__icon svg{width:48px;height:48px;max-width:100%}';
    echo '.sf-icon-manager-sprite__name{font-size:12px;color:#50575e;text-align:center;word-break:break-all}';
    echo '</style>';

    if ($symbols === []) {
        echo '<div class="notice notice-info"><p>';
        if ('none' === $sprite['source']) {
            echo esc_html__('No sprite is configured yet. Go to the settings tab to upload an SVG sprite file.', 'sf-icon-manager');
        } else {
            echo esc_html__('The configured sprite contains no symbols that can be previewed.', 'sf-icon-manager');
        }
        echo '</p></div>';

        return;
    }

    echo '<h2 style="margin-bottom:0">' . esc_html__('Sprite preview', 'sf-icon-manager') . '</h2>';
    echo '<p class="description" style="margin-top:.5em">';
    echo esc_html(sprintf(
        /* translators: %1$d: Number of previewed symbols, %2$s: Active sprite URL. */
        __('%1$d symbols in the active sprite (%2$s).', 'sf-icon-manager'),
        count($symbols),
        $sprite_url,
    ));
    echo '</p>';

    $groups = sfim_sprite_preview_groups(array_map(
        static fn(string $id): array => ['label' => $id],
        $symbols,
    ));

    foreach ($groups as $group) {
        if ($group['prefix'] !== '') {
            echo '<h3 class="sf-icon-manager-sprite__group-heading">' . esc_html($group['prefix']) . '</h3>';
        }

        echo '<ul class="sf-icon-manager-sprite">';

        foreach ($group['icons'] as $icon) {
            $id = (string) $icon['label'];

            echo '<li title="' . esc_attr($id) . '">';
            echo '<div class="sf-icon-manager-sprite__icon">';
            echo '<svg aria-hidden="true" focusable="false"><use href="' . esc_url(rtrim($sprite_url, '#') . '#' . $id) . '"></use></svg>';
            echo '</div>';
            echo '<span class="sf-icon-manager-sprite__name">' . esc_html($id) . '</span>';
            echo '</li>';
        }

        echo '</ul>';
    }
}
