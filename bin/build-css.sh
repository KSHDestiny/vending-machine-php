#!/bin/sh
# Builds public/assets/app.css with the Tailwind CSS standalone CLI (no Node needed).
# Downloads the pinned release once into .tools/ and checks its SHA-256 before use.
set -eu

VERSION="v4.3.5"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOLS="$ROOT/.tools"

case "$(uname -s)-$(uname -m)" in
    Linux-x86_64) ASSET="tailwindcss-linux-x64" ;;
    Linux-aarch64 | Linux-arm64) ASSET="tailwindcss-linux-arm64" ;;
    Darwin-arm64) ASSET="tailwindcss-macos-arm64" ;;
    Darwin-x86_64) ASSET="tailwindcss-macos-x64" ;;
    *) echo "Unsupported platform: $(uname -s)-$(uname -m)" >&2; exit 1 ;;
esac

BIN="$TOOLS/$ASSET-$VERSION"
if [ ! -x "$BIN" ]; then
    mkdir -p "$TOOLS"
    BASE="https://github.com/tailwindlabs/tailwindcss/releases/download/$VERSION"
    curl -fsSL "$BASE/$ASSET" -o "$BIN.download"
    EXPECTED="$(curl -fsSL "$BASE/sha256sums.txt" | awk -v asset="$ASSET" '$2 == asset || $2 == "./"asset {print $1}')"
    ACTUAL="$(sha256sum "$BIN.download" 2>/dev/null | cut -d' ' -f1 || shasum -a 256 "$BIN.download" | cut -d' ' -f1)"
    if [ -z "$EXPECTED" ] || [ "$EXPECTED" != "$ACTUAL" ]; then
        rm -f "$BIN.download"
        echo "Checksum mismatch for $ASSET $VERSION" >&2
        exit 1
    fi
    mv "$BIN.download" "$BIN"
    chmod +x "$BIN"
fi

cd "$ROOT"
"$BIN" --input resources/css/app.css --output public/assets/app.css --minify
