# Claude Code Instructions

These instructions apply to the `ai-content-forge-plugin/` repository and override Claude Code defaults.

## Release Workflow

When completing any user-requested plugin update, execute the full release end-to-end:

1. **Bump version** exactly as specified by the user (or use patch/minor/major as appropriate).
   - Update `ai-content-forge.php` (plugin header + `ACF_VERSION` constant)
   - Update `gutenberg/package.json`
2. **Rebuild Gutenberg assets** if any file under `gutenberg/src/` changed.
   - Host Node is too new — always use the Docker container:
     ```bash
     docker run --rm -v "$PWD":/work node:20 bash -lc \
       'cp -R /work /tmp/b; cd /tmp/b/gutenberg; npm install --no-fund --no-audit; npm run build; cp -R build /work/gutenberg/'
     ```
3. **PHP syntax check** every modified `.php` file:
   ```bash
   docker run --rm -v "$PWD":/work -w /work wordpress:latest php -l <file>
   ```
4. **Package** using the build script (reads version from plugin header automatically):
   ```bash
   ./build-release.sh
   ```
   Output: `ai-content-forge-vX.Y.Z.zip` — never overwrites existing zips.
5. **Commit** source changes (zips are gitignored — uploaded as release assets only).
6. **Push** to `https://github.com/charettep/wordpress-ai-content-forge-plugin`.
7. **Create GitHub release** `vX.Y.Z` with the zip as an asset and proper release notes.
8. **Test in WordPress Playground** using Chrome DevTools MCP:
   - Open `https://playground.wordpress.net/?site-slug=curious-vintage-garden`
   - Navigate to `/wp-admin/plugin-install.php?tab=upload`
   - Upload the new versioned zip
   - Click through the update/install flow and activate
   - Navigate to `/wp-admin/admin.php?page=ai-content-forge` and verify the settings page loads
   - Open the Gutenberg editor on a post and verify the AI Content Forge sidebar appears
   - Trigger a generation with the affected provider/feature and confirm it succeeds
   - Report what was tested and what the result was

## Packaging Rules

- Release zips are named `ai-content-forge-vX.Y.Z.zip` — never overwrite existing ones.
- The zip must include: `ai-content-forge.php`, `admin/`, `assets/`, `includes/`, `gutenberg/build/`, `README.md`.
- Do not include `gutenberg/node_modules/`, `gutenberg/src/`, or dev files.

## Git Safety

- Never discard uncommitted changes unless explicitly asked.
- Stage specific files — avoid `git add -A` which can accidentally include sensitive files.
- If worktree has unrelated changes, include them intentionally and describe what was released.

## WordPress Playground Notes

- Site slug: `curious-vintage-garden`
- "Cookie check failed" on the settings page is a Playground session issue (stale nonce after restore) — refresh the page inside Playground to resolve.
- The browser session at `~/.claude/chrome-profile-persistent` persists logins; the Playground site should already be accessible.

## Project Structure

```
ai-content-forge.php              Plugin boot, ACF_VERSION, file includes
includes/class-acf-settings.php   Option storage, sanitization
includes/class-acf-provider.php   Abstract base: http_get, http_post, normalize_response
includes/class-acf-generator.php  Prompt builder, provider dispatcher
includes/class-acf-rest-api.php   REST: /generate, /test-provider, /sync-provider, /providers
includes/providers/
  class-acf-provider-claude.php   Anthropic provider (Messages API)
  class-acf-provider-openai.php   OpenAI provider (Chat + Responses APIs)
  class-acf-provider-ollama.php   Ollama local LLM
admin/class-acf-admin.php         Settings page PHP render
admin/class-acf-gutenberg.php     Gutenberg asset loader
gutenberg/src/index.js            React sidebar (build source)
gutenberg/build/                  Compiled assets (index.js, index.asset.php)
assets/css/admin.css
assets/js/admin.js
build-release.sh                  Packaging script
AGENTS.md                         Instructions for Codex/OpenAI agents
```

## Key Technical Notes

- OpenAI: `gpt-5`, `gpt-4.1`, `gpt-4o`, `o1`, `o3`, `o4` use the Responses API (`/v1/responses`).
  - gpt-5 does NOT support `temperature` — excluded from request body via `supports_temperature()`.
  - Only send `model`, `input`, `max_output_tokens` as the minimum Responses API body; add `reasoning` only for gpt-5 family using valid effort values: `low`, `medium`, `high`.
- REST auth: `X-WP-Nonce` with `wp_rest` nonce; nonce is localized via `wp_localize_script`.
- `post_with_parameter_fallback` retries a request once after removing a named parameter if the API returns an "unsupported parameter" error for it.
