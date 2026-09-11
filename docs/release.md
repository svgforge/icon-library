# Automatic release to GitHub + WordPress.org

Pushing a tag (e.g. `v0.1.0`) builds the plugin, publishes it to the wp.org directory, and creates a GitHub release with a ZIP:

```bash
git tag v0.1.0 && git push origin v0.1.0
```

The workflow `.github/workflows/release.yml` uses:
- `pnpm/action-setup` + `actions/setup-node` (cache: pnpm) → `pnpm install --frozen-lockfile`
- `pnpm run build` → production-ready `build/`
- `10up/action-wordpress-plugin-deploy@stable` → commit to wp.org SVN (trunk + tag)
- `softprops/action-gh-release@v2` → GitHub release with the generated ZIP

## Requirements for wp.org publishing

1. The plugin must already be listed in the [WordPress.org directory](https://wordpress.org/plugins/) (initial submission process with manual review).
2. `readme.txt` in the repo is required — it is used for the wp.org listing.
3. Store repo secrets (Settings → Secrets and variables → Actions):
   - `SVN_USERNAME` — your wp.org username (developer account with plugin access)
   - `SVN_PASSWORD` — your wp.org API/SVN password (not your login password)

## Assets (banners/screenshots/icons)

Optional: create a `.wordpress-org/` folder and place `banner-772x250.png`, `icon-256x256.png`, and `screenshot-1.png` there. The deploy action transfers them to the wp.org SVN `assets/` directory automatically.

## Excluded files

Development files that must not be published are listed in `.distignore` (prevents `src/`, `node_modules`, `docs/`, etc. from being deployed to wp.org).