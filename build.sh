#!/usr/bin/env bash
set -euo pipefail

# --- Config ---
PLUGIN_SLUG="store-notice-plus"

# --- Requirements ---
for cmd in php zip rsync; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "✗ Missing dependency: $cmd" >&2; exit 1; }
done

# --- Locate plugin root (supports running from plugin root or its parent) ---
if [[ -f "${PLUGIN_SLUG}.php" && -d "includes" && -d "assets" ]]; then
  SRC_DIR="$(pwd)"
elif [[ -d "${PLUGIN_SLUG}" && -f "${PLUGIN_SLUG}/${PLUGIN_SLUG}.php" ]]; then
  SRC_DIR="$(cd "${PLUGIN_SLUG}" && pwd)"
else
  echo "✗ Run from either the plugin root or the parent directory containing '${PLUGIN_SLUG}/'." >&2
  exit 1
fi

# --- Derive version from plugin header or constant as fallback ---
HEADER_VERSION=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\(.*\)$/\1/p' "${SRC_DIR}/${PLUGIN_SLUG}.php" | head -n1 | tr -d '\r')
CONST_VERSION=$(sed -n "s/^[[:space:]]*define[(][[:space:]]*'SNP_VERSION',[[:space:]]*'\(.*\)'.*$/\1/p" "${SRC_DIR}/${PLUGIN_SLUG}.php" | head -n1 | tr -d '\r')
VERSION="${HEADER_VERSION:-$CONST_VERSION}"
: "${VERSION:=dev}"

# --- Paths ---
OUT_DIR="$(cd "${SRC_DIR}/.." && pwd)/dist"
STAGE="${OUT_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${OUT_DIR}/${PLUGIN_SLUG}-v${VERSION}.zip"

# --- Clean stage ---
rm -rf "${STAGE}" "${ZIP_PATH}"
mkdir -p "${STAGE}"

# --- Copy with excludes ---
RSYNC_EXCLUDES=(
  "--exclude" ".git/"
  "--exclude" ".github/"
  "--exclude" ".idea/"
  "--exclude" ".vscode/"
  "--exclude" "node_modules/"
  "--exclude" "build/"
  "--exclude" "dist/"
  "--exclude" "*.map"
  "--exclude" ".DS_Store"
  "--exclude" "__MACOSX"
)
echo "→ Staging files…"
rsync -a "${RSYNC_EXCLUDES[@]}" "${SRC_DIR}/" "${STAGE}/"

# --- PHP syntax check ---
echo "→ PHP lint…"
while IFS= read -r -d '' file; do
  php -l "$file" > /dev/null
done < <(find "${STAGE}" -type f -name "*.php" -print0)
echo "✓ PHP OK"

# --- Zip ---
echo "→ Creating zip…"
mkdir -p "${OUT_DIR}"
( cd "${OUT_DIR}" && zip -r -q "${ZIP_PATH##*/}" "${PLUGIN_SLUG}" )
echo "✓ Built ${ZIP_PATH}"

# --- Done ---
echo "Upload this file in WP Admin → Plugins → Add New → Upload Plugin:"
echo "  ${ZIP_PATH}"
