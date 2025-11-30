#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

if [[ ! -f docker-compose.yaml ]]; then
  echo "✗ docker-compose.yaml not found. Run from the repository root." >&2
  exit 1
fi

PURGE="${1:-}"
echo "→ Stopping containers…"
if [[ "$PURGE" == "--purge" || "$PURGE" == "-p" ]]; then
  docker compose down -v --remove-orphans
  echo "✓ Stopped containers and removed volumes."
else
  docker compose down --remove-orphans
  echo "✓ Stopped containers (volumes preserved)."
fi


