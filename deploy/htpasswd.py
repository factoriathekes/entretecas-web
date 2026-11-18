#!/usr/bin/env python3
"""Escribe en la salida estándar la línea de .htpasswd de staging (usuario:hash APR1-MD5, el formato de Apache).

Lee STAGING_AUTH_USER y STAGING_AUTH_PASSWORD del entorno como bytes, sin pasar por la shell, stdin ni
argumentos, así que la contraseña llega intacta con cualquier carácter ($, comillas, barras, espacios, emojis…)
y sin límite de longitud. `openssl passwd -stdin` cortaba en 255 bytes y partía las que tenían saltos de línea.
Uso: python3 deploy/htpasswd.py > dist/.htpasswd
"""

import hashlib
import os
import secrets
import sys

ITOA64 = b"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"


def apr1(clave: bytes, sal: bytes) -> bytes:
    """Hash APR1-MD5 de Apache (md5crypt con el prefijo $apr1$)."""
    magia = b"$apr1$"
    final = hashlib.md5(clave + sal + clave).digest()
    ctx = clave + magia + sal
    for resto in range(len(clave), 0, -16):
        ctx += final[: min(16, resto)]
    i = len(clave)
    while i:
        ctx += b"\x00" if i & 1 else clave[:1]
        i >>= 1
    final = hashlib.md5(ctx).digest()
    for i in range(1000):
        c = clave if i & 1 else final
        if i % 3:
            c += sal
        if i % 7:
            c += clave
        c += final if i & 1 else clave
        final = hashlib.md5(c).digest()

    def a64(valor: int, n: int) -> bytes:
        salida = bytearray()
        for _ in range(n):
            salida.append(ITOA64[valor & 0x3F])
            valor >>= 6
        return bytes(salida)

    f = final
    cifrado = b"".join(
        a64((f[a] << 16) | (f[b] << 8) | f[c], 4)
        for a, b, c in ((0, 6, 12), (1, 7, 13), (2, 8, 14), (3, 9, 15), (4, 10, 5))
    ) + a64(f[11], 2)
    return magia + sal + b"$" + cifrado


def main() -> int:
    usuario = os.environb.get(b"STAGING_AUTH_USER", b"")
    clave = os.environb.get(b"STAGING_AUTH_PASSWORD", b"")
    if not usuario or not clave:
        print("Faltan STAGING_AUTH_USER o STAGING_AUTH_PASSWORD", file=sys.stderr)
        return 1
    if b":" in usuario or any(c in usuario for c in b"\r\n"):
        print("El usuario de staging no puede llevar «:» ni saltos de línea", file=sys.stderr)
        return 1
    if any(c in clave for c in b"\r\n"):
        # No se puede teclear en el cuadro de acceso del navegador: mejor parar que guardarla alterada
        print("La contraseña de staging no puede llevar saltos de línea", file=sys.stderr)
        return 1
    sal = bytes(secrets.choice(ITOA64) for _ in range(8))
    sys.stdout.buffer.write(usuario + b":" + apr1(clave, sal) + b"\n")
    return 0


if __name__ == "__main__":
    sys.exit(main())
