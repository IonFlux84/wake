#!/usr/bin/env bash
# Builds the installation ZIP: everything a site needs, with wake/vendor
# included, so installing is unzipping and uploading. Usage:
#   scripts/build-release.sh [version]     (default: version from wake/app/Wake.php)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
version="${1:-$(sed -n "s/.*VERSION = '\(.*\)';/\1/p" "$root/wake/app/Wake.php")}"
work="$(mktemp -d)"
target="$work/wake-$version"
trap 'rm -rf "$work"' EXIT

echo "Building Wake $version"

# Tracked files only; export-ignore in .gitattributes leaves out design/ and docs/internal/.
mkdir -p "$target"
git -C "$root" archive HEAD | tar -x -C "$target"

# Production dependencies, optimised autoloader.
composer --working-dir="$target/wake" install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

# Composer runs artisan, and Wake writes a .env with a fresh key on its first
# start. That key must never ship: every site creates its own on first visit.
rm -f "$target/.env"

# Development files are not needed on a server.
rm -rf "$target/.github" "$target/wake/tests" "$target/wake/resources/js" \
       "$target/wake/package.json" "$target/wake/package-lock.json" "$target/wake/vite.config.js" \
       "$target/wake/phpunit.xml" "$target/wake/pint.json" "$target/scripts" "$target/AGENTS.md" "$target/CLAUDE.md"

mkdir -p "$root/dist"
# zip -r adds to an existing archive, so start from scratch.
rm -f "$root/dist/wake-$version.zip" "$root/dist/wake-$version.zip.sha256"
test ! -e "$target/.env" || { echo "A .env would be shipped" >&2; exit 1; }
( cd "$work" && zip -qr "$root/dist/wake-$version.zip" "wake-$version" )
( cd "$root/dist" && shasum -a 256 "wake-$version.zip" > "wake-$version.zip.sha256" )

echo "dist/wake-$version.zip"
