<?php

/**
 * Shared SVG sprite option helpers (frontend + admin).
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
function icon_library_uploaded_sprite_data(): array
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
function icon_library_uploaded_sprite_url(): string
{
    $data = icon_library_uploaded_sprite_data();

    return $data['url'] ?? '';
}
