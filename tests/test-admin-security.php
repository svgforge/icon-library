<?php

/**
 * Tests for the admin handler security (sprite upload and delete).
 *
 * @package icon-library
 */

/**
 * Verifies the WordPress-style guards of the admin_post handlers: the
 * manage_options capability check, the referer nonce and the upload block
 * when the sprite URL is overridden by a filter.
 */
final class Test_Icon_Library_Admin_Security extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('icon_library_sprite_url');
        remove_all_filters('wp_redirect');
        unset($_REQUEST['_wpnonce'], $_POST['_wpnonce'], $_GET['_wpnonce']);
    }

    private function create_user(string $role): int
    {
        return $this->factory()->user->create(['role' => $role]);
    }

    private function throw_on_redirect(): void
    {
        add_filter('wp_redirect', static function ($location) {
            throw new WPDieException((string) $location);
        });
    }

    public function test_upload_requires_admin_capability(): void
    {
        wp_set_current_user($this->create_user('subscriber'));

        $this->expectException(WPDieException::class);
        icon_library_handle_sprite_upload();
    }

    public function test_delete_requires_admin_capability(): void
    {
        wp_set_current_user($this->create_user('subscriber'));

        $this->expectException(WPDieException::class);
        icon_library_handle_sprite_delete();
    }

    public function test_upload_requires_valid_nonce(): void
    {
        wp_set_current_user($this->create_user('administrator'));

        $this->expectException(WPDieException::class);
        icon_library_handle_sprite_upload();
    }

    public function test_delete_requires_valid_nonce(): void
    {
        wp_set_current_user($this->create_user('administrator'));

        $this->expectException(WPDieException::class);
        icon_library_handle_sprite_delete();
    }

    public function test_upload_is_rejected_when_filter_active(): void
    {
        $admin = $this->create_user('administrator');
        wp_set_current_user($admin);
        $_REQUEST['_wpnonce'] = wp_create_nonce('icon_library_upload_sprite');
        add_filter('icon_library_sprite_url', static fn() => 'https://cdn.example.com/sprite.svg');
        $this->throw_on_redirect();

        try {
            icon_library_handle_sprite_upload();
            $this->fail('Expected a redirect to error_filter_active.');
        } catch (WPDieException $e) {
            $this->assertStringContainsString('error_filter_active', $e->getMessage());
        }
    }

    public function test_delete_is_allowed_with_valid_nonce(): void
    {
        $admin = $this->create_user('administrator');
        wp_set_current_user($admin);
        $_REQUEST['_wpnonce'] = wp_create_nonce('icon_library_delete_sprite');
        $this->throw_on_redirect();

        try {
            icon_library_handle_sprite_delete();
            $this->fail('Expected a redirect to deleted.');
        } catch (WPDieException $e) {
            $this->assertStringContainsString('icon_library_message=deleted', $e->getMessage());
        }
    }
}
