<?php

/**
 * Tests for the WordPress 7.1 native icon API integration.
 *
 * @package sf-icon-manager
 */

/**
 * Tests symbol parsing, caching and the native registration.
 */
final class Test_Icon_Library_Native_Icons extends WP_UnitTestCase
{
    /**
     * Minimal sprite fixture used across the parser tests.
     */
    private function sprite_fixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg">
	<symbol id="home" viewBox="0 0 24 24">
		<path d="M12 3v9h6v9H6V3z" fill="none" stroke="currentColor" stroke-width="2" class="icon" onclick="evil()" />
	</symbol>
	<symbol id="circle-only"><circle cx="5" cy="5" r="4" /></symbol>
	<symbol id="mixed-viewbox" viewbox="0 0 10 10">
		<g><polygon points="1,1 9,1 5,9" fill="#fff" stroke="red" /></g>
	</symbol>
	<symbol><path d="no id here" /></symbol>
	<symbol id="Home"><path d="M0 0h6v6H0z" /></symbol>
	<symbol id="home"><path d="M0 0h6v6H0z" /></symbol>
	<symbol id="line-icon"><line x1="0" y1="0" x2="1" y2="1" /></symbol>
</svg>
XML;
    }

    protected function setUp(): void
    {
        parent::setUp();
        sfim_invalidate_native_icons();
        update_option(SFIM_NATIVE_OPTION, 'on');
        remove_all_filters('sfim_sprite_url');
        remove_all_filters('sfim_register_native_icons');
        $this->reset_icon_registries();
    }

    /**
     * Clears the static icon registries so registrations do not leak
     * from one test into the next (they are process-wide singletons).
     */
    private function reset_icon_registries(): void
    {
        if (class_exists('WP_Icon_Collections_Registry') && class_exists('WP_Icons_Registry')) {
            $collections = WP_Icon_Collections_Registry::get_instance();

            if (function_exists('wp_unregister_icon_collection') && $collections->is_registered('sf-icon-manager')) {
                wp_unregister_icon_collection('sf-icon-manager');
            }

            foreach (WP_Icons_Registry::get_instance()->get_registered_icons() as $icon) {
                $name = $icon['name'] ?? '';

                if (is_string($name) && str_starts_with($name, 'sf-icon-manager/')) {
                    wp_unregister_icon($name);
                }
            }
        }
    }

    public function test_icon_slug_normalizes_symbol_ids(): void
    {
        $this->assertSame('add--circle', sfim_icon_slug('Add--Circle'));
        $this->assertSame('a-b-c-d', sfim_icon_slug('a.b/c d'));
        $this->assertSame('123', sfim_icon_slug('123'));
        $this->assertSame('foo', sfim_icon_slug('--foo--'));
        $this->assertSame('upper', sfim_icon_slug('UPPER'));
        $this->assertSame('', sfim_icon_slug('é'));
        $this->assertSame('', sfim_icon_slug(''));
    }

    public function test_parse_extracts_path_and_polygon_symbols_only(): void
    {
        $icons = sfim_parse_sprite_icons($this->sprite_fixture());

        $names = array_column($icons, 'name');

        $this->assertSame(['sf-icon-manager/home', 'sf-icon-manager/mixed-viewbox'], $names);

        $home = $icons[0];
        $this->assertSame('home', $home['label']);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $home['content']);
        $this->assertStringContainsString('d="M12 3v9h6v9H6V3z"', $home['content']);
        $this->assertStringContainsString('fill="none"', $home['content']);

        foreach ($icons as $icon) {
            $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $icon['content']);
            $this->assertStringEndsWith('</svg>', $icon['content']);
        }
    }

    public function test_parse_prunes_disallowed_shape_attributes(): void
    {
        $icons = sfim_parse_sprite_icons($this->sprite_fixture());

        $home = $icons[0]['content'];
        $this->assertStringNotContainsString('stroke', $home);
        $this->assertStringNotContainsString('class=', $home);
        $this->assertStringNotContainsString('onclick', $home);

        $mixed = $icons[1]['content'];
        $this->assertStringContainsString('points="1,1 9,1 5,9"', $mixed);
        $this->assertStringContainsString('fill="#fff"', $mixed);
        $this->assertStringNotContainsString('stroke', $mixed);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $mixed);
    }

    public function test_parse_deduplicates_colliding_slugs(): void
    {
        $icons = sfim_parse_sprite_icons($this->sprite_fixture());

        $names = array_column($icons, 'name');

        $this->assertCount(1, array_filter($names, static fn($n) => 'sf-icon-manager/home' === $n));

        foreach ($names as $name) {
            $this->assertSame(1, preg_match('/^sf-icon-manager\/[a-z0-9_-]+$/', $name));
        }
    }

    public function test_parse_invalid_or_empty_input_returns_empty(): void
    {
        $this->assertSame([], sfim_parse_sprite_icons(''));
        $this->assertSame([], sfim_parse_sprite_icons('not xml'));
        $this->assertSame([], sfim_parse_sprite_icons(null));
    }

    public function test_sprite_icons_parses_and_caches_the_bundled_sprite(): void
    {
        $icons = sfim_sprite_icons();

        $this->assertNotEmpty($icons);

        foreach ($icons as $icon) {
            $this->assertSame(1, preg_match('/^sf-icon-manager\/[a-z0-9_-]+$/', $icon['name']));
            $this->assertStringStartsWith('<svg', $icon['content']);
            $this->assertStringContainsString('<path', $icon['content']);
        }

        $cached = get_option(SFIM_ICONS_OPTION);
        $this->assertNotFalse($cached);
        $this->assertSame(sfim_sprite_source_signature(), $cached['signature']);
    }

    public function test_sprite_icons_serves_the_cached_list_without_rewriting(): void
    {
        $fixture = ['name' => 'sf-icon-manager/fixture', 'label' => 'fixture', 'content' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1z" /></svg>'];

        update_option(SFIM_ICONS_OPTION, [
            'signature' => sfim_sprite_source_signature(),
            'icons' => [$fixture],
        ], false);

        $icons = sfim_sprite_icons();

        $this->assertSame([$fixture], $icons);
    }

    public function test_sprite_icons_rebuilds_when_the_signature_changes(): void
    {
        update_option(SFIM_ICONS_OPTION, [
            'signature' => 'stale-signature',
            'icons' => [],
        ], false);

        $icons = sfim_sprite_icons();

        $this->assertNotEmpty($icons);

        $cached = get_option(SFIM_ICONS_OPTION);
        $this->assertSame(sfim_sprite_source_signature(), $cached['signature']);
    }

    public function test_invalidate_native_icons_clears_the_cache(): void
    {
        sfim_sprite_icons();
        $this->assertNotFalse(get_option(SFIM_ICONS_OPTION));

        sfim_invalidate_native_icons();
        $this->assertFalse(get_option(SFIM_ICONS_OPTION));
    }

    public function test_registration_is_idempotent_on_wp_71(): void
    {
        if (! function_exists('wp_register_icon') || ! class_exists('WP_Icon_Collections_Registry')) {
            $this->markTestSkipped('WordPress 7.1 icon API not available in this test environment.');
        }

        sfim_register_native_icons();

        $ours = array_values(array_filter(
            WP_Icons_Registry::get_instance()->get_registered_icons(),
            static fn($icon) => str_starts_with((string) ($icon['name'] ?? ''), 'sf-icon-manager/'),
        ));

        $this->assertNotEmpty($ours);

        $name = $ours[0]['name'];
        $rendered = wp_get_icon($name);

        $this->assertStringContainsString('<svg', $rendered);
    }

    public function test_register_twice_is_a_noop_on_wp_71(): void
    {
        if (! function_exists('wp_register_icon') || ! class_exists('WP_Icon_Collections_Registry')) {
            $this->markTestSkipped('WordPress 7.1 icon API not available in this test environment.');
        }

        sfim_register_native_icons();
        sfim_register_native_icons();

        $slugs = array_column(WP_Icon_Collections_Registry::get_instance()->get_all_registered(), 'slug');
        $this->assertEquals(1, count(array_keys($slugs, 'sf-icon-manager', true)));
    }

    public function test_registration_is_lazy(): void
    {
        $this->assertSame(10, has_action('rest_api_init', 'sfim_ensure_native_icons'));
        $this->assertSame(10, has_filter('render_block_data', 'sfim_ensure_on_icon_block'));
        $this->assertSame(PHP_INT_MAX, has_action('init', 'sfim_deregister_native_icon_block'));
        $this->assertFalse(has_action('init', 'sfim_register_native_icons'));
    }

    public function test_ensure_on_icon_block_triggers_registration(): void
    {
        sfim_invalidate_native_icons();

        sfim_ensure_on_icon_block(['blockName' => 'core/icon']);

        $this->assertTrue($GLOBALS['sfim_native_registered'] ?? false);
    }

    public function test_ensure_on_other_blocks_does_not_register(): void
    {
        sfim_invalidate_native_icons();

        sfim_ensure_on_icon_block(['blockName' => 'core/paragraph']);

        $this->assertFalse($GLOBALS['sfim_native_registered'] ?? false);
        $this->assertFalse(get_option(SFIM_ICONS_OPTION));
    }

    public function test_native_setting_defaults_to_off_and_falls_back(): void
    {
        delete_option(SFIM_NATIVE_OPTION);
        $this->assertSame('off', sfim_native_setting());

        update_option(SFIM_NATIVE_OPTION, 'garbage');
        $this->assertSame('off', sfim_native_setting());

        update_option(SFIM_NATIVE_OPTION, 'no_block');
        $this->assertSame('no_block', sfim_native_setting());
    }

    public function test_registration_is_disabled_when_mode_is_off(): void
    {
        delete_option(SFIM_NATIVE_OPTION);
        sfim_invalidate_native_icons();

        sfim_register_native_icons();

        $this->assertFalse($GLOBALS['sfim_native_registered'] ?? false);
        $this->assertFalse(get_option(SFIM_ICONS_OPTION));
    }

    public function test_deregister_hides_block_only_in_no_block_mode(): void
    {
        if (! class_exists('WP_Block_Type_Registry')) {
            $this->markTestSkipped('WordPress block type registry not available in this test environment.');
        }

        $registry = WP_Block_Type_Registry::get_instance();
        $dummy = 'sf-icon-manager/dummy';

        if (! $registry->is_registered($dummy)) {
            $registry->register($dummy, ['title' => 'Dummy']);
        }

        delete_option(SFIM_NATIVE_OPTION);
        sfim_deregister_native_icon_block($dummy);
        $this->assertTrue($registry->is_registered($dummy));

        update_option(SFIM_NATIVE_OPTION, 'no_block');
        sfim_deregister_native_icon_block($dummy);
        $this->assertFalse($registry->is_registered($dummy));

        update_option(SFIM_NATIVE_OPTION, 'on');
        sfim_deregister_native_icon_block($dummy);
        $this->assertFalse($registry->is_registered($dummy));
    }

    public function test_deregister_ignores_non_string_hook_arguments(): void
    {
        if (! class_exists('WP_Block_Type_Registry')) {
            $this->markTestSkipped('WordPress block type registry not available in this test environment.');
        }

        update_option(SFIM_NATIVE_OPTION, 'no_block');

        // rest_api_init passes the WP_REST_Server as the first callback
        // argument; it must not break the deregistration or be treated as
        // a block name.
        sfim_deregister_native_icon_block(new stdClass());
        sfim_deregister_native_icon_block(null);

        $this->assertFalse(WP_Block_Type_Registry::get_instance()->is_registered('core/icon'));
    }

    public function test_editor_unregister_script_enqueued_only_in_no_block_mode(): void
    {
        delete_option(SFIM_NATIVE_OPTION);
        sfim_native_unregister_block_editor_assets();
        $this->assertFalse(wp_script_is('sf-icon-manager-unregister-icon-block', 'registered'));

        update_option(SFIM_NATIVE_OPTION, 'no_block');
        sfim_native_unregister_block_editor_assets();
        $this->assertTrue(wp_script_is('sf-icon-manager-unregister-icon-block', 'registered'));
        $this->assertTrue(wp_script_is('sf-icon-manager-unregister-icon-block', 'enqueued'));

        // The static asset must exist and be served from the plugin.
        $data = wp_scripts()->registered['sf-icon-manager-unregister-icon-block'];
        $this->assertFileExists(dirname(SFIM_PLUGIN_FILE) . '/assets/js/unregister-icon-block.js');
        $this->assertSame(['wp-dom-ready', 'wp-blocks'], $data->deps);
    }

    public function test_deny_removes_icon_block_from_allowlist_only_in_no_block_mode(): void
    {
        delete_option(SFIM_NATIVE_OPTION);
        $this->assertSame(['core/icon', 'core/paragraph'], sfim_deny_native_icon_block_types(['core/icon', 'core/paragraph']));
        $this->assertTrue(sfim_deny_native_icon_block_types(true));
        $this->assertFalse(sfim_deny_native_icon_block_types(false));

        update_option(SFIM_NATIVE_OPTION, 'no_block');
        $this->assertSame(['core/paragraph'], sfim_deny_native_icon_block_types(['core/icon', 'core/paragraph']));
        $this->assertSame(['core/paragraph'], sfim_deny_native_icon_block_types(['core/paragraph']));
        $this->assertFalse(sfim_deny_native_icon_block_types(false));
        $this->assertIsArray(sfim_deny_native_icon_block_types(true));
        $this->assertNotContains('core/icon', sfim_deny_native_icon_block_types(true));
    }

    public function test_render_strip_removes_core_icon_output_only_in_no_block_mode(): void
    {
        $content = '<svg class="icon"><use href="#home"/></svg>';
        $block = ['blockName' => 'core/icon'];

        delete_option(SFIM_NATIVE_OPTION);
        $this->assertSame($content, sfim_strip_native_icon_block($content, $block));

        update_option(SFIM_NATIVE_OPTION, 'on');
        $this->assertSame($content, sfim_strip_native_icon_block($content, $block));

        update_option(SFIM_NATIVE_OPTION, 'no_block');
        $this->assertSame('', sfim_strip_native_icon_block($content, $block));

        $other = ['blockName' => 'core/paragraph'];
        $this->assertSame($content, sfim_strip_native_icon_block($content, $other));
    }
}
