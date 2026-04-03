#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# docker-setup.sh — One-time WordPress installation for local dev
#
# Run this ONCE after the first `docker compose up -d`:
#   chmod +x docker-setup.sh && ./docker-setup.sh
#
# Safe to re-run: skips install if WordPress is already configured.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

WP="docker compose run --rm wpcli wp"

# ── 1. Wait for WordPress container to be healthy ─────────────────────────────
echo "⏳  Waiting for WordPress to be ready..."
until docker compose exec -T wordpress curl -sf http://localhost/wp-login.php > /dev/null 2>&1; do
  printf "."
  sleep 3
done
echo " ✓"

# ── 2. Install WordPress (idempotent) ─────────────────────────────────────────
if $WP core is-installed 2>/dev/null; then
  echo "✓  WordPress already installed — skipping core install."
else
  echo "📦  Installing WordPress core..."
  $WP core install \
    --url="http://localhost:8082" \
    --title="AI Content Forge Dev" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=dev@dev.local \
    --skip-email
  echo "✓  Core installed."
fi

# ── 3. Activate the plugin ────────────────────────────────────────────────────
echo "🔌  Activating plugin..."
$WP plugin activate ai-content-forge
echo "✓  Plugin active."

# ── 4. Nice-to-have tweaks for local dev ─────────────────────────────────────
$WP rewrite structure '/%postname%/' --hard
$WP option update blogdescription "Local dev instance"

# Create a test post to edit in Gutenberg
POST_ID=$($WP post create \
  --post_title="Test post for AI Content Forge" \
  --post_status=draft \
  --post_type=post \
  --porcelain 2>/dev/null || echo "")
if [ -n "$POST_ID" ]; then
  echo "✓  Draft post created (ID: $POST_ID)."
fi

# ── 5. Done ───────────────────────────────────────────────────────────────────
cat <<EOF

╔══════════════════════════════════════════════════════════╗
║  ✅  Local WordPress ready!                              ║
╠══════════════════════════════════════════════════════════╣
║  Site:       http://localhost:8082                       ║
║  Admin:      http://localhost:8082/wp-admin              ║
║  Login:      admin / password                            ║
║  phpMyAdmin: http://localhost:8081                       ║
╠══════════════════════════════════════════════════════════╣
║  Plugin settings:                                        ║
║  http://localhost:8082/wp-admin/admin.php?page=ai-content-forge
╚══════════════════════════════════════════════════════════╝

Tip: keep the Gutenberg watcher running for live JS rebuilds:
  docker run --rm -it -v "$PWD":/work -w /work/gutenberg \\
    node:20 bash -lc 'npm install --package-lock=false --no-fund --no-audit && npm run start'
EOF
