# Project Instructions

These instructions apply to the entire `ai-content-forge-plugin/` repository.

## Release Workflow

- Do not overwrite previous packaged plugin archives.
- Every packaged release zip must include the plugin version in the filename using this format:
  - `ai-content-forge-vX.Y.Z.zip`
- Keep older release zip files in the repo directory unless the user explicitly asks to remove them.

## When Completing a User-Requested Plugin Update

When the user asks for a new plugin version, complete the release end-to-end unless blocked:

1. Bump the version number exactly as the user specifies.
2. Update all relevant version references, including:
   - `ai-content-forge.php`
   - `gutenberg/package.json`
   - any generated build metadata that must stay in sync
3. Rebuild the Gutenberg assets when source changes require it.
4. Package the plugin as a versioned zip file using the required naming format.
5. Update `README.md` so installation instructions, usage notes, and release references match the new version.
6. Stage the release changes.
7. Commit the release changes with a clear versioned commit message.
8. Push the changes to the public GitHub repository:
   - `https://github.com/charettep/wordpress-ai-content-forge-plugin`
9. Create or update the GitHub release for that version and upload the packaged zip asset.
10. Write proper release notes that summarize the actual user-facing changes and fixes for that version.

## Packaging Expectations

- Prefer the repository build script when packaging.
- The packaged zip must include the files needed for activation and normal plugin operation.
- Verify the package before finishing when practical.

## Git Safety

- Never discard user changes unless the user explicitly asks.
- If the worktree contains unrelated changes, avoid reverting them.
- If a requested release depends on changes already present in the worktree, include them intentionally and describe what was released.
