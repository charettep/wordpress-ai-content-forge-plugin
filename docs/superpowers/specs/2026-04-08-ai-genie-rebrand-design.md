# AI Genie Rebrand — Design Spec

**Date:** 2026-04-08
**Status:** Approved
**Scope:** Full rename (clean break) + mascot icon system

---

## Overview

Rename the plugin from "AI Content Forge" to "AI Genie" and replace the existing branding with a cute cartoon genie mascot. This is a clean break — all internal identifiers change and existing saved settings will reset on upgrade.

---

## 1. Rename Spec

### Display & Metadata

| Field | From | To |
|---|---|---|
| Plugin display name | AI Content Forge | AI Genie |
| Plugin description | (update to match new name) | AI-powered content generation via Claude, OpenAI, or Ollama — your AI genie for WordPress content. |
| Plugin URI | `...wordpress-ai-content-forge-plugin` | `...wordpress-ai-genie-plugin` |

### File & Slug

| Field | From | To |
|---|---|---|
| Main plugin file | `ai-content-forge.php` | `ai-genie.php` |
| Plugin slug | `ai-content-forge` | `ai-genie` |
| Text domain | `ai-content-forge` | `ai-genie` |
| Admin menu slug | `ai-content-forge` | `ai-genie` |
| Build script `PLUGIN_SLUG` | `ai-content-forge` | `ai-genie` |

### PHP Identifiers

| From | To |
|---|---|
| `ACF_VERSION` | `AIG_VERSION` |
| `ACF_PLUGIN_DIR` | `AIG_PLUGIN_DIR` |
| `ACF_PLUGIN_URL` | `AIG_PLUGIN_URL` |
| `ACF_Admin` | `AIG_Admin` |
| `ACF_Settings` | `AIG_Settings` |
| `ACF_Generator` | `AIG_Generator` |
| `ACF_Provider` | `AIG_Provider` |
| `ACF_Rest_Api` | `AIG_Rest_Api` |
| `ACF_Gutenberg` | `AIG_Gutenberg` |
| `ACF_Provider_Claude` | `AIG_Provider_Claude` |
| `ACF_Provider_OpenAI` | `AIG_Provider_OpenAI` |
| `ACF_Provider_Ollama` | `AIG_Provider_Ollama` |

### JavaScript & CSS

- `assets/js/admin.js`:
  - Rename localStorage key `acf-active-tab` → `aig-active-tab`
  - Rename all jQuery selectors: `.acf-tab-nav`, `.acf-tab-panel`, `.acf-tab-link`, `.acf-summary-badge`, `.acf-badge-indicator`, `.acf-summary-strip` → `.aig-*` equivalents
  - Rename DOM ID selectors: `#acf-save-footer`, `#acf-dirty-notice`, `#acf-discard-btn`, `#acf-settings-form` → `#aig-*`
- `gutenberg/src/index.js` — update slug references and localised data keys (rebuild `gutenberg/build/` after)
- `assets/css/admin.css` — rename all `.acf-*` classes to `.aig-*`
- PHP admin templates (`admin/class-acf-admin.php`) — update all HTML `id=` and `class=` attributes that output `acf-*` to `aig-*` so they match the JS selectors and CSS

### GitHub Repository

| Field | From | To |
|---|---|---|
| Repo name | `wordpress-ai-content-forge-plugin` | `wordpress-ai-genie-plugin` |

Rename via: `gh repo rename wordpress-ai-genie-plugin`

### Clean Break Notes

- WordPress options stored under `ai-content-forge` keys will be orphaned. No migration needed — this is intentional.
- The admin menu hook suffix will change from `toplevel_page_ai-content-forge` to `toplevel_page_ai-genie`. Update the hook check in `AIG_Admin::enqueue_assets()`.
- Gutenberg meta key `_acf_meta_description` → `_aig_meta_description` (clean break, existing meta is abandoned).

---

## 2. Icon System

### Mascot Design

**Character:** Cute cartoon genie — full body, arms raised in excitement, wispy smoke tail below, golden turban with jewel on top. Large expressive eyes with gloss highlights. Rosy cheeks. Style: rounded/bubbly, Duolingo-tier friendliness.

**Color palette:**

| Name | Hex | Usage |
|---|---|---|
| Genie Teal | `#10B981` | Body, arms, primary brand color |
| Deep Forest | `#064E3B` | Background, pupils, dark accents |
| Turban Gold | `#F59E0B` | Turban base, sparkles |
| Gold Deep | `#D97706` | Turban mid roll |
| Jewel Light | `#FCD34D` | Turban jewel, star sparkles |
| Wisp Mint | `#6EE7B7` | Cheeks, tail glow, light accents |

### Icon Files

| File | Size | Format | Usage |
|---|---|---|---|
| `images/plugin-icon.png` | 256×256 | PNG | Plugins list page, admin page header |
| `images/plugin-icon-wpadmin_left_sidebar.png` | 64×64 | PNG | Legacy sidebar reference |
| `images/plugin-icon-gutenberg-selected.png` | 48×48 | PNG | Gutenberg sidebar panel, active state |
| `images/plugin-icon-gutenberg-unselected.png` | 48×48 | PNG | Gutenberg sidebar panel, inactive state |
| Inline SVG in `AIG_Admin::menu_icon_data_uri()` | 20×20 | SVG | WordPress admin sidebar menu |

### Sidebar SVG Behaviour

The sidebar icon uses `currentColor` so WordPress can tint it correctly across all admin colour schemes (default dark, active blue, light, ocean, etc.). The icon renders as a simplified genie silhouette: round body + turban cap visible at 20px. Pupils are rendered as negative-space white circles over a dark fill.

### Gutenberg Icon States

- **Selected:** Full colour — teal body, gold turban, white eyes, dark pupils
- **Unselected:** Muted — body desaturated to `#8FA3BE`, turban to `#A0B4CC`, on `#E8EDF5` background

---

## 3. Release Process

Per `CLAUDE.md`, every change requires the full release workflow:

1. Update plugin version in `ai-genie.php` (bump to next minor, e.g. `2.13.0`)
2. Update `README.md` to reflect new name throughout
3. Commit all changes and push to `main` on renamed GitHub repo
4. Run `./scripts/build-release.sh` to produce `ai-genie-v2.13.0.zip`
5. Publish GitHub release with the zip asset
6. Verify install/update/activate from WP admin upload UI

---

## 4. Out of Scope

- Migrating existing user settings (clean break by design)
- Publishing to the WordPress.org plugin directory
- Changing the Docker / deployment configuration
- Any frontend or content-generation feature changes
