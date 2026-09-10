<?php

/**
 * Tests for the sprite URL resolution and the settings helpers.
 *
 * @package wp-iconizer
 */

/**
 * Tests for wp_iconizer_sprite_url().
 *
 * Source order: upload → filter → plugin sprite.svg fallback.
 */
final class Test_WP_Iconizer_Sprite_Url extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option(WP_ICONIZER_SPRITE_OPTION);
        remove_all_filters('wp_iconizer_sprite_url');
    }

    public function test_empty_uploaded_sprite_data_returns_empty(): void
    {
        $this->assertSame([], wp_iconizer_uploaded_sprite_data());
        $this->assertSame('', wp_iconizer_uploaded_sprite_url());
    }

    public function test_fallback_is_the_plugins_bundled_sprite(): void
    {
        $url = wp_iconizer_sprite_url();

        $this->assertStringContainsString('wp-iconizer/sprite.svg', $url);
    }

    public function test_filter_short_circuits_the_fallback(): void
    {
        add_filter('wp_iconizer_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        $this->assertSame('https://cdn.example.net/icons.svg', wp_iconizer_sprite_url());
    }

    public function test_upload_wins_over_the_filter(): void
    {
        add_filter('wp_iconizer_sprite_url', static fn() => 'https://cdn.example.net/icons.svg');

        update_option(WP_ICONIZER_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/wp-iconizer/ico.svg',
            'path' => '/tmp/ico.svg',
            'name' => 'ico.svg',
            'time' => 123,
        ]);

        $this->assertSame(
            'https://example.test/app/uploads/wp-iconizer/ico.svg?m=123',
            wp_iconizer_sprite_url(),
        );
    }

    public function test_upload_without_timestamp_gets_no_cache_buster(): void
    {
        update_option(WP_ICONIZER_SPRITE_OPTION, [
            'url' => 'https://example.test/app/uploads/wp-iconizer/ico.svg',
            'path' => '/tmp/ico.svg',
        ]);

        $this->assertSame(
            'https://example.test/app/uploads/wp-iconizer/ico.svg',
            wp_iconizer_sprite_url(),
        );
    }
}
