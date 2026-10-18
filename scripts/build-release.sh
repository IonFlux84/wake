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

# Development files are not needed on a server.
rm -rf "$target/.github" "$target/wake/tests" "$target/wake/resources/js" \
       "$target/wake/package.json" "$target/wake/package-lock.json" "$target/wake/vite.config.js" \
       "$target/wake/phpunit.xml" "$target/wake/pint.json" "$target/scripts" "$target/AGENTS.md" "$target/CLAUDE.md"

mkdir -p "$root/dist"
( cd "$work" && zip -qr "$root/dist/wake-$version.zip" "wake-$version" )
( cd "$root/dist" && shasum -a 256 "wake-$version.zip" > "wake-$version.zip.sha256" )

echo "dist/wake-$version.zip"
