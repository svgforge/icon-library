# Composer installation

The plugin is packaged as a `wordpress-plugin` (`composer/installers`). Installable from the GitHub repo (development) or via the **WP Packages** repository once published.

**Note:** You must build the frontend manually, because the build directory is not in git.

## From the GitHub repository (development)

```json
// composer.json of your WordPress project
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/svgforge/sf-icon-manager.git"
        }
    ],
    "require": {
        "svgforge/sf-icon-manager": "dev-main"
    }
}
```

```bash
composer require svgforge/sf-icon-manager
```

## Via WP Packages (once published on WordPress.org)

[WP Packages](https://github.com/roots/wp-packages) is the community-run WPackagist replacement built and maintained by [Roots](https://roots.io/), used by default in current Bedrock projects. It mirrors the WordPress.org directory every 5 minutes, supports the Composer v2 `metadata-url` protocol (instead of WPackagist's legacy `provider-includes` index), and offers clean `wp-plugin/*` / `wp-theme/*` naming.

```json
// composer.json of your WordPress project
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.wp-packages.org"
        }
    ],
    "require": {
        "wp-plugin/sf-icon-manager": "^0.1"
    }
}
```

```bash
composer require wp-plugin/sf-icon-manager
```

`composer/installers` places the plugin under `wp-content/plugins/sf-icon-manager/` automatically when your project has `"type": "wordpress-plugin"` paths configured (e.g. via `extra.installer-paths`).

> Note: even with Composer installation, the build must be present or checked into Git before release — the release workflow builds `build/` automatically.

## Composer scripts

```bash
composer install                        # installs dev tools (Pint, PHPUnit)
composer run lint                       # Pint in check mode (--test, exit 1 on deviations)
composer run lint:fix                   # Pint auto-fix for pre-configured files/rules (pint.json)
composer run test                       # PHPUnit (needs a reachable WordPress core + MySQL, see docs/development.md)
```

Configuration lives in `preset: per` in `pint.json` at the project root; generated folders like `build/` are excluded. Default setup based on [roots/bedrock](https://github.com/roots/bedrock). List used rules: `vendor/bin/pint --list`.