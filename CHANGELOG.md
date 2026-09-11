# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-10

Initial release.

- Settings page to upload a central SVG sprite file.
- Symbol picker in the block editor with live preview, grid/list view and group filter.
- Icon linking with new-tab, rel attributes and aria-label.
- Per-block fill/stroke colours and width/height with unit selection.
- Sprite override via the `icon_library_sprite_url` filter.
- SVG sanitization on upload (scripts, event handlers, `javascript:` links).
- Server-side rendering with `get_block_wrapper_attributes()`.
