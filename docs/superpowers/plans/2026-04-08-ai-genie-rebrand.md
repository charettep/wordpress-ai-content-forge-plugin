# AI Genie Rebrand Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the plugin from "AI Content Forge" to "AI Genie" (clean break), replace all identifiers, and swap the icon system to a cute emerald-teal genie mascot.

**Architecture:** Bulk find-replace of identifiers across PHP, JS, and CSS (ACF_ → AIG_, acf- → aig-, ai-content-forge → ai-genie). Generate new mascot icon SVGs, convert to PNG, update inline admin sidebar SVG. Rebuild Gutenberg bundle. Bump version to 3.0.0 (major = breaking rename). Rename GitHub repo and publish release.

**Tech Stack:** PHP 8.1, WordPress 6.4+, `@wordpress/scripts` (Gutenberg build), `rsvg-convert` (SVG→PNG), `gh` CLI (repo rename + release)

---

## File Map

### Files to rename (git mv)

| From | To |
|---|---|
| `ai-content-forge.php` | `ai-genie.php` |
| `admin/class-acf-admin.php` | `admin/class-aig-admin.php` |
| `admin/class-acf-gutenberg.php` | `admin/class-aig-gutenberg.php` |
| `includes/class-acf-settings.php` | `includes/class-aig-settings.php` |
| `includes/class-acf-provider.php` | `includes/class-aig-provider.php` |
| `includes/class-acf-generator.php` | `includes/class-aig-generator.php` |
| `includes/class-acf-rest-api.php` | `includes/class-aig-rest-api.php` |
| `includes/providers/class-acf-provider-claude.php` | `includes/providers/class-aig-provider-claude.php` |
| `includes/providers/class-acf-provider-openai.php` | `includes/providers/class-aig-provider-openai.php` |
| `includes/providers/class-acf-provider-ollama.php` | `includes/providers/class-aig-provider-ollama.php` |

### Files to modify in-place (content changes only)

| File | What changes |
|---|---|
| `assets/css/admin.css` | `.acf-*` → `.aig-*` (bulk replace) |
| `assets/js/admin.js` | `acf-*` → `aig-*` selectors, localStorage key, DOM IDs |
| `gutenberg/src/index.js` | `window.acfGutenberg` → `window.aigGutenberg`, `ai-content-forge` → `ai-genie`, text domain |
| `gutenberg/package.json` | `name` field |
| `scripts/build-release.sh` | All `ai-content-forge` → `ai-genie` references |
| `README.md` | Full rewrite of name references |
| `CLAUDE.md` | Update name references |
| `AGENTS.md` | Update name references if present |

### Files to create

| File | Purpose |
|---|---|
| `images/icon-source.svg` | 256×256 full-colour mascot SVG (source for PNGs) |
| `images/icon-gutenberg-selected-src.svg` | 48×48 selected-state Gutenberg icon |
| `images/icon-gutenberg-unselected-src.svg` | 48×48 muted unselected-state Gutenberg icon |

### Files to regenerate

| File | How |
|---|---|
| `images/plugin-icon.png` | `rsvg-convert -w 256 -h 256` from `icon-source.svg` |
| `images/plugin-icon-wpadmin_left_sidebar.png` | `rsvg-convert -w 64 -h 64` from `icon-source.svg` |
| `images/plugin-icon-gutenberg-selected.png` | `rsvg-convert -w 48 -h 48` from selected SVG |
| `images/plugin-icon-gutenberg-unselected.png` | `rsvg-convert -w 48 -h 48` from unselected SVG |
| `gutenberg/build/*` | `cd gutenberg && npm run build` |

---

### Task 1: Rename PHP files and update main entry point

**Files:**
- Rename: all 10 PHP files listed in File Map above
- Modify: `ai-genie.php` (after rename)

