<?php

/**
 * Tests for the sprite preview grouping helper.
 *
 * @package sf-icon-manager
 */

/**
 * Tests for sfim_sprite_preview_groups().
 */
final class Test_Icon_Library_Sprite_Preview extends WP_UnitTestCase
{
    private function icon(string $id): array
    {
        return ['name' => 'sf-icon-manager/' . $id, 'label' => $id, 'content' => '<svg></svg>'];
    }

    public function test_groups_icons_by_directory_prefix(): void
    {
        $groups = sfim_sprite_preview_groups([
            $this->icon('actions--add_circle'),
            $this->icon('people--user'),
            $this->icon('actions--check'),
        ]);

        $this->assertSame(['actions', 'people'], array_column($groups, 'prefix'));
        $this->assertCount(2, $groups[0]['icons']);
        $this->assertCount(1, $groups[1]['icons']);
    }

    public function test_group_order_is_alphabetical(): void
    {
        $groups = sfim_sprite_preview_groups([
            $this->icon('zeta--a'),
            $this->icon('alpha--b'),
        ]);

        $this->assertSame(['alpha', 'zeta'], array_column($groups, 'prefix'));
    }

    public function test_icons_without_prefix_form_a_trailing_group(): void
    {
        $groups = sfim_sprite_preview_groups([
            $this->icon('plain_icon'),
            $this->icon('actions--check'),
        ]);

        $this->assertCount(2, $groups);
        $this->assertSame('', $groups[1]['prefix']);
        $this->assertSame(['plain_icon'], array_column($groups[1]['icons'], 'label'));
    }

    public function test_icons_within_a_group_are_sorted_by_id(): void
    {
        $groups = sfim_sprite_preview_groups([
            $this->icon('actions--zeta'),
            $this->icon('actions--alpha'),
        ]);

        $this->assertSame(['actions--alpha', 'actions--zeta'], array_column($groups[0]['icons'], 'label'));
    }

    public function test_empty_input_yields_empty_result(): void
    {
        $this->assertSame([], sfim_sprite_preview_groups([]));
    }
}
