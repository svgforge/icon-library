<?php

/**
 * Dynamic frontend render for the SVG Icon block.
 *
 * @var array $attributes Block attributes.
 * @var string $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

if (! defined('ABSPATH')) {
    exit;
}

// PHPCS: render.php is a template executed inside the block's render callback;
// the plain variable names are part of this template scope, not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$symbol_id = isset($attributes['symbolId']) ? sanitize_key($attributes['symbolId']) : '';
$url = isset($attributes['url']) ? esc_url($attributes['url']) : '';
$label = isset($attributes['label']) ? $attributes['label'] : '';
$rel = isset($attributes['rel']) ? $attributes['rel'] : '';
$width = '';
$dimensions_styles = isset($attributes['style']['dimensions']) && is_array($attributes['style']['dimensions'])
    ? $attributes['style']['dimensions']
    : [];

if (isset($dimensions_styles['width']) && $dimensions_styles['width'] !== '') {
    $width = sfim_resolve_dimension(sanitize_text_field((string) $dimensions_styles['width']));
}

// Legacy fallback for blocks saved before the standard Dimensions support:
// width/height were stored as block attributes.
if ($width === '') {
    $width = isset($attributes['width']) ? sanitize_text_field((string) $attributes['width']) : '';
}
if ($width === '') {
    $width = '48px';
}

$height = isset($attributes['height']) ? sanitize_text_field((string) $attributes['height']) : '';
if ($height === '') {
    // The icon is square by default, like the core Icon block.
    $height = $width;
}
$opens_in_new_tab = ! empty($attributes['opensInNewTab']);

if ($symbol_id === '') {
    echo '<div class="svg-icon__placeholder">' . esc_html__('Select symbol …', 'sf-icon-manager') . '</div>';
    return '';
}

// Sprite URL: filter, upload, or fallback.
$sprite_base = function_exists('sfim_sprite_url')
    ? sfim_sprite_url()
    : plugins_url('sprite.svg', dirname(__DIR__, 2) . '/sf-icon-manager.php');

if (strpos($sprite_base, '#') === false) {
    $svg_href = esc_url(rtrim($sprite_base, '#') . '#' . $symbol_id);
} else {
    $svg_href = esc_url($sprite_base);
}

$style = '';
$style .= 'width:' . esc_attr($width) . ';height:' . esc_attr($height) . ';';

$svg_classes = ['svg-icon__svg'];

$color = isset($attributes['style']['color']) && is_array($attributes['style']['color'])
    ? $attributes['style']['color']
    : [];

$text_color = isset($color['text'])
    ? (string) $color['text']
    : (isset($attributes['textColor']) ? (string) $attributes['textColor'] : '');

$background_color = isset($color['background'])
    ? (string) $color['background']
    : (isset($attributes['backgroundColor']) ? (string) $attributes['backgroundColor'] : '');

if ($text_color !== '') {
    $style .= 'color:' . esc_attr(sfim_resolve_color($text_color)) . ';';
    $svg_classes[] = 'has-text-color';
}

if ($background_color !== '') {
    $style .= 'background-color:' . esc_attr(sfim_resolve_color($background_color)) . ';';
    $svg_classes[] = 'has-background';
}

// Spacing padding is applied to the SVG itself (like the core Icon block), so
// the background covers the padded area; the block wrapper never gets padding.
$padding = isset($attributes['style']['spacing']['padding']) && is_array($attributes['style']['spacing']['padding'])
    ? $attributes['style']['spacing']['padding']
    : [];

foreach (['top', 'right', 'bottom', 'left'] as $side) {
    if (isset($padding[$side]) && $padding[$side] !== '') {
        $style .= 'padding-' . $side . ':' . esc_attr(sfim_resolve_spacing((string) $padding[$side])) . ';';
    }
}

$style_attr = $style !== '' ? ' style="' . $style . '"' : '';
$svg_class_attr = ' class="' . esc_attr(implode(' ', $svg_classes)) . '"';

// Accessibility: linked → SVG hidden, aria-label on the link.
// Otherwise aria-label on the SVG, otherwise aria-hidden.
$svg_aria = $url !== ''
    ? ' aria-hidden="true"'
    : ($label !== '' ? ' aria-label="' . esc_attr($label) . '"' : ' aria-hidden="true"');

$svg = '<svg' . $svg_aria . ' focusable="false"' . $svg_class_attr . $style_attr . '><use href="' . $svg_href . '"></use></svg>';

if ($url !== '') {
    $aria = $label !== '' ? ' aria-label="' . esc_attr($label) . '"' : '';
    $computed_rel = $rel;
    if ($opens_in_new_tab) {
        $parts = preg_split('/\s+/', (string) $computed_rel, -1, PREG_SPLIT_NO_EMPTY);
        $parts[] = 'noopener';
        $parts[] = 'noreferrer';
        $computed_rel = implode(' ', array_unique($parts));
    }
    $rel_attr = trim((string) $computed_rel) !== '' ? ' rel="' . esc_attr(trim((string) $computed_rel)) . '"' : '';
    $target = $opens_in_new_tab ? ' target="_blank"' : '';
    $wrapper = get_block_wrapper_attributes(['class' => 'svg-icon']);

    echo wp_kses(
        '<a ' . $wrapper . ' href="' . $url . '"' . $target . $rel_attr . $aria . '>' . $svg . '</a>',
        sfim_allowed_svg_kses(),
    );
    return '';
}

$wrapper = get_block_wrapper_attributes(['class' => 'svg-icon']);

echo wp_kses(
    '<div ' . $wrapper . '>' . $svg . '</div>',
    sfim_allowed_svg_kses(),
);
return '';
