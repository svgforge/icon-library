<?php

/**
 * Tests for the block attribute resolvers (sfim_resolve_color() and
 * sfim_resolve_dimension()).
 *
 * @package sf-icon-manager
 */

/**
 * Tests for the color and dimension value resolution helpers.
 */
final class Test_Icon_Library_Attribute_Resolvers extends WP_UnitTestCase
{
    public function test_resolve_color_maps_slug_to_preset_var(): void
    {
        $this->assertSame(
            'var(--wp--preset--color--vivid-red)',
            sfim_resolve_color('vivid-red'),
        );
    }

    public function test_resolve_color_maps_preset_reference_to_preset_var(): void
    {
        $this->assertSame(
            'var(--wp--preset--color--vivid-red)',
            sfim_resolve_color('var:preset|color|vivid-red'),
        );
    }

    public function test_resolve_color_passes_raw_css_through(): void
    {
        $this->assertSame('#bada55', sfim_resolve_color('#bada55'));
        $this->assertSame('rgb(255 0 0)', sfim_resolve_color('rgb(255 0 0)'));
        $this->assertSame('var(--wp--preset--color--x)', sfim_resolve_color('var(--wp--preset--color--x)'));
    }

    public function test_resolve_dimension_passes_raw_length_through(): void
    {
        $this->assertSame('42px', sfim_resolve_dimension('42px'));
        $this->assertSame('2em', sfim_resolve_dimension('2em'));
        $this->assertSame('', sfim_resolve_dimension(''));
    }

    public function test_resolve_dimension_resolves_preset_slug_with_injected_presets(): void
    {
        $presets = ['theme' => [
            ['name' => 'S', 'slug' => 's', 'size' => '24px'],
            ['name' => 'XL', 'slug' => 'xl', 'size' => '128px'],
        ]];

        $this->assertSame('128px', sfim_resolve_dimension('var:preset|dimension|xl', $presets));
        $this->assertSame('24px', sfim_resolve_dimension('var:preset|dimension|s', $presets));
    }

    public function test_resolve_dimension_uses_first_entry_of_an_array_size(): void
    {
        $presets = ['theme' => [
            ['slug' => 'xy', 'size' => ['64px', '32px']],
        ]];

        $this->assertSame('64px', sfim_resolve_dimension('var:preset|dimension|xy', $presets));
    }

    public function test_resolve_dimension_returns_empty_for_unknown_slug(): void
    {
        $presets = ['theme' => [
            ['slug' => 's', 'size' => '24px'],
        ]];

        $this->assertSame('', sfim_resolve_dimension('var:preset|dimension|z', $presets));
    }

    public function test_resolve_dimension_ignores_non_array_entries(): void
    {
        $presets = ['theme' => 'junk', 'custom' => null];

        $this->assertSame('', sfim_resolve_dimension('var:preset|dimension|s', $presets));
    }

    public function test_resolve_spacing_maps_preset_reference_to_preset_var(): void
    {
        $this->assertSame(
            'var(--wp--preset--spacing--30)',
            sfim_resolve_spacing('var:preset|spacing|30'),
        );
    }

    public function test_resolve_spacing_passes_raw_css_through(): void
    {
        $this->assertSame('4px', sfim_resolve_spacing('4px'));
        $this->assertSame('var(--wp--preset--spacing--10)', sfim_resolve_spacing('var(--wp--preset--spacing--10)'));
        $this->assertSame('', sfim_resolve_spacing(''));
    }
}
