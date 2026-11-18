// Genera public/og.png (1200 × 630), la imagen para redes: fondo de color de marca y logo completo
// en blanco (regla del brief: sobre color de marca, todo en papel). Se ejecuta a mano con `npm run og`
// solo si cambia el logo o el color; el PNG resultante se versiona.
import sharp from 'sharp';
import { readFile } from 'node:fs/promises';

const FONDO = '#2E8A6C';
const LOGO = new URL('./entretecas-completo-blanco.svg', import.meta.url);
const SALIDA = new URL('../public/og.png', import.meta.url);
const ANCHO = 1200;
const ALTO = 630;
const ANCHO_LOGO = 760;

const logo = await sharp(await readFile(LOGO), { density: 600 }).resize({ width: ANCHO_LOGO }).png().toBuffer();
const { height: altoLogo } = await sharp(logo).metadata();

await sharp({ create: { width: ANCHO, height: ALTO, channels: 3, background: FONDO } })
  .composite([{ input: logo, left: Math.round((ANCHO - ANCHO_LOGO) / 2), top: Math.round((ALTO - altoLogo) / 2) }])
  .png({ compressionLevel: 9 })
  .toFile(SALIDA.pathname);

console.log(`public/og.png generado (${ANCHO} × ${ALTO})`);
