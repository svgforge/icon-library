# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- PHPUnit test suite: `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/wp-tests-config.php` and tests for the sprite URL resolution, the SVG sanitizer and the block render callback (WP test suite via `wp-phpunit` + `yoast/phpunit-polyfills`).
- CI workflow (`.github/workflows/ci.yml`): runs PHPUnit + Pint and the JS lint/typecheck checks on every pull request and push to `main`.
- CodeQL workflow (`.github/workflows/codeql.yml`): JS/TypeScript analysis on PRs, `main` and weekly (PHP is not supported by CodeQL).
- TypeScript migration: block sources are now `edit.tsx` and `index.tsx` with a strict `tsconfig.json` (`typecheck` script).
- `eslint.config.js` flat config extending the WordPress default (allows the experimental `ToggleGroupControl` imports).
- Manual type shims for untyped WP packages and global declarations (`src/ambient.d.ts`, `src/global.d.ts`, `src/types/`).
- Full internationalization: all translatable strings are English source strings, a `languages/` directory ships the template `wp-iconizer.pot` with German (`de_DE`) translations for the settings page and the block editor (`wp-iconizer-de_DE.po`, `.mo`, `.json`). `AGENTS.md` documents the English-only policy.

### Changed
- Fallback sprite: the plugin now ships a default `sprite.svg` and uses it instead of the web server root `/ico.svg`.
- Replaced deprecated `ButtonGroup` with `ToggleGroupControl`/`ToggleGroupControlOptionIcon` for the grid/list view toggle.
- Removed deprecated `__nextHasNoMarginBottom` prop; the margin is now reset via CSS.
- All code comments and the README are in English.
- README: Composer installation now documents the WP Packages repository (`wp-plugin/*`) instead of WPackagist; added a svgforge-cli hint for generating the sprite; added a Translations section with the `make-pot`/`make-mo`/`make-json` workflow.
- Documentation restructured: README reduced to a lean overview; detailed topics moved into `docs/` (`developer`/maintenance guide, translations, release + wp.org, Composer installation) and `screen.png` moved to `docs/`. The sprite file configuration stays in the README.

### Fixed
- `pnpm format` failing on the invalid YAML in `.github/workflows/release.yml` (tab indentation), rewrote the workflow.

## [0.1.0] - 2026-09-10

### Added
- Settings page (Settings → WP Iconizer) to upload an SVG sprite file (`ico.svg`/`.svgz`) as a central fragment library.
- SVG sanitization on upload: removes scripts, `foreignObject`, inline event handlers, and `javascript:` links.
- Sprite source resolution: theme constant > backend upload > `WP_ICONIZER_SPRITE_URL` constant > `wp_iconizer_sprite_url` filter > `/ico.svg` fallback.
- Symbol picker in the editor with a live preview of all `<symbol>` elements, group filter (ID prefix before `--`), and grid/list view toggle.
- Link support for icons (new tab including `noopener`/`noreferrer`), aria-label handling, and automatic `rel` syncing.
- Per-block fill/stroke colours and width/height with `px`/`em`/`rem`/`%` units.
- Dynamic frontend rendering via `render.php` with `get_block_wrapper_attributes()`.
- Cache busting: the sprite URL is versioned with `?m=<upload time>`.
- Release workflow (`.github/workflows/release.yml`) for WordPress.org + GitHub releases.
- Composer packaging (`wordpress-plugin`, Pint linting).

### Changed
- Editor picker width fixed to `360px` with `max-width: calc(100vw - 32px)` and grid overflow clipping.
- Sprite refetch in the editor when the picker is reopened after an upload.

### Fixed
- Frontend block not rendering on WordPress 7.1 (`render.php` must `echo`, return values are discarded).
- Horizontal scrollbar in the picker grid view.
- Wrong Intelephense setting key (`intelephense.files.maxSize` instead of singular `file`).