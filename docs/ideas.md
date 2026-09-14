# Ideas / TODO

Collection of feature ideas with their research status. Nothing in this file is
committed to the plugin yet.

## Render icons as `<img>` instead of `<use>`

Status: idea — research done, not implemented.

Rationale: `<use>` clones the symbol into the shadow tree once per instance,
which grows the DOM and the paint cost with the number of icons. `<img>` is one
DOM node, cacheable (fits the existing `immutable` cache strategy) and
lazy-loadable. Measurable benefit mostly above ~100 visible icons per page
(Cloud Four stress test); below that the difference is marginal.

Blocker — why a second sprite would be needed: `src="sprite.svg#symbol-id"` does
not work. In an image context, SVG fragment identifiers only resolve to areas
declared with `<view viewBox="x y w h">` (SVG 1.1 Linking, §17.3); a `<symbol>`
fragment renders nothing. `<img>` therefore needs one of:

- a second `view`-based sprite (viewBox clipping; collision-prone when icons
  have heterogeneous sizes), or
- one standalone SVG per icon, e.g. an endpoint `/icon/<slug>.svg` generated
  from `icon_library_parse_sprite_icons()` / `icon_library_icon_shape()`, served
  with `immutable` cache headers and the same rewrite/serve infrastructure as
  the short URL — or
- an `svgView(viewBox(...))` fragment (comma escaping in URLs is awkward).

Tradeoff: `<img>` cannot be tinted via `currentColor` or path-level CSS, so it
only makes sense when no color is applied to the icon — fall back to `<use>`
otherwise.

Proposed UI (decided to stay one block): keep the SVG Icon block, add a
per-instance "render as `<img>`" toggle in the inspector plus an optional global
default on the settings page. A separate block would duplicate the editor
logic, attributes and icon picker.

Open questions: global default for new icons (`use` keeps backward compat?),
`loading="lazy"` as the `<img>` default?