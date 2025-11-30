#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

if [[ ! -f docker-compose.yaml ]]; then
  echo "✗ docker-compose.yaml not found. Run from the repository root." >&2
  exit 1
fi

# Ensure .env exists with sensible development defaults
ENV_FILE="${REPO_ROOT}/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  cat > "$ENV_FILE" <<'EOF'
DB_NAME=wordpress
DB_USER=wordpress
DB_PASSWORD=wordpress
DB_ROOT_PASSWORD=root
TZ=UTC
EOF
  echo "→ Created .env with default development credentials."
fi

echo "→ Starting containers (detached)…"
docker compose up -d

# Optionally wait for WordPress HTTP to respond
echo "→ Waiting for WordPress at http://localhost:8000 …"
if command -v curl >/dev/null 2>&1; then
  for _ in {1..60}; do
    if curl -fsS http://localhost:8000/ >/dev/null 2>&1; then
      break
    fi
    sleep 2
  done
fi

echo "→ Checking if WordPress is installed…"
if docker compose run --rm \
  -e HOME=/var/www/html \
  -e WP_CLI_CACHE_DIR=/tmp/wp-cli-cache \
  cli wp core is-installed >/dev/null 2>&1; then
  echo "✓ WordPress already installed."
else
  echo "→ Running first-time WordPress setup…"
  bash "${REPO_ROOT}/first_install.sh"
fi

echo "✓ Dev environment is up."
echo "   WordPress:  http://localhost:8000"
echo "   phpMyAdmin: http://localhost:8080"
echo "   MailHog:    http://localhost:8025"


