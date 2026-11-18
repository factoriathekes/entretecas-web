// Datos que cambian (precios, plazos, correos, titular, cifras) validados al compilar.
// Se editan en src/data/sitio.json (a mano o desde Pages CMS); si algo no encaja con el esquema,
// `npm run build` falla con el campo exacto en vez de publicar una web con un dato roto.
import { z } from 'astro/zod';
import datos from './sitio.json';

const enlace = z.object({
  texto: z.string().min(1),
  ruta: z.string().regex(/^\/|^https:\/\//, 'Ruta interna (/…) o URL https'),
});

const plazo = z
  .object({
    desde: z.number().int().positive(),
    hasta: z.number().int().positive(),
    unidad: z.enum(['días laborables', 'semanas', 'meses']),
  })
  .refine((p) => p.hasta >= p.desde, '«hasta» no puede ser menor que «desde»');

export const esquemaSitio = z.object({
  nombre: z.string().min(1),
  url: z.string().url(),
  descripcion: z.string().min(50).max(160),
  correo: z.string().email(),
  titular: z.object({
    razonSocial: z.string().min(1),
    nombreComercial: z.string().min(1),
    cif: z.string().regex(/^[A-Z]\d{7}[0-9A-J]$/, 'CIF con formato B12345678'),
    domicilio: z.string().min(1),
    correo: z.string().email(),
    web: z.string().url(),
  }),
  precios: z.object({
    diagnosticoDesde: z.number().positive(),
  }),
  plazos: z.object({
    diagnostico: plazo,
    implantacion: plazo,
    implantacionConMigracion: plazo,
    respuestaContacto: plazo,
  }),
  permanenciaMeses: z.number().int().positive(),
  cifras: z.object({
    anos: z.number().int().positive(),
    bibliotecas: z.number().int().positive(),
  }),
  menu: z.array(enlace).min(1),
  botonMenu: enlace,
  // Bloque negro «Qué pasa después» (componente QuePasaDespues): portada y diagnóstico
  quePasaDespues: z.object({ titular: z.string().min(1), pasos: z.array(z.string().min(1)).min(1) }),
  legal: z.array(enlace).length(3),
});

export type Sitio = z.infer<typeof esquemaSitio>;
export type Plazo = z.infer<typeof plazo>;

export const sitio: Sitio = esquemaSitio.parse(datos);

/** 290 → «290 €» (sin IVA, como dicen los textos). */
export const euros = (cantidad: number) =>
  `${new Intl.NumberFormat('es-ES', { maximumFractionDigits: 0 }).format(cantidad)} €`;

/** { 5, 10, días laborables } → «5 a 10 días laborables»; si desde = hasta, «2 días laborables». */
export const textoPlazo = ({ desde, hasta, unidad }: Plazo) =>
  desde === hasta ? `${desde} ${unidad}` : `${desde} a ${hasta} ${unidad}`;

/** { 2, 4, semanas } → «entre 2 y 4 semanas»; si desde = hasta, «2 semanas». */
export const textoEntre = ({ desde, hasta, unidad }: Plazo) =>
  desde === hasta ? `${desde} ${unidad}` : `entre ${desde} y ${hasta} ${unidad}`;

/**
 * Sustituye en un texto de contenido las marcas {anos}, {bibliotecas}, {correo}, {diagnosticoDesde},
 * {permanenciaMeses}, {respuestaContacto}, los plazos {plazoDiagnostico}, {plazoImplantacion} y
 * {plazoImplantacionConMigracion} («entre 2 y 4 semanas») y los datos del titular para las páginas
 * legales ({razonSocial}, {nombreComercial}, {cif}, {domicilio}, {correoTitular}, {webTitular}, {url})
 * por su valor de sitio.json. Así los textos se editan en
 * Markdown/Pages CMS y las cifras siguen viviendo en un solo sitio. Una marca desconocida rompe el build.
 */
export const rellenar = (texto: string) =>
  texto.replace(/\{(\w+)\}/g, (_, clave: string) => {
    const valores: Record<string, string> = {
      anos: String(sitio.cifras.anos),
      bibliotecas: String(sitio.cifras.bibliotecas),
      correo: sitio.correo,
      diagnosticoDesde: euros(sitio.precios.diagnosticoDesde),
      permanenciaMeses: String(sitio.permanenciaMeses),
      respuestaContacto: textoPlazo(sitio.plazos.respuestaContacto),
      plazoDiagnostico: textoEntre(sitio.plazos.diagnostico),
      plazoImplantacion: textoEntre(sitio.plazos.implantacion),
      plazoImplantacionConMigracion: textoEntre(sitio.plazos.implantacionConMigracion),
      razonSocial: sitio.titular.razonSocial,
      nombreComercial: sitio.titular.nombreComercial,
      cif: sitio.titular.cif,
      domicilio: sitio.titular.domicilio,
      correoTitular: sitio.titular.correo,
      webTitular: sitio.titular.web,
      url: sitio.url,
    };
    if (!(clave in valores)) throw new Error(`Marca desconocida en un texto: {${clave}}`);
    return valores[clave];
  });

/** Identificador HTML a partir de un texto: «Catálogo Colectivo…» → «catalogo-colectivo…» */
export const idDe = (texto: string) =>
  texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