- [ ] **Step 1: Rename all PHP files with git mv**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
git mv ai-content-forge.php ai-genie.php
git mv admin/class-acf-admin.php admin/class-aig-admin.php
git mv admin/class-acf-gutenberg.php admin/class-aig-gutenberg.php
git mv includes/class-acf-settings.php includes/class-aig-settings.php
git mv includes/class-acf-provider.php includes/class-aig-provider.php
git mv includes/class-acf-generator.php includes/class-aig-generator.php
git mv includes/class-acf-rest-api.php includes/class-aig-rest-api.php
git mv includes/providers/class-acf-provider-claude.php includes/providers/class-aig-provider-claude.php
git mv includes/providers/class-acf-provider-openai.php includes/providers/class-aig-provider-openai.php
git mv includes/providers/class-acf-provider-ollama.php includes/providers/class-aig-provider-ollama.php
```

- [ ] **Step 2: Rewrite `ai-genie.php` (main entry point)**

Replace the entire file with:

```php
<?php
/**
 * Plugin Name: AI Genie
 * Plugin URI:  https://github.com/charettep/wordpress-ai-genie-plugin
 * Description: AI-powered content generation (posts, SEO, descriptions) via Claude, OpenAI, or Ollama — your AI genie for WordPress content.
 * Version:     3.0.0
 * Author:      charettep
 * License:     GPL-2.0+
 * Text Domain: ai-genie
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'AIG_VERSION',    '3.0.0' );
define( 'AIG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIG_PLUGIN_DIR . 'includes/class-aig-settings.php';
require_once AIG_PLUGIN_DIR . 'includes/class-aig-provider.php';
require_once AIG_PLUGIN_DIR . 'includes/providers/class-aig-provider-claude.php';
require_once AIG_PLUGIN_DIR . 'includes/providers/class-aig-provider-openai.php';
require_once AIG_PLUGIN_DIR . 'includes/providers/class-aig-provider-ollama.php';
require_once AIG_PLUGIN_DIR . 'includes/class-aig-generator.php';
require_once AIG_PLUGIN_DIR . 'includes/class-aig-rest-api.php';
require_once AIG_PLUGIN_DIR . 'admin/class-aig-admin.php';
require_once AIG_PLUGIN_DIR . 'admin/class-aig-gutenberg.php';

function aig_init() {
    AIG_Settings::init();
    AIG_Rest_API::init();
    AIG_Admin::init();
    AIG_Gutenberg::init();
}
add_action( 'plugins_loaded', 'aig_init' );
```

- [ ] **Step 3: Verify syntax**

```bash
php -l ai-genie.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "rename: mv all PHP files from ACF to AIG prefix, rewrite entry point"
```

---

### Task 2: Update all PHP includes files (bulk identifier rename)

**Files:**
- Modify: `includes/class-aig-settings.php`
- Modify: `includes/class-aig-provider.php`
- Modify: `includes/class-aig-generator.php`
- Modify: `includes/class-aig-rest-api.php`
- Modify: `includes/providers/class-aig-provider-claude.php`
- Modify: `includes/providers/class-aig-provider-openai.php`
- Modify: `includes/providers/class-aig-provider-ollama.php`

- [ ] **Step 1: Bulk-replace identifiers across all includes PHP files**

Use `sed` for the mechanical replacements. Run each command:

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin

# Class names: ACF_ → AIG_
sed -i 's/ACF_Settings/AIG_Settings/g; s/ACF_Provider_Claude/AIG_Provider_Claude/g; s/ACF_Provider_OpenAI/AIG_Provider_OpenAI/g; s/ACF_Provider_Ollama/AIG_Provider_Ollama/g; s/ACF_Provider/AIG_Provider/g; s/ACF_Generator/AIG_Generator/g; s/ACF_Rest_API/AIG_Rest_API/g' \
  includes/class-aig-settings.php \
  includes/class-aig-provider.php \
  includes/class-aig-generator.php \
  includes/class-aig-rest-api.php \
  includes/providers/class-aig-provider-claude.php \
  includes/providers/class-aig-provider-openai.php \
  includes/providers/class-aig-provider-ollama.php

# Constants: ACF_VERSION → AIG_VERSION, ACF_PLUGIN_DIR → AIG_PLUGIN_DIR, ACF_PLUGIN_URL → AIG_PLUGIN_URL
sed -i 's/ACF_VERSION/AIG_VERSION/g; s/ACF_PLUGIN_DIR/AIG_PLUGIN_DIR/g; s/ACF_PLUGIN_URL/AIG_PLUGIN_URL/g' \
  includes/class-aig-settings.php \
  includes/class-aig-provider.php \
  includes/class-aig-generator.php \
  includes/class-aig-rest-api.php \
  includes/providers/class-aig-provider-claude.php \
  includes/providers/class-aig-provider-openai.php \
  includes/providers/class-aig-provider-ollama.php

# Settings option key
sed -i "s/'acf_settings'/'aig_settings'/g" includes/class-aig-settings.php

# Settings group
sed -i "s/'acf_settings_group'/'aig_settings_group'/g" includes/class-aig-settings.php

# REST namespace
sed -i "s|ai-content-forge/v1|ai-genie/v1|g" includes/class-aig-rest-api.php

# Transient cache key prefix
sed -i "s/'acf_models_'/'aig_models_'/g" includes/class-aig-rest-api.php

# Text domain in __() calls
sed -i "s/'ai-content-forge'/'ai-genie'/g" \
  includes/class-aig-settings.php \
  includes/class-aig-provider.php \
  includes/class-aig-generator.php \
  includes/class-aig-rest-api.php \
  includes/providers/class-aig-provider-claude.php \
  includes/providers/class-aig-provider-openai.php \
  includes/providers/class-aig-provider-ollama.php
```

- [ ] **Step 2: Verify no stale ACF_ references remain in includes/**

```bash
grep -rn 'ACF_\|acf_settings\|ai-content-forge' includes/
```

Expected: no output (empty)

- [ ] **Step 3: Syntax-check all includes files**

```bash
for f in includes/class-aig-*.php includes/providers/class-aig-*.php; do php -l "$f"; done
```

Expected: all files report `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "rename: update all includes/ PHP identifiers ACF → AIG"
```

---

### Task 3: Update admin PHP files (class names + HTML classes)

**Files:**
- Modify: `admin/class-aig-admin.php`
- Modify: `admin/class-aig-gutenberg.php`

- [ ] **Step 1: Bulk-replace identifiers in admin files**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin

# Class/constant names
sed -i 's/ACF_Admin/AIG_Admin/g; s/ACF_Gutenberg/AIG_Gutenberg/g; s/ACF_Settings/AIG_Settings/g; s/ACF_Generator/AIG_Generator/g; s/ACF_Rest_API/AIG_Rest_API/g; s/ACF_Provider_Claude/AIG_Provider_Claude/g; s/ACF_Provider_OpenAI/AIG_Provider_OpenAI/g; s/ACF_Provider_Ollama/AIG_Provider_Ollama/g; s/ACF_Provider/AIG_Provider/g' \
  admin/class-aig-admin.php admin/class-aig-gutenberg.php

sed -i 's/ACF_VERSION/AIG_VERSION/g; s/ACF_PLUGIN_DIR/AIG_PLUGIN_DIR/g; s/ACF_PLUGIN_URL/AIG_PLUGIN_URL/g' \
  admin/class-aig-admin.php admin/class-aig-gutenberg.php

# Text domain
sed -i "s/'ai-content-forge'/'ai-genie'/g" admin/class-aig-admin.php admin/class-aig-gutenberg.php

# Plugin display name in strings
sed -i "s/AI Content Forge/AI Genie/g" admin/class-aig-admin.php admin/class-aig-gutenberg.php

# Admin menu/page slug
sed -i "s/ai-content-forge/ai-genie/g" admin/class-aig-admin.php admin/class-aig-gutenberg.php

# CSS class prefix in HTML output: acf- → aig-
sed -i 's/acf-/aig-/g' admin/class-aig-admin.php

# Gutenberg script/style handles
sed -i "s/'acf-gutenberg'/'aig-gutenberg'/g" admin/class-aig-gutenberg.php

# Gutenberg localized JS object name
sed -i "s/'acfGutenberg'/'aigGutenberg'/g" admin/class-aig-gutenberg.php

# Gutenberg meta key
sed -i "s/_acf_meta_description/_aig_meta_description/g" admin/class-aig-gutenberg.php

# Settings group (used in settings_fields())
sed -i "s/acf_settings_group/aig_settings_group/g" admin/class-aig-admin.php
```

- [ ] **Step 2: Verify no stale references remain**

```bash
grep -rn 'ACF_\|acf-\|acf_\|ai-content-forge\|AI Content Forge\|acfGutenberg' admin/
```

Expected: no output

- [ ] **Step 3: Syntax-check admin files**

```bash
php -l admin/class-aig-admin.php && php -l admin/class-aig-gutenberg.php
```

Expected: `No syntax errors detected` for both

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "rename: update admin/ PHP identifiers and HTML class names ACF → AIG"
```

---

### Task 4: Update CSS

**Files:**
- Modify: `assets/css/admin.css`

- [ ] **Step 1: Bulk-replace all `.acf-` class prefixes**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
sed -i 's/\.acf-/.aig-/g; s/acf-/aig-/g' assets/css/admin.css
```

- [ ] **Step 2: Also update the CSS comment header**

Replace the first line `/* AI Content Forge — Admin Styles */` with:

```css
/* AI Genie — Admin Styles */
```

- [ ] **Step 3: Also update CSS variable prefix if present**

```bash
sed -i 's/--acf-/--aig-/g' assets/css/admin.css
```

- [ ] **Step 4: Verify no stale acf references**

```bash
grep -n 'acf' assets/css/admin.css
```

Expected: no output

- [ ] **Step 5: Commit**

```bash
git add assets/css/admin.css && git commit -m "rename: update admin CSS class prefix acf → aig"
```

---

### Task 5: Update admin JavaScript

**Files:**
- Modify: `assets/js/admin.js`

- [ ] **Step 1: Bulk-replace all acf- references**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
sed -i 's/acf-/aig-/g; s/acf_/aig_/g' assets/js/admin.js
```

