# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-09-14

### Added

- Short URL `/i.svg` via the `sfim_short_url` filter (Apache: flush permalinks once; Nginx: small config or symlink).
- SVG sanitization of uploaded sprites (scripts, event handlers, `javascript:` links).
- Automated dev releases from `main`.
- `docs/api.md` API reference.

### Changed

- Type declarations across the source (PHP 8.3+).
- Padding applies to the SVG element, not the wrapper.
- Admin code loads only in the backend; plugin ZIP ships vendored dependencies.
- Settings page shows the short URL under "Active sprite file".

## [0.2.1] - 2026-09-13

- WordPress.org plugin-check compliance: escape the rendered SVG markup through an input allowlist, add a direct-access guard to the render template, and make the readme description detect as standard English.
- Remove the discouraged `load_plugin_textdomain()` call; translations are loaded by WordPress for the plugin slug.

## [0.2.0] - 2026-09-12

- WordPress 7.1+ support (experimental): a new setting on the Icon Library settings page controls whether your uploaded sprite icons are also usable in WordPress's built-in Icon block, or only in this plugin's SVG Icon block. Options: `off` (default — icons are only available in the SVG Icon block), `on` (the sprite icons are also registered for the core Icon block), `no_block` (same as `on`, but the core Icon block is additionally fully removed, so the SVG Icon block remains the only icon block).
- Icon picker opens as a modal via a new "Replace" toolbar button (like the core Icon block) instead of the sidebar dropdown.
- Colors panel respects `theme.json` (`color.custom` / `color.palette`) and hides automatically for multi-color icons (e.g. Tango sets); fill and stroke remain separate.
- SVG Icon (`<use>`) keep rendering unrestricted in the SVG Icon block — the native path applies core's strict sanitizer.
- Lots of Bugfixes and better tests.

## [0.1.0] - 2026-09-10

Initial release.

- Settings page to upload a central SVG sprite file.
- Symbol picker in the block editor with live preview, grid/list view and group filter.
- Icon linking with new-tab, rel attributes and aria-label.
- Per-block fill/stroke colours and width/height with unit selection.
- Sprite override via the `sfim_sprite_url` filter.
- SVG sanitization on upload (scripts, event handlers, `javascript:` links).
- Server-side rendering with `get_block_wrapper_attributes()`.
