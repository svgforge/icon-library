<?php

/**
 * Shared SVG sprite option helpers (frontend + admin).
 *
 * @package sf-icon-manager
 */
defined('ABSPATH') || exit;

/**
 * Options key for the uploaded SVG sprite file.
 */
const SFIM_SPRITE_OPTION = 'sfim_sprite';

/**
 * Returns the stored data of the uploaded SVG sprite file.
 *
 * @return array{url: string, path: string, name: string, time: int, symbols: int}|array{}
 */
function sfim_uploaded_sprite_data(): array
{
    $data = get_option(SFIM_SPRITE_OPTION, []);

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
function sfim_uploaded_sprite_url(): string
{
    $data = sfim_uploaded_sprite_data();

    return $data['url'] ?? '';
}
