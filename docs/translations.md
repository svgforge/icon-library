# Translations

User-facing strings are written in English and shipped through the text domain `wp-iconizer`. Translation files live in `languages/`:

- `wp-iconizer.pot` — source template
- `wp-iconizer-*.po` / `.mo` — per-locale translations for the settings page and the block metadata
- `wp-iconizer-<locale>-<hash>.json` — Jed-style JSON for the block editor script strings

`de_DE` is bundled. The plugin header sets `Text Domain: wp-iconizer` and `Domain Path: /languages`; `load_plugin_textdomain()` is additionally hooked on `init` in `wp-iconizer.php`. The `Domain Path` header is required for the just-in-time translation loading WordPress uses since 6.7 (it tells the `WP_Textdomain_Registry` where the bundled `.mo` files live before the first translation lookup).

## Adding or updating a locale

The POT includes the compiled editor bundle, so rebuild the block and extract first:

```bash
pnpm run build

# via WP-CLI / ddev (i18n-command), from the plugin root:
wp i18n make-pot . languages/wp-iconizer.pot --slug=wp-iconizer --ignore-domain \
    --exclude="node_modules/**,vendor/**,tests/**,.github/**,languages/**" \
    --include="src/**,wp-iconizer.php,build/block/index.js"

# fill in languages/wp-iconizer-<locale>.po (German: wp-iconizer-de_DE.po), then:
wp i18n make-mo languages
wp i18n make-json languages/wp-iconizer-<locale>.po languages --pretty-print
```

Commit the generated `.pot`, `.po`, `.mo` and `.json` files.

## Notes

- WP-CLI `make-pot` scans `.php` and `.js`, but **not** `.tsx` — the block editor strings are extracted from the compiled `build/block/index.js`, so run `pnpm run build` before extracting.
- The block title/description (block.json) are translated at runtime through the plugin `.mo`; the JSON files only translate the editor script strings.
- A `.mo` that is bundled in the plugin's `languages/` folder is only picked up if the plugin header contains `Domain Path: /languages`. Without it, WordPress registers the plugin root as the language directory and the `.mo` is never found (WP 6.7+ registry fallback keeps the wrong cached path).