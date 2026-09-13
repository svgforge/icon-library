<?php

/**
 * Tests for the server-side block rendering (src/block/render.php).
 *
 * @package icon-library
 */

/**
 * Tests for the Icon Library "SVG Icon" block render callback.
 */
final class Test_Icon_Library_Block_Render extends WP_UnitTestCase
{
    public function test_block_is_registered(): void
    {
        $this->assertInstanceOf('WP_Block_Type', $block = WP_Block_Type_Registry::get_instance()->get_registered('icon-library/svg-icon'));
        $this->assertNotNull($block->render_callback);
    }

    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('icon_library_sprite_url');
    }

    /**
     * Renders the block via render_block() with a parsed-block array.
     */
    private function render_block_html(array $attrs): string
    {
        return render_block([
            'blockName' => 'icon-library/svg-icon',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ]);
    }

    public function test_renders_linked_icon_with_accessibility_and_rel(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'person',
            'url' => 'https://example.test/about',
            'label' => 'Über uns',
            'opensInNewTab' => true,
            'rel' => 'nofollow',
            'width' => '24',
            'height' => '24',
        ]);

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href="https://example.test/about"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
        $this->assertStringContainsString('aria-label="Über uns"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('sprite.svg#person', $html);
        $this->assertStringContainsString('<use href=', $html);
    }

    public function test_does_not_duplicate_noopener_noreferrer_in_rel(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'person',
            'url' => 'https://example.test/about',
            'opensInNewTab' => true,
            'rel' => 'noopener nofollow noreferrer',
        ]);

        $this->assertStringContainsString('rel="noopener nofollow noreferrer"', $html);
        $this->assertStringNotContainsString('noopener noopener', $html);
        $this->assertStringNotContainsString('noreferrer noreferrer', $html);
    }

    public function test_uses_filtered_sprite_url_without_fragment(): void
    {
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        $html = $this->render_block_html(['symbolId' => 'home']);

        $this->assertStringContainsString('https://cdn.example.net/icons.svg#home', $html);
    }

    public function test_uses_filtered_sprite_url_with_fragment_as_is(): void
    {
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.net/icons.svg#brand');

        $html = $this->render_block_html(['symbolId' => 'home']);

        $this->assertStringContainsString('<use href="https://cdn.example.net/icons.svg#brand">', $html);
    }

    public function test_renders_unlinked_icon_with_svg_label(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'label' => 'Startseite',
        ]);

        $this->assertStringContainsString('<div ', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringContainsString('aria-label="Startseite"', $html);
        $this->assertStringNotContainsString('aria-hidden="true"', $html);
    }

    public function test_renders_placeholder_for_missing_symbol(): void
    {
        $html = $this->render_block_html([]);

        $this->assertStringContainsString('svg-icon__placeholder', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_applies_colors_to_the_svg_not_the_wrapper(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'width' => '24',
            'height' => '24',
            'style' => [
                'color' => [
                    'text' => '#bada55',
                    'background' => '#123456',
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'class="svg-icon__svg has-text-color has-background" style="width:24;height:24;color:#bada55;background-color:#123456"',
            $html,
        );
        $this->assertStringNotContainsString('has-text-color', $this->wrapper_classes($html));
        $this->assertStringNotContainsString('#bada55', $this->wrapper_classes($html));
    }

    public function test_applies_preset_colors_to_the_svg(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'width' => '24',
            'height' => '24',
            'textColor' => 'vivid-red',
            'backgroundColor' => 'vivid-purple',
        ]);

        $this->assertStringContainsString(
            'class="svg-icon__svg has-text-color has-background" style="width:24;height:24;color:var(--wp--preset--color--vivid-red);background-color:var(--wp--preset--color--vivid-purple)"',
            $html,
        );
        $this->assertStringNotContainsString('has-text-color', $this->wrapper_classes($html));
    }

    public function test_applies_custom_dimension_width_to_the_svg(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'style' => [
                'dimensions' => [
                    'width' => '42px',
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'class="svg-icon__svg" style="width:42px;height:42px"',
            $html,
        );
        $this->assertStringNotContainsString('width:42px', $this->wrapper_classes($html));
    }

    public function test_applies_padding_to_the_svg_not_the_wrapper(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'width' => '24',
            'height' => '24',
            'style' => [
                'spacing' => [
                    'padding' => [
                        'top' => 'var:preset|spacing|30',
                        'right' => '4px',
                        'bottom' => 'var:preset|spacing|30',
                        'left' => '4px',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'class="svg-icon__svg" style="width:24;height:24;padding-top:var(--wp--preset--spacing--30);padding-right:4px;padding-bottom:var(--wp--preset--spacing--30);padding-left:4px"',
            $html,
        );
        $this->assertStringNotContainsString('padding-top', $this->wrapper_classes($html));
    }

    public function test_skips_empty_padding_sides(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'style' => [
                'spacing' => [
                    'padding' => [
                        'top' => '20px',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'style="width:48px;height:48px;padding-top:20px"',
            $html,
        );
        $this->assertStringNotContainsString('padding-right', $html);
        $this->assertStringNotContainsString('padding-bottom', $html);
        $this->assertStringNotContainsString('padding-left', $html);
    }

    public function test_falls_back_to_legacy_width_height_attributes(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'width' => '64px',
            'height' => '32px',
        ]);

        $this->assertStringContainsString(
            'style="width:64px;height:32px"',
            $html,
        );
    }

    public function test_defaults_to_the_square_fallback_size(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
        ]);

        $this->assertStringContainsString(
            'style="width:48px;height:48px"',
            $html,
        );
    }

    private function wrapper_classes(string $html): string
    {
        preg_match('#^<div class="([^"]+)"#', $html, $match);

        return $match[1] ?? '';
    }
}
