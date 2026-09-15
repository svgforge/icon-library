<?php

/**
 * Tests for the short-URL feature (/i.svg rewrite).
 *
 * @package sf-icon-manager
 */

/**
 * Tests for the rewrite rule, query variable and request interception.
 */
final class Test_Icon_Library_Short_Url extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('sfim_short_url');
    }

    public function test_rewrite_rule_is_registered_when_enabled(): void
    {
        global $wp_rewrite;

        $wp_rewrite->extra_rules_top = [];

        add_filter('sfim_short_url', '__return_true');
        sfim_short_url_init();

        $this->assertSame('index.php?sfim_svg=1', $wp_rewrite->extra_rules_top['^i\.svg/?$'] ?? null);
    }

    public function test_rewrite_rule_is_dropped_when_disabled(): void
    {
        global $wp_rewrite;

        $wp_rewrite->extra_rules_top = [];

        sfim_short_url_init();

        $this->assertArrayNotHasKey('^i\.svg/?$', $wp_rewrite->extra_rules_top);
    }

    public function test_query_var_is_whitelisted(): void
    {
        $vars = apply_filters('query_vars', []);

        $this->assertContains('sfim_svg', $vars);
    }

    public function test_request_is_intercepted_on_short_url(): void
    {
        add_filter('sfim_short_url', '__return_true');

        $_SERVER['REQUEST_URI'] = '/i.svg';

        $query = apply_filters('request', []);

        $this->assertSame(1, $query['sfim_svg'] ?? null);
    }

    public function test_request_is_not_intercepted_when_disabled(): void
    {
        $_SERVER['REQUEST_URI'] = '/i.svg';

        $query = apply_filters('request', []);

        $this->assertArrayNotHasKey('sfim_svg', $query);
    }

    public function test_request_ignores_other_paths(): void
    {
        add_filter('sfim_short_url', '__return_true');

        $_SERVER['REQUEST_URI'] = '/wp-content/uploads/sf-icon-manager/ico.svg';

        $query = apply_filters('request', []);

        $this->assertArrayNotHasKey('sfim_svg', $query);
    }

    public function test_request_matches_subdirectory_and_trailing_slash(): void
    {
        add_filter('sfim_short_url', '__return_true');

        $_SERVER['REQUEST_URI'] = '/blog/i.svg/';

        $query = apply_filters('request', []);

        $this->assertSame(1, $query['sfim_svg'] ?? null);
    }

    public function test_validator_hit_on_matching_strong_etag(): void
    {
        $this->assertTrue(sfim_short_url_validator_hit('"abc"', '"abc"', '', ''));
    }

    public function test_validator_hit_on_matching_weak_etag(): void
    {
        $this->assertTrue(sfim_short_url_validator_hit('W/"abc"', '"abc"', '', ''));
    }

    public function test_validator_hit_on_matching_last_modified(): void
    {
        $modified = 'Mon, 14 Sep 2026 09:00:00 GMT';

        $this->assertTrue(sfim_short_url_validator_hit('', '"abc"', $modified, $modified));
    }

    public function test_validator_miss_on_other_etag(): void
    {
        $this->assertFalse(sfim_short_url_validator_hit('"other"', '"abc"', '', ''));
    }

    public function test_validator_miss_without_any_validator(): void
    {
        $this->assertFalse(sfim_short_url_validator_hit('', '"abc"', '', ''));
    }
}