- [ ] **Step 2: Verify no stale references**

```bash
grep -n 'acf' assets/js/admin.js
```

Expected: no output

- [ ] **Step 3: Commit**

```bash
git add assets/js/admin.js && git commit -m "rename: update admin JS selectors and keys acf → aig"
```

---

### Task 6: Update Gutenberg source and rebuild

**Files:**
- Modify: `gutenberg/src/index.js`
- Modify: `gutenberg/package.json`
- Regenerate: `gutenberg/build/*`

- [ ] **Step 1: Bulk-replace identifiers in Gutenberg source**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin

# JS global object
sed -i 's/acfGutenberg/aigGutenberg/g' gutenberg/src/index.js

# Plugin registration slug and sidebar names
sed -i "s/ai-content-forge/ai-genie/g" gutenberg/src/index.js

# Text domain in __() calls
# (already covered by previous sed since 'ai-content-forge' → 'ai-genie')

# Package name
sed -i 's/ai-content-forge-gutenberg/ai-genie-gutenberg/g' gutenberg/package.json
```

- [ ] **Step 2: Verify no stale references**

```bash
grep -n 'acfGutenberg\|ai-content-forge' gutenberg/src/index.js gutenberg/package.json
```

Expected: no output

- [ ] **Step 3: Rebuild the Gutenberg bundle**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin/gutenberg
npm run build
```

