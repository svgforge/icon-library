<?php

/**
 * Tests for the sprite URL resolution and the settings helpers.
 *
 * @package icon-library
 */

/**
 * Tests for icon_library_sprite_url().
 *
 * Source order: filter → upload → plugin sprite.svg fallback.
 */
final class Test_Icon_Library_Sprite_Url extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option(ICON_LIBRARY_SPRITE_OPTION);
        remove_all_filters('icon_library_sprite_url');
    }

    public function test_empty_uploaded_sprite_data_returns_empty(): void
    {
        $this->assertSame([], icon_library_uploaded_sprite_data());
        $this->assertSame('', icon_library_uploaded_sprite_url());
    }

    public function test_fallback_is_the_plugins_bundled_sprite(): void
    {
        $url = icon_library_sprite_url();

        $this->assertStringContainsString('wp-content/plugins', $url);
        $this->assertStringEndsWith('sprite.svg', $url);
    }

    public function test_filter_short_circuits_the_fallback(): void
    {
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        $this->assertSame('https://cdn.example.net/icons.svg', icon_library_sprite_url());
    }

    public function test_filter_overrides_the_upload(): void
    {
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/icon-library/ico.svg',
            'path' => '/tmp/ico.svg',
            'name' => 'ico.svg',
            'time' => 123,
        ]);

        $this->assertSame('https://cdn.example.net/icons.svg', icon_library_sprite_url());
    }

    public function test_upload_used_when_no_filter(): void
    {
        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/icon-library/ico.svg',
            'path' => '/tmp/ico.svg',
            'name' => 'ico.svg',
            'time' => 123,
        ]);

        $this->assertSame(
            'https://example.test/app/uploads/icon-library/ico.svg?m=123',
            icon_library_sprite_url(),
        );
    }

    public function test_upload_without_timestamp_gets_no_cache_buster(): void
    {
        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/icon-library/ico.svg',
            'path' => '/tmp/ico.svg',
        ]);

        $this->assertSame(
            'https://example.test/app/uploads/icon-library/ico.svg',
            icon_library_sprite_url(),
        );
    }

    private function bundled_path(): string
    {
        return dirname(ICON_LIBRARY_PLUGIN_FILE) . '/sprite.svg';
    }

    public function test_current_sprite_falls_back_to_the_bundled_sprite(): void
    {
        $sprite = icon_library_current_sprite();

        $this->assertSame('default', $sprite['source']);
        $this->assertSame([], $sprite['data']);
        $this->assertSame($this->bundled_path(), $sprite['path']);
        $this->assertStringContainsString('wp-content/plugins', $sprite['url']);
    }

    public function test_current_sprite_uses_the_filter(): void
    {
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        $sprite = icon_library_current_sprite();

        $this->assertSame('filter', $sprite['source']);
        $this->assertSame('https://cdn.example.net/icons.svg', $sprite['url']);
        $this->assertSame('', $sprite['path']);
        $this->assertSame([], $sprite['data']);
    }

    public function test_current_sprite_uses_the_uploaded_file(): void
    {
        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/icon-library/ico.svg',
            'path' => $this->bundled_path(),
            'name' => 'ico.svg',
            'time' => 456,
        ]);

        $sprite = icon_library_current_sprite();

        $this->assertSame('upload', $sprite['source']);
        $this->assertSame('https://example.test/app/uploads/icon-library/ico.svg?m=456', $sprite['url']);
        $this->assertSame($this->bundled_path(), $sprite['path']);
        $this->assertSame(456, $sprite['data']['time']);
    }

    public function test_current_sprite_does_not_report_an_unreadable_upload_path(): void
    {
        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/icon-library/ico.svg',
            'path' => '/var/empty/nonexistent/ico.svg',
            'name' => 'ico.svg',
            'time' => 456,
        ]);

        $sprite = icon_library_current_sprite();

        $this->assertSame('upload', $sprite['source']);
        $this->assertSame('', $sprite['path']);
    }
}
