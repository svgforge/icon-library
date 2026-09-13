# Release 0.2.0 — Working plan

Status: Phase A done (all code verified on the site). Phase B executed locally on
`release/0.2.0-clean` (2026-09-13) — clean history rebuilt, nothing pushed.
Phase C (user test) and Phase D (publish) still pending.

Last update: 2026-09-13

## Agreement (applies from now on)

- One commit = one feature.
- Only working, verified states are committed (lint, typecheck, build, tests).
- No commit or push without explicit user approval.
- No force-push / history rewrite without explicit user approval.

## Phase A — Finish all remaining changes first (no git operations)

**DONE.** All remaining feature work is implemented and verified on the site
(colors on the SVG like core/icon, standard Dimensions size UI, spacing toggles
with padding off by default, source icon cleanup in WORKX/default, unit-test
roundup: PHPUnit 61 tests / Jest 22 tests).

## Phase B — Clean history rebuild (local only, nothing pushed)

**EXECUTED on 2026-09-13.** Nothing is pushed.

Backups created (all untouched):
- `backup/0.2.0-pre-cleanup` → old `release/0.2.0` history
- `backup/0.2.0-pushed` → old pushed `origin/release/0.2.0`
- `release/0.2.0-phaseA` → Phase A working state snapshot (memento commit), so
  no verified work gets lost during the rebuild
- `release/0.2.0` and its remote stay untouched as backups

Clean branch `release/0.2.0-clean` is created from `main` (the initial commit)
and re-introduces the whole 0.2.0 state as one commit per feature. Files that
span several features (block.json, render.php, icon-library.php, admin.php,
editor.css, style.css, sprite.svg) live in a single commit with their final
content, so each commit stays a complete, valid state. The final tree is
cryptographically identical to the Phase A snapshot (git-tree diff empty).

Commits on `release/0.2.0-clean`:

1. feat: theme.json-driven colors and size presets in the editor
2. feat: WordPress 7.1 native icon integration
3. chore: unit-test tooling and CI (Jest, lint, typecheck, dry-run publish)
4. feat: native integration options and svgforge-cli link on the settings page
5. feat: core Icon block parity — color, dimension and spacing supports,
   currentColor tint, block renamed to "SVG Icon" (icon-library/svg-icon)
6. docs: theme.json block settings, performance, 0.2.0 release notes, release
   plan

## Phase C — Test by the user

- User builds and tests the clean branch on the site.
- Everything still works; nothing is pushed yet.
- Old `release/0.2.0` and its remote stay untouched as backup.

## Phase D — Publish (only with explicit user go)

- Either replace `release/0.2.0` with the clean branch (force-push with user
  consent, CI re-runs on PR #5), or open a new PR from the clean branch and
  close PR #5.