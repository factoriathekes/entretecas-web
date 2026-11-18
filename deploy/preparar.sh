#!/usr/bin/env bash
# Añade a dist/ los archivos de servidor de cada entorno. Se ejecuta tras `npm run build`.
# Uso: deploy/preparar.sh produccion|staging
# Staging: STAGING_AUTH_USER, STAGING_AUTH_PASSWORD y STAGING_HTPASSWD_PATH son opcionales (contraseña de acceso).
set -euo pipefail
cd "$(dirname "$0")/.."

entorno="${1:-}"
[ -d dist/_astro ] || { echo "Falta dist/: ejecuta antes npm run build" >&2; exit 1; }

cp deploy/htaccess-assets dist/_astro/.htaccess

# Hash CSP del único script en línea (el de la clase .js, en <head>). Falla si alguna página tiene otro script en
# línea distinto (la CSP lo bloquearía) o si el hash no es el mismo en todas.
hash_js="$(python3 - <<'PY'
import base64, glob, hashlib, re, sys
hashes = set()
for f in glob.glob('dist/**/*.html', recursive=True):
    html = open(f, encoding='utf-8').read()
    for m in re.finditer(r'<script(?![^>]*\bsrc=)([^>]*)>(.*?)</script>', html, re.S):
        if 'ld+json' in m.group(1):
            continue
        hashes.add(base64.b64encode(hashlib.sha256(m.group(2).encode('utf-8')).digest()).decode())
if len(hashes) != 1:
    sys.exit('Scripts en línea distintos o ausentes en dist/: %d hashes' % len(hashes))
print(hashes.pop())
PY
)"

case "$entorno" in
  produccion)
    sed "s#__HASH_SCRIPT_JS__#${hash_js}#" deploy/htaccess > dist/.htaccess
    ;;
  staging)
    # Siempre: X-Robots-Tag noindex y robots.txt con Disallow. La contraseña es opcional: solo si están los tres
    # STAGING_AUTH_USER, STAGING_AUTH_PASSWORD y STAGING_HTPASSWD_PATH.
    {
      if [ -n "${STAGING_AUTH_USER:-}" ] && [ -n "${STAGING_AUTH_PASSWORD:-}" ] && [ -n "${STAGING_HTPASSWD_PATH:-}" ]; then
        sed "s#__HTPASSWD__#${STAGING_HTPASSWD_PATH}#" deploy/htaccess-staging-acceso
        # La contraseña se lee del entorno tal cual, admite cualquier carácter (ver deploy/htpasswd.py)
        python3 deploy/htpasswd.py > dist/.htpasswd
        echo "Staging con contraseña de acceso" >&2
      else
        echo "AVISO: staging SIN contraseña (faltan STAGING_AUTH_*): solo noindex y robots.txt" >&2
      fi
      cat deploy/htaccess-staging
      sed "s#__HASH_SCRIPT_JS__#${hash_js}#" deploy/htaccess
    } > dist/.htaccess
    printf 'User-agent: *\nDisallow: /\n' > dist/robots.txt
    ;;
  *)
    echo "Uso: deploy/preparar.sh produccion|staging" >&2
    exit 1
    ;;
esac

echo "Archivos de servidor de $entorno añadidos a dist/"