Expected: build succeeds, creates `build/index.js` and `build/index.asset.php`

- [ ] **Step 4: Commit**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
git add gutenberg/src/index.js gutenberg/package.json gutenberg/build/ && git commit -m "rename: update Gutenberg source and rebuild (acf → aig)"
```

---

### Task 7: Generate new mascot icon system

**Files:**
- Create: `images/icon-source.svg`
- Create: `images/icon-gutenberg-selected-src.svg`
- Create: `images/icon-gutenberg-unselected-src.svg`
- Regenerate: `images/plugin-icon.png`
- Regenerate: `images/plugin-icon-wpadmin_left_sidebar.png`
- Regenerate: `images/plugin-icon-gutenberg-selected.png`
- Regenerate: `images/plugin-icon-gutenberg-unselected.png`
- Modify: `admin/class-aig-admin.php` (inline SVG)

- [ ] **Step 1: Write the full-colour 256×256 mascot SVG**

Write to `images/icon-source.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
  <!-- AI Genie mascot icon — full colour on dark background -->
  <rect width="256" height="256" rx="54" fill="#064E3B"/>
  <g transform="translate(28,10) scale(2)">
    <!-- Wispy tail -->
    <path d="M50,108 Q36,110 34,102 Q32,93 40,91 Q44,89 50,93 Q56,89 60,91 Q68,93 66,102 Q64,110 50,108 Z" fill="#059669" opacity="0.55"/>
    <!-- Body -->
    <ellipse cx="50" cy="65" rx="28" ry="32" fill="#10B981"/>
    <!-- Arms raised -->
    <path d="M23,56 Q15,44 19,36 Q22,30 27,33 Q29,36 27,41 Q25,46 27,51" fill="none" stroke="#10B981" stroke-width="9" stroke-linecap="round"/>
    <ellipse cx="19.5" cy="35" rx="7" ry="7" fill="#10B981"/>
    <circle cx="20" cy="33" r="5" fill="#059669"/>
    <path d="M77,56 Q85,44 81,36 Q78,30 73,33 Q71,36 73,41 Q75,46 73,51" fill="none" stroke="#10B981" stroke-width="9" stroke-linecap="round"/>
    <ellipse cx="80.5" cy="35" rx="7" ry="7" fill="#10B981"/>
    <circle cx="80" cy="33" r="5" fill="#059669"/>
    <!-- Cheeks -->
    <ellipse cx="30" cy="70" rx="6" ry="4.5" fill="#6EE7B7" opacity="0.65"/>
    <ellipse cx="70" cy="70" rx="6" ry="4.5" fill="#6EE7B7" opacity="0.65"/>
    <!-- Eyes -->
    <ellipse cx="39" cy="61" rx="9.5" ry="11" fill="white"/>
    <ellipse cx="61" cy="61" rx="9.5" ry="11" fill="white"/>
    <circle cx="40.5" cy="62.5" r="6.5" fill="#064E3B"/>
    <circle cx="62.5" cy="62.5" r="6.5" fill="#064E3B"/>
    <circle cx="43" cy="60" r="2.5" fill="white"/>
    <circle cx="65" cy="60" r="2.5" fill="white"/>
    <!-- Smile -->
    <path d="M40,76 Q50,83 60,76" fill="none" stroke="#047857" stroke-width="3" stroke-linecap="round"/>
    <!-- Turban -->
    <ellipse cx="50" cy="35" rx="19" ry="9.5" fill="#F59E0B"/>
    <ellipse cx="50" cy="35" rx="13" ry="7" fill="#D97706"/>
    <ellipse cx="50" cy="29" rx="9" ry="6" fill="#F59E0B"/>
    <circle cx="50" cy="25" r="5" fill="#FCD34D"/>
    <circle cx="50" cy="25" r="3" fill="white"/>
    <!-- Sparkles -->
    <circle cx="88" cy="20" r="2.5" fill="#FCD34D" opacity="0.9"/>
    <circle cx="8" cy="24" r="2" fill="#FCD34D" opacity="0.8"/>
  </g>
