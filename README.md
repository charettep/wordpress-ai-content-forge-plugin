# AI Content Forge

AI Content Forge is a WordPress plugin for generating editorial content with Anthropic Claude, OpenAI, or Ollama. It adds:

- a settings screen under `Settings -> AI Content Forge`
- a Gutenberg sidebar for on-demand generation inside the block editor
- REST endpoints for generation, provider status, and model discovery

The current packaged release is `v2.2.0`.

## Features

- Generate full post body HTML
- Generate SEO titles
- Generate meta descriptions
- Generate excerpts
- Choose a global default provider
- Override the provider per generation run
- Control shared generation defaults such as `max_tokens` and `temperature`
- Auto-check OpenAI and Claude connectivity from wp-admin as soon as an API key is present
- Auto-load available OpenAI and Claude models into a dropdown after a successful connection check

## Requirements

- WordPress `6.4+`
- PHP `8.1+`
- Node `18+` only when building from source
- At least one configured provider

## Release Install

Use the packaged zip if you just want to install the plugin in WordPress.

1. Download the latest versioned package such as `ai-content-forge-v2.2.0.zip` from the latest GitHub release.
2. In WordPress admin, go to `Plugins -> Add Plugin -> Upload Plugin`.
3. Upload the versioned plugin archive.
4. Click `Install Now`, then `Activate Plugin`.
5. Open `Settings -> AI Content Forge` and configure at least one provider.

### WordPress Playground

This plugin can be installed in WordPress Playground with the same upload flow:

1. Open your Playground site.
2. Go to `Plugins -> Add Plugin -> Upload Plugin`.
3. Upload the versioned plugin archive.
4. Activate the plugin.

If you hit a fatal error while activating an older package, use `v2.0.1` or later. Earlier broken archives omitted required admin files from the zip.

## Build From Source

Clone the repo and build the Gutenberg assets before packaging a release.

```bash
git clone https://github.com/charettep/wordpress-ai-content-forge-plugin.git
cd wordpress-ai-content-forge-plugin/gutenberg
npm install
npm run build
cd ..
./build-release.sh
```

That produces:

- `gutenberg/build/index.js`
- `gutenberg/build/index.asset.php`
- `ai-content-forge-vX.Y.Z.zip`

## Local Development Install

To test from source without using the release zip:

1. Build the Gutenberg assets.
2. Copy the plugin directory into `wp-content/plugins/ai-content-forge`.
3. Activate `AI Content Forge` in wp-admin.

Example:

```bash
cp -R /path/to/wordpress-ai-content-forge-plugin /path/to/wp-content/plugins/ai-content-forge
```

The plugin directory name inside WordPress must be `ai-content-forge` so the main file path resolves to:

```text
wp-content/plugins/ai-content-forge/ai-content-forge.php
```

## Configuration

Open `Settings -> AI Content Forge`.

### Default Provider

Used whenever the generator UI does not specify a provider override.

### Anthropic Claude

- `API Key`: Anthropic API key
- `Model`: automatically populated from the Anthropic Models API after the API key is detected and validated

### OpenAI

- `API Key`: OpenAI API key
- `Model`: automatically populated from the OpenAI Models API after the API key is detected and validated

### Ollama

- `Base URL`: defaults to `http://localhost:11434`
- `Model`: defaults to `llama3`

Important:

- `localhost` is resolved from the WordPress runtime, not from your browser tab.
- In Docker, `localhost` means the container.
- In Playground, Ollama connectivity is generally not a practical target unless the runtime can reach your Ollama host.

### Generation Defaults

- `Max Tokens`: global token budget; shorter content types are capped lower internally
- `Temperature`: global creativity control

### Live Provider Status

- Anthropic Claude and OpenAI are checked automatically after the API key field becomes non-empty
- a green `Connected` status appears beside the provider heading after a successful check
- the `Model` dropdown is refreshed with the models returned by that provider API
- the selected model becomes the saved active model used for later generation after you click `Save Settings`
- Ollama still uses a manual base URL and model entry because it does not use an API key flow

## User Guide

### Settings Screen

Use the settings screen to:

- store provider credentials
- choose the default provider
- set baseline generation behavior
- confirm provider connectivity and choose the exact model before editing content

### Gutenberg Sidebar

Open the block editor for a post or page, then open `AI Content Forge` from the editor sidebar / more-menu entry.

Available controls:

- `Content Type`
- `AI Provider`
- `Keywords / Topic hints`
- `Tone`
- `Language`

After generation, the sidebar exposes:

- `Copy`
- `Apply to Post`

### What Each Content Type Does

#### Post Content

- Generates HTML intended for the block editor
- Applies the result by converting the generated HTML into native Gutenberg blocks such as paragraphs, headings, lists, and code blocks when possible
- Falls back to a single `Custom HTML` block only if Gutenberg cannot parse the generated markup

This is destructive to the current editor canvas, so generate carefully if the post already contains work you want to keep.

#### SEO Title

- Generates a short SEO-style title
- Applies the result by overwriting the current post title

#### Excerpt

- Generates a short excerpt
- Applies the result by overwriting the current post excerpt

#### Meta Description

- Generates a meta description
- Applies the result to `_acf_meta_description` in editor state

Important:

