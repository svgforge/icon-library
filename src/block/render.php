<?php

/**
 * Dynamic frontend render for the SVG Fragment block.
 *
 * @var array $attributes Block attributes.
 * @var string $content    Block default content.
 * @var WP_Block $block      Block instance.
 */
$symbol_id = isset($attributes['symbolId']) ? sanitize_key($attributes['symbolId']) : '';
$url = isset($attributes['url']) ? esc_url($attributes['url']) : '';
$label = isset($attributes['label']) ? $attributes['label'] : '';
$rel = isset($attributes['rel']) ? $attributes['rel'] : '';
$fill = isset($attributes['fillColor']) ? sanitize_text_field((string) $attributes['fillColor']) : '';
$stroke = isset($attributes['strokeColor']) ? sanitize_text_field((string) $attributes['strokeColor']) : '';
$width = isset($attributes['width']) ? sanitize_text_field((string) $attributes['width']) : '';
$height = isset($attributes['height']) ? sanitize_text_field((string) $attributes['height']) : '';
$opens_in_new_tab = ! empty($attributes['opensInNewTab']);

if ($symbol_id === '') {
    echo '<div class="svg-fragment__placeholder">' . esc_html__('Select symbol …', 'wp-iconizer') . '</div>';
    return '';
}

// Sprite URL: constant, filter, or fallback.
$sprite_base = function_exists('wp_iconizer_sprite_url') ? wp_iconizer_sprite_url() : '/ico.svg';

if (strpos($sprite_base, '#') === false) {
    $svg_href = esc_url(rtrim($sprite_base, '#') . '#' . $symbol_id);
} else {
    $svg_href = esc_url($sprite_base);
}

$style = '';
if ($fill !== '') {
    $style .= 'fill:' . esc_attr($fill) . ';';
}
if ($stroke !== '') {
    $style .= 'stroke:' . esc_attr($stroke) . ';';
}
$style .= 'width:' . esc_attr($width) . ';height:' . esc_attr($height) . ';';
$style_attr = $style !== '' ? ' style="' . $style . '"' : '';

// Accessibility: linked → SVG hidden, aria-label on the link.
// Otherwise aria-label on the SVG, otherwise aria-hidden.
$svg_aria = $url !== ''
    ? ' aria-hidden="true"'
    : ($label !== '' ? ' aria-label="' . esc_attr($label) . '"' : ' aria-hidden="true"');

$svg = '<svg' . $svg_aria . ' focusable="false" class="svg-fragment__svg"' . $style_attr . '><use href="' . $svg_href . '"></use></svg>';

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
    $wrapper = get_block_wrapper_attributes(['class' => 'svg-fragment']);

    echo '<a ' . $wrapper . ' href="' . $url . '"' . $target . $rel_attr . $aria . '>' . $svg . '</a>';
    return '';
}

$wrapper = get_block_wrapper_attributes(['class' => 'svg-fragment']);

echo '<span ' . $wrapper . '>' . $svg . '</span>';
return '';