</svg>
```

- [ ] **Step 2: Write the 48×48 Gutenberg selected SVG**

Write to `images/icon-gutenberg-selected-src.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
  <rect width="48" height="48" rx="10" fill="#064E3B"/>
  <g transform="translate(4,2) scale(0.4)">
    <ellipse cx="50" cy="65" rx="28" ry="32" fill="#10B981"/>
    <path d="M23,56 Q15,44 19,36 Q22,30 27,33" fill="none" stroke="#10B981" stroke-width="9" stroke-linecap="round"/>
    <circle cx="19" cy="33" r="7" fill="#10B981"/>
    <path d="M77,56 Q85,44 81,36 Q78,30 73,33" fill="none" stroke="#10B981" stroke-width="9" stroke-linecap="round"/>
    <circle cx="81" cy="33" r="7" fill="#10B981"/>
    <ellipse cx="39" cy="61" rx="9.5" ry="11" fill="white"/>
    <ellipse cx="61" cy="61" rx="9.5" ry="11" fill="white"/>
    <circle cx="41" cy="63" r="6.5" fill="#064E3B"/>
    <circle cx="63" cy="63" r="6.5" fill="#064E3B"/>
    <circle cx="43" cy="60" r="2.5" fill="white"/>
    <circle cx="65" cy="60" r="2.5" fill="white"/>
    <ellipse cx="50" cy="35" rx="19" ry="9.5" fill="#F59E0B"/>
    <ellipse cx="50" cy="35" rx="12" ry="7" fill="#D97706"/>
    <circle cx="50" cy="26" r="5" fill="#FCD34D"/>
    <circle cx="50" cy="26" r="2.5" fill="white"/>
  </g>
