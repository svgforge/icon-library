<?php

/**
 * Tests for the SVG content sanitizer.
 *
 * @package wp-iconizer
 */

/**
 * Tests for wp_iconizer_sanitize_svg().
 */
final class Test_WP_Iconizer_Sanitize_Svg extends WP_UnitTestCase
{
    public function test_empty_input_is_rejected(): void
    {
        $this->assertSame('', wp_iconizer_sanitize_svg(''));
        $this->assertSame('', wp_iconizer_sanitize_svg(" \n\t "));
    }

    public function test_input_without_svg_element_is_rejected(): void
    {
        $this->assertSame('', wp_iconizer_sanitize_svg('<p>no svg here</p>'));
    }

    public function test_script_block_is_removed(): void
    {
        $svg = '<svg><script>alert(1)</script><symbol id="a"></symbol></svg>';
        $clean = wp_iconizer_sanitize_svg($svg);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<symbol id="a"></symbol>', $clean);
    }

    public function test_self_closing_script_is_removed(): void
    {
        $svg = '<svg><script src="https://evil.example/x.js"/><symbol id="a"></symbol></svg>';
        $clean = wp_iconizer_sanitize_svg($svg);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<symbol id="a"></symbol>', $clean);
    }

    public function test_event_handler_attributes_are_removed(): void
    {
        $svg = '<svg><symbol id="a" onclick="alert(1)" onload="evil()"></symbol></svg>';
        $clean = wp_iconizer_sanitize_svg($svg);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringContainsString('id="a"', $clean);
    }

    public function test_javascript_links_are_removed(): void
    {
        $svg = '<svg><symbol id="a" href="javascript:alert(1)"></symbol></svg>';
        $clean = wp_iconizer_sanitize_svg($svg);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_foreign_object_is_removed(): void
    {
        $svg = '<svg><foreignObject><div>html</div></foreignObject><symbol id="a"></symbol></svg>';
        $clean = wp_iconizer_sanitize_svg($svg);

        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringContainsString('<symbol id="a"></symbol>', $clean);
    }

    public function test_clean_svg_is_kept(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="home" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></symbol></svg>';

        $this->assertSame($svg, wp_iconizer_sanitize_svg($svg));
    }
}
