<?php

/**
 * Tests for the SVG content sanitizer.
 *
 * @package sf-icon-manager
 */

/**
 * Tests for sfim_sanitize_svg().
 */
final class Test_Icon_Library_Sanitize_Svg extends WP_UnitTestCase
{
    public function test_empty_input_is_rejected(): void
    {
        $this->assertSame('', sfim_sanitize_svg(''));
        $this->assertSame('', sfim_sanitize_svg(" \n\t "));
    }

    public function test_input_without_svg_element_is_rejected(): void
    {
        $this->assertSame('', sfim_sanitize_svg('<p>no svg here</p>'));
    }

    public function test_script_block_is_removed(): void
    {
        $svg = '<svg><script>alert(1)</script><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_self_closing_script_is_removed(): void
    {
        $svg = '<svg><script src="https://evil.example/x.js"/><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_event_handler_attributes_are_removed(): void
    {
        $svg = '<svg><symbol id="a" onclick="alert(1)" onload="evil()"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringContainsString('id="a"', $clean);
    }

    public function test_javascript_links_are_removed(): void
    {
        $svg = '<svg><symbol id="a" href="javascript:alert(1)"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_foreign_object_is_removed(): void
    {
        $svg = '<svg><foreignObject><div>html</div></foreignObject><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_clean_svg_is_kept(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="home" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></symbol></svg>';

        $this->assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="home" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"></path></symbol></svg>',
            sfim_sanitize_svg($svg),
        );
    }

    public function test_entity_encoded_javascript_href_is_neutralized(): void
    {
        $svg = '<svg><symbol id="a" href="java&#x73;cript:alert(1)"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('href', $clean);
        $this->assertStringContainsString('id="a"', $clean);
    }

    public function test_entity_encoded_attribute_name_is_rejected(): void
    {
        $svg = '<svg><symbol id="a" oncl&#x69;ck="alert(1)"></symbol></svg>';

        $this->assertSame('', sfim_sanitize_svg($svg));
    }

    public function test_dtd_defaulted_event_attribute_is_neutralized(): void
    {
        $svg = '<!DOCTYPE svg [<!ATTLIST svg onload CDATA "alert(1)">]><svg><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('ATTLIST', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_external_use_reference_is_rejected(): void
    {
        $svg = '<svg><use href="https://evil.example/x.svg#a"/><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('https://evil', $clean);
        $this->assertStringNotContainsString('<use', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_data_url_href_is_rejected(): void
    {
        $svg = '<svg><symbol id="a" href="data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_php_processing_instructions_are_removed(): void
    {
        $svg = '<svg><?php echo "x"; ?><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('php', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }

    public function test_unknown_elements_are_removed_and_safe_attributes_kept(): void
    {
        $svg = '<svg><foreignObject/><iframe/><symbol id="a" style="color:red" data-x="1" href="#safe"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('id="a"', $clean);
        $this->assertStringContainsString('style="color:red"', $clean);
        $this->assertStringContainsString('data-x="1"', $clean);
        $this->assertStringContainsString('href="#safe"', $clean);
    }

    public function test_xml_prolog_is_tolerated(): void
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg><symbol id="a"></symbol></svg>';
        $clean = sfim_sanitize_svg($svg);

        $this->assertStringNotContainsString('<?xml', $clean);
        $this->assertStringContainsString('<symbol id="a"', $clean);
    }
}
