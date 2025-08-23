# Store Notice Plus

Customizable, rotating, dismissible WooCommerce store notice banner with color pickers, slide animation, and flexible placement (inside header, after `<body>`, or footer). Built the WordPress way (Settings API), a11y-friendly, and mobile-ready.

- **Multiple messages** (one per line, safe HTML links allowed)
- **Rotation** with smooth **slide** animation, pause on hover/focus
- **Dismissible** (“×”) with configurable cookie lifetime
- **Color pickers** (background, text, link, close)
- **Placement**: inside **header** (via selector), **top** (after `<body>`), or **footer**
- **Top/bottom** position + optional sticky (top only)
- **Hide** WooCommerce native Store Notice to avoid duplicates
- **A11y**: `aria-live="polite"`, labeled close button, reduced-motion support
- **Performance**: assets enqueued only when needed, cache-busted via `filemtime`

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 7.0+ (optional but intended)
- Modern theme that calls `wp_body_open` (fallbacks included)

---

## Installation

### From ZIP (recommended)
1. Download the release ZIP (or build one — see **Build** below).
2. WP Admin → **Plugins → Add New → Upload Plugin** → select ZIP.
3. Activate: **Store Notice+** appears in the sidebar.

### From source
Place this folder into `wp-content/plugins/store-notice-plus/` and activate the plugin.

---

## Configuration

WP Admin → **Store Notice+**

- **Enable banner**: on/off
- **Messages**: one per line. Allowed tags: `<a> <strong> <em> <br> <span>`
- **Rotation interval** (seconds): 2–60
- **Dismiss duration** (days): 1–365
- **Insert banner at**:
  - **Inside header (via selector)** — prepends into your header element
  - **Top (right after `<body>`)** — above the theme header
  - **Footer (before `</body>`)** — non-sticky by design
- **Header CSS selector**: comma-separated (first match wins), default:
  `header, .site-header, #site-header, #masthead, .main-header`
- **Position**: top/bottom (when not “inside header”)
- **Sticky** (top only)
- **Colors**: background, text, link, close (native WP color pickers)
- **Hide WooCommerce default Store Notice**

> Tip: If you choose *Inside header*, we disable our own sticky; if your header is sticky, the banner rides with it.

---

## Developer notes

### Asset versioning
Assets are versioned with a small helper using `filemtime`, so browsers fetch new CSS/JS automatically on change. Example:

```php
function snp_asset_ver( $path ) {
  $abs = plugin_dir_path(__FILE__) . ltrim($path, '/');
  return file_exists($abs) ? (string) filemtime($abs) : (defined('SNP_VERSION') ? SNP_VERSION : '1.0.0');
}