</svg>
```

- [ ] **Step 3: Write the 48×48 Gutenberg unselected SVG**

Write to `images/icon-gutenberg-unselected-src.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
  <rect width="48" height="48" rx="10" fill="#E8EDF5"/>
  <g transform="translate(4,2) scale(0.4)">
    <ellipse cx="50" cy="65" rx="28" ry="32" fill="#8FA3BE"/>
    <path d="M23,56 Q15,44 19,36 Q22,30 27,33" fill="none" stroke="#8FA3BE" stroke-width="9" stroke-linecap="round"/>
    <circle cx="19" cy="33" r="7" fill="#8FA3BE"/>
    <path d="M77,56 Q85,44 81,36 Q78,30 73,33" fill="none" stroke="#8FA3BE" stroke-width="9" stroke-linecap="round"/>
    <circle cx="81" cy="33" r="7" fill="#8FA3BE"/>
    <ellipse cx="39" cy="61" rx="9.5" ry="11" fill="white"/>
    <ellipse cx="61" cy="61" rx="9.5" ry="11" fill="white"/>
    <circle cx="41" cy="63" r="6.5" fill="#A0B4CC"/>
    <circle cx="63" cy="63" r="6.5" fill="#A0B4CC"/>
    <ellipse cx="50" cy="35" rx="19" ry="9.5" fill="#A0B4CC"/>
    <ellipse cx="50" cy="35" rx="12" ry="7" fill="#8FA3BE"/>
    <circle cx="50" cy="26" r="5" fill="#C8D5E8"/>
  </g>
</svg>
```

- [ ] **Step 4: Generate all PNGs from SVGs**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin/images
rsvg-convert -w 256 -h 256 icon-source.svg -o plugin-icon.png
rsvg-convert -w 64 -h 64 icon-source.svg -o plugin-icon-wpadmin_left_sidebar.png
rsvg-convert -w 48 -h 48 icon-gutenberg-selected-src.svg -o plugin-icon-gutenberg-selected.png
rsvg-convert -w 48 -h 48 icon-gutenberg-unselected-src.svg -o plugin-icon-gutenberg-unselected.png
```

- [ ] **Step 5: Update the inline admin sidebar SVG in `admin/class-aig-admin.php`**

Find the `menu_icon_data_uri()` method and replace the SVG string with this genie-silhouette version (uses `currentColor` for WordPress theme compatibility):

```php
    private static function menu_icon_data_uri(): string {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
  <ellipse cx="10" cy="13.5" rx="5.5" ry="6" fill="currentColor"/>
  <path d="M4.5,11 Q2.5,8 3.5,6 Q4.5,4.5 6,5.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
  <circle cx="3.5" cy="5.5" r="2" fill="currentColor"/>
  <path d="M15.5,11 Q17.5,8 16.5,6 Q15.5,4.5 14,5.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
  <circle cx="16.5" cy="5.5" r="2" fill="currentColor"/>
  <ellipse cx="10" cy="7" rx="5" ry="2.5" fill="currentColor"/>
  <ellipse cx="10" cy="5.5" rx="3" ry="2" fill="currentColor"/>
  <circle cx="10" cy="4" r="1.5" fill="currentColor" opacity="0.9"/>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
```

- [ ] **Step 6: Clean up old icon source SVGs from earlier experiments**

```bash
rm -f images/icon-menu-20.svg
```

- [ ] **Step 7: Commit**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
git add images/ admin/class-aig-admin.php && git commit -m "brand: replace icon system with AI Genie mascot (emerald teal genie)"
```

---

### Task 8: Update build script, README, and project docs

**Files:**
- Modify: `scripts/build-release.sh`
- Modify: `README.md`
- Modify: `CLAUDE.md`
- Modify: `AGENTS.md` (if references exist)

- [ ] **Step 1: Update build script**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
sed -i 's/ai-content-forge/ai-genie/g' scripts/build-release.sh
```

Verify:

```bash
grep 'ai-content-forge' scripts/build-release.sh
```

Expected: no output

- [ ] **Step 2: Rewrite README.md with branding**

First, bulk-replace the name:

```bash
sed -i 's/AI Content Forge/AI Genie/g; s/ai-content-forge/ai-genie/g' README.md
```

Then manually add the mascot and brand elements to the top of README.md. Insert right after the `# AI Genie` heading:

