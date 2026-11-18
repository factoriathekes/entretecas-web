#!/usr/bin/env bash
# Regenera src/assets/fonts/literata-opsz.woff2 (Literata variable, wght 400-700 y opsz) solo con los caracteres de
# scripts/fuente-caracteres.txt (español completo + puntuación + los que aparecen en el sitio).
# Requiere fonttools y brotli de Python (pip install fonttools brotli) y el paquete @fontsource-variable/literata
# (npm i --no-save @fontsource-variable/literata). No forma parte del build.
set -euo pipefail
cd "$(dirname "$0")/.."
origen=node_modules/@fontsource-variable/literata/files/literata-latin-opsz-normal.woff2
tmp="$(mktemp -d)"
# Solo los pesos que usa el sitio (400, 500, 700): se recorta el eje wght (era 200-900); opsz entero
fonttools varLib.instancer "$origen" wght=400:700 -o "$tmp/literata.woff2" --no-recalc-bounds
pyftsubset "$tmp/literata.woff2" --text-file=scripts/fuente-caracteres.txt --flavor=woff2 --layout-features='*' \
  --output-file=src/assets/fonts/literata-opsz.woff2
rm -rf "$tmp"
