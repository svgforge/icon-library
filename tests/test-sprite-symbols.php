<?php

/**
 * Tests for icon_library_sprite_symbols().
 *
 * @package icon-library
 */

/**
 * Tests that the symbol enumeration returns every raw symbol id of the sprite.
 */
final class Test_Icon_Library_Sprite_Symbols extends WP_UnitTestCase
{
    private string $temp = '';

    protected function setUp(): void
    {
        parent::setUp();
        delete_option(ICON_LIBRARY_SPRITE_OPTION);
        remove_all_filters('icon_library_sprite_url');

        $this->temp = (string) tempnam(sys_get_temp_dir(), 'icon-library-sprite') . '.svg';
    }

    protected function tearDown(): void
    {
        if ($this->temp !== '') {
            @unlink($this->temp);
        }

        parent::tearDown();
    }

    private function set_sprite(string $svg): void
    {
        file_put_contents($this->temp, $svg);

        update_option(ICON_LIBRARY_SPRITE_OPTION, [
            'url' => 'https://example.test/tmp/sprite.svg',
            'path' => $this->temp,
            'time' => 1,
        ]);
    }

    public function test_lists_all_symbol_ids_sorted(): void
    {
        $this->set_sprite(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol id="zeta"><path d="M0 0"/></symbol>'
            . '<symbol id="alpha"><rect x="0" y="0" width="1" height="1"/></symbol>'
            . '<symbol id="zeta"/></svg>',
        );

        $this->assertSame(['alpha', 'zeta'], icon_library_sprite_symbols());
    }

    public function test_returns_symbols_without_core_shape_filter(): void
    {
        $this->set_sprite(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol id="no-shape"/>'
            . '<symbol id="circle-only"><circle cx="1" cy="1" r="1"/></symbol>'
            . '<symbol id="path-only"><path d="M0 0"/></symbol></svg>',
        );

        $this->assertSame(['circle-only', 'no-shape', 'path-only'], icon_library_sprite_symbols());
    }

    public function test_ignores_symbols_without_id(): void
    {
        $this->set_sprite(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol/>'
            . '<symbol id="ok"><path d="M0 0"/></symbol></svg>',
        );

        $this->assertSame(['ok'], icon_library_sprite_symbols());
    }

    public function test_returns_empty_for_invalid_svg(): void
    {
        $this->set_sprite('<svg xmlns="http://www.w3.org/2000/svg"><symbol id="broken">');

        $this->assertSame([], icon_library_sprite_symbols());
    }
}