```markdown
<p align="center">
  <img src="images/plugin-icon.png" alt="AI Genie mascot" width="128" height="128">
</p>

<p align="center">
  <strong>Your AI genie for WordPress content.</strong><br>
  AI-powered content generation via Claude, OpenAI, or Ollama.
</p>

### Brand Colours

| Swatch | Name | Hex |
|--------|------|-----|
| 🟢 | Genie Teal | `#10B981` |
| 🟤 | Deep Forest | `#064E3B` |
| 🟡 | Turban Gold | `#F59E0B` |
| 🟢 | Wisp Mint | `#6EE7B7` |
```

- [ ] **Step 3: Update CLAUDE.md**

```bash
sed -i 's/AI Content Forge/AI Genie/g; s/ai-content-forge/ai-genie/g; s/ACF_VERSION/AIG_VERSION/g' CLAUDE.md
```

- [ ] **Step 4: Update AGENTS.md if it has references**

```bash
grep -l 'ai-content-forge\|AI Content Forge\|ACF_' AGENTS.md 2>/dev/null && \
  sed -i 's/AI Content Forge/AI Genie/g; s/ai-content-forge/ai-genie/g; s/ACF_/AIG_/g' AGENTS.md || true
```

- [ ] **Step 5: Final global stale-reference check**

```bash
grep -rn 'ACF_\|acf_\|acf-\|ai-content-forge\|AI Content Forge' \
  --include='*.php' --include='*.js' --include='*.css' --include='*.sh' --include='*.md' \
  --exclude-dir=node_modules --exclude-dir=.git --exclude-dir='gutenberg/build' \
  --exclude-dir='.superpowers' --exclude-dir='ollama-worker-proxy-setup-*' \
  --exclude='*.zip' \
  . | grep -v 'docs/superpowers/'
```

Expected: no output (all stale references eliminated). Ignore docs/superpowers/ which contains historical spec text.

- [ ] **Step 6: Commit**

```bash
git add scripts/ README.md CLAUDE.md AGENTS.md && git commit -m "docs: update build script, README, CLAUDE.md for AI Genie rename"
```

---

### Task 9: Rename GitHub repo, build release, and publish

**Files:**
- Produce: `ai-genie-v3.0.0.zip`

- [ ] **Step 1: Rename the GitHub repository**

```bash
cd /home/p/Desktop/ai-content-forge/ai-content-forge-plugin
gh repo rename wordpress-ai-genie-plugin --yes
```

- [ ] **Step 2: Update the git remote to match**

```bash
git remote set-url origin https://github.com/charettep/wordpress-ai-genie-plugin.git
```

- [ ] **Step 3: Push all commits to main**

```bash
git push origin main
```

- [ ] **Step 4: Build the release zip**

```bash
./scripts/build-release.sh
```

Expected: `Built ./ai-genie-v3.0.0.zip`

- [ ] **Step 5: Create the GitHub release with the zip**

```bash
gh release create v3.0.0 ./ai-genie-v3.0.0.zip \
  --title "v3.0.0 — AI Genie Rebrand" \
  --notes "$(cat <<'EOF'
## AI Genie v3.0.0

**Full rebrand from AI Content Forge → AI Genie.**

This is a clean-break rename. Existing installations should deactivate AI Content Forge and install AI Genie fresh. Settings will need to be re-entered.

### What changed
- Plugin renamed to **AI Genie** across all identifiers, slugs, and REST endpoints
- New mascot icon: emerald teal genie character
- REST namespace changed from `ai-content-forge/v1` to `ai-genie/v1`
- All PHP class/constant prefixes changed from `ACF_` to `AIG_`
- All CSS/JS selectors changed from `acf-` to `aig-`
EOF
)"
```

- [ ] **Step 6: Verify the release zip installs cleanly**

Download the zip from the GitHub release and install it via WordPress wp-admin → Plugins → Add New → Upload Plugin. Verify:
1. The plugin activates without errors
2. The genie mascot icon appears in the admin sidebar
3. The settings page loads at wp-admin → AI Genie
4. Provider connections still work after re-entering API keys

- [ ] **Step 7: Commit the zip to the repo (per existing convention)**

```bash
git add ai-genie-v3.0.0.zip && git commit -m "Release v3.0.0" && git push origin main
```
