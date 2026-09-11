<?php

/**
 * Tests for the server-side block rendering (src/block/render.php).
 *
 * @package icon-library
 */

/**
 * Tests for the Icon Library "SVG Fragment" block render callback.
 */
final class Test_Icon_Library_Block_Render extends WP_UnitTestCase
{
    public function test_block_is_registered(): void
    {
        $this->assertInstanceOf('WP_Block_Type', $block = WP_Block_Type_Registry::get_instance()->get_registered('icon-library/svg-fragment'));
        $this->assertNotNull($block->render_callback);
    }

    /**
     * Renders the block via render_block() with a parsed-block array.
     */
    private function render_block_html(array $attrs): string
    {
        return render_block([
            'blockName' => 'icon-library/svg-fragment',
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

    public function test_renders_unlinked_icon_with_svg_label(): void
    {
        $html = $this->render_block_html([
            'symbolId' => 'home',
            'label' => 'Startseite',
        ]);

        $this->assertStringContainsString('<span ', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringContainsString('aria-label="Startseite"', $html);
        $this->assertStringNotContainsString('aria-hidden="true"', $html);
    }

    public function test_renders_placeholder_for_missing_symbol(): void
    {
        $html = $this->render_block_html([]);

        $this->assertStringContainsString('svg-fragment__placeholder', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }
}
