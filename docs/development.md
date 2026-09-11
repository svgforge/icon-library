# Development

## Build

```bash
pnpm install        # one-time
pnpm start          # build + watch (hot-reload) in the editor
pnpm run build      # production build into /build
```

Other scripts: `pnpm run lint:js`, `pnpm run lint:css`, `pnpm run typecheck`, `pnpm run format`, `pnpm run check-engines`, `pnpm run plugin-zip`.

Requirements: Node.js LTS (>= 20.19, recommended 24 — see `.nvmrc`) and [pnpm](https://pnpm.io/) (version pinned via `packageManager` in `package.json`).

> IntelliSense (VSCode/Intelephense): `php-stubs/wordpress-stubs` is installed as a dev dependency (declares the full WP API for auto-completion). The setting lives in `.vscode/settings.json` (`intelephense.files.maxSize`); after installation, run **"Intelephense: Clear Cache and Reload"** once.

> Note: `wp-scripts packages-update` uses npm internally and would write a `package-lock.json` for the `@wordpress/*` dependencies. Since this project runs on pnpm, update the WP packages with `pnpm up --latest` instead, then run `pnpm install`.

## Tests

PHPUnit unit tests run against the [WordPress test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/) (`wp-phpunit`) with the maintained [yoast/phpunit-polyfills](https://github.com/Yoast/PHPUnit-Polyfills). Test cases live in `tests/` (`tests/wp-tests-config.php` holds DB + core defaults, overridable via `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD`, `WP_TESTS_DB_HOST`, `WP_TESTS_WP_ROOT`).

The test suite needs a MySQL database (default `wordpress_test`) and a WordPress core checkout with the plugin available under `wp-content/plugins/wp-iconizer`. On this project's ddev site (`jdfse`), the plugin is symlinked into `web/app/plugins`, so:

```bash
cd ../jdfse && ddev mysql -e 'CREATE DATABASE IF NOT EXISTS wordpress_test'
ddev exec bash -c 'cd /var/www/html/web/app/plugins/wp-iconizer && php vendor/bin/phpunit --no-coverage'
```

In CI, point the environment variables at the service MySQL and a WP core checkout with the plugin installed there.

## Translations

See [docs/translations.md](translations.md).

## Release

See [docs/release.md](release.md).

## Structure

```
wp-iconizer/
├── wp-iconizer.php        Plugin bootstrap (block registration, sprite URL, i18n)
├── docs/                  Documentation (this directory)
├── src/
│   ├── admin/admin.php    Settings page (SVG upload), not part of the build
│   ├── block/             Block source code
│   │   ├── block.json     Block metadata (apiVersion 3)
│   │   ├── index.tsx      registerBlockType (TypeScript)
│   │   ├── edit.tsx       Editor component (React, TypeScript)
│   │   ├── render.php     Frontend rendering (dynamic)
│   │   ├── editor.css     Editor styles
│   │   ├── style.css      Frontend styles
│   │   └── icon.svg       Block icon
│   ├── ambient.d.ts       Ambient type declarations (*.svg, *.css)
│   ├── global.d.ts        Window augmentation
│   └── types/             Manual type shims for untyped WP packages
├── build/block/           Generated (do not commit): index.js/css, block.json, render.php
├── eslint.config.js       ESLint flat config (extends WP default)
├── tsconfig.json          TypeScript configuration (noEmit, strict)
├── package.json           pnpm scripts & @wordpress/* packages
├── pnpm-lock.yaml         Lockfile (commit for CI)
├── pnpm-workspace.yaml    pnpm settings (allowBuilds etc.)
├── composer.json          Composer package (wordpress-plugin)
├── .github/workflows/     Release + deploy
└── readme.txt             WordPress.org listing
```