- this plugin does not register or display that meta key on the frontend by itself
- you need a companion integration, SEO plugin mapping, or custom code if you want the meta description persisted and surfaced elsewhere

## Prompt Behavior

The generator builds prompts from:

- post title
- keywords
- tone
- language
- existing content
- post type

Behavior by type:

- `post_content`: aims for a structured article with headings and HTML output
- `seo_title`: aims for a 50 to 60 character title
- `meta_description`: aims for a 150 to 160 character description
- `excerpt`: aims for a 40 to 55 word excerpt

## REST API

Namespace:

```text
/wp-json/ai-content-forge/v1
```

All endpoints require:

- a logged-in user with `edit_posts`
- a valid REST nonce

### `POST /generate`

Parameters:

| Parameter | Required | Notes |
| --- | --- | --- |
| `type` | yes | `post_content`, `seo_title`, `meta_description`, `excerpt` |
| `provider` | no | empty string uses the global default |
| `title` | no | post title context |
| `keywords` | no | keyword hints |
| `tone` | no | defaults to `professional` |
| `language` | no | defaults to `English` |
| `existing_content` | no | existing body content for context |
| `post_type` | no | defaults to `post` |

Successful response:

```json
{
  "success": true,
  "result": "Generated text here"
}
```

### `POST /test-provider`

Parameters:

| Parameter | Required | Notes |
| --- | --- | --- |
| `provider` | yes | `claude`, `openai`, or `ollama` |

For `claude` and `openai`, this now validates credentials by loading the provider's model list instead of issuing a throwaway generation request.

### `POST /sync-provider`

This endpoint is used by the wp-admin settings screen for live API key validation and model discovery.

Permissions:

- logged-in user with `manage_options`
- valid REST nonce

Parameters:

| Parameter | Required | Notes |
| --- | --- | --- |
| `provider` | yes | `claude` or `openai` |
| `api_key` | yes | unsaved API key currently typed in the form |
| `current_model` | no | currently selected or previously saved model |

### `GET /providers`

Returns the provider list with:

- `id`
- `label`
- `is_configured`
- `is_default`

## Packaging Releases

Use the release script from the plugin root:

```bash
./build-release.sh
```

The script:

- requires the Gutenberg build to exist first
- stages the plugin under the correct runtime folder name: `ai-content-forge`
- creates a clean versioned archive such as `ai-content-forge-v2.2.0.zip`
- refuses to overwrite an existing archive for the same version
- excludes development-only directories such as `node_modules`

## Repository Layout

```text
ai-content-forge.php                 Plugin bootstrap and version headers
admin/class-acf-admin.php           Settings screen
admin/class-acf-gutenberg.php       Gutenberg asset loader
assets/css/admin.css                Settings page styles
assets/js/admin.js                  Settings page behavior
includes/class-acf-settings.php     Option storage and sanitization
includes/class-acf-provider.php     Provider base class
includes/class-acf-generator.php    Prompt construction and dispatch
includes/class-acf-rest-api.php     REST routes
includes/providers/                 Claude, OpenAI, Ollama drivers
gutenberg/src/index.js              Sidebar source
gutenberg/build/                    Compiled editor assets
build-release.sh                    Release packaging script
```

## Troubleshooting

### Plugin activation fails

Check that the installed archive contains:

- `admin/class-acf-admin.php`
- `admin/class-acf-gutenberg.php`
- `includes/...`
- `ai-content-forge.php`

The `v2.0.1` package fixes the broken zip layout that caused activation fatals in earlier builds.

### Gutenberg sidebar does not appear

Confirm the compiled assets exist:

```text
gutenberg/build/index.js
gutenberg/build/index.asset.php
```

If they are missing, rebuild with:

```bash
cd gutenberg
npm install
npm run build
```

### Provider connection fails

Check:

- API key correctness
- whether the provider account exposes at least one supported text model
- outbound network access from the WordPress runtime
- Ollama reachability from the PHP runtime

If OpenAI or Claude connects successfully, the provider header will show `Connected` and the `Model` field will switch to a populated dropdown.

### Generated HTML is not block-native

`Apply to Post` uses Gutenberg's raw HTML conversion pipeline. If output still lands in a `Custom HTML` block, the generated markup likely contains structures Gutenberg cannot safely convert into native blocks.

## Changelog

### `v2.2.0`

- replaced the manual Claude and OpenAI `Test Connection` buttons with live API key validation
- added inline `Connected` status badges near the provider headings in wp-admin
- changed the Claude and OpenAI model fields from free text to provider-populated dropdowns
- added a new admin REST flow for unsaved API key validation and model discovery
- updated OpenAI generation to support modern selected models through model-aware request handling
- changed release packaging to versioned zip filenames such as `ai-content-forge-v2.2.0.zip`

### `v2.1.0`

- changed `Apply to Post` for `Post Content` to convert generated HTML into native Gutenberg blocks
- kept a `Custom HTML` fallback only for unparseable markup

### `v2.0.1`

- fixed the broken release packaging that omitted required admin files
- renamed the REST namespace constant to avoid a reserved keyword collision
- made Gutenberg asset loading conditional on compiled build files
- added a deterministic release packaging script
- removed an accidentally committed secret from the project documentation

## License

GPL-2.0+
