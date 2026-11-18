// Colecciones de contenido. Cada página es un Markdown con frontmatter, editable a mano o desde Pages CMS
// (.pages.yml repite estos campos: si se cambia uno aquí, cambiarlo también allí).
// Los esquemas son la base común; los bloques propios de cada página se añaden al construir esa página.
import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

// Lo que va en <title> y en la meta description («Título:» y «Descripción para buscadores:» de los textos)
const seo = {
  titulo: z.string().min(1).max(70),
  descripcion: z.string().min(50).max(170),
};

const enlace = z.object({ texto: z.string().min(1), ruta: z.string().min(1) });
const punto = z.object({ titulo: z.string().min(1), texto: z.string().min(1) });
const texto = z.string().min(1);
// Los textos de las páginas interiores admiten además **negrita** y [enlace](/ruta/) (enLinea de src/data/texto.ts)
const cita = z.object({ texto: z.union([texto, z.array(texto).min(1)]), autoria: texto });
const preguntas = z.object({
  etiqueta: z.string().optional(),
  titular: texto,
  preguntas: z.array(z.object({ pregunta: texto, respuesta: texto })).min(1),
});
// Franja final en color de marca: titular en la franja; texto y botón debajo
const cta = z.object({ titular: texto, texto: texto, boton: enlace });
// Cabecera de página interior
const cabeza = { etiqueta: z.string().optional(), h1: texto, entradilla: z.array(texto).min(1) };
// Aceptación y texto legal del formulario: entre corchetes, el enlace a la política de privacidad
const conEnlacePolitica = z.string().regex(/\[[^\]]+\]/, 'Marca entre [corchetes] el enlace a la política');

// Portada: un solo archivo (src/content/portada/portada.md) con un bloque por sección.
// Los textos admiten las marcas de rellenar() (src/data/sitio.ts): {anos}, {bibliotecas}, {correo}…
const portada = defineCollection({
  loader: glob({ pattern: 'portada.md', base: './src/content/portada' }),
  schema: z.object({
    ...seo,
    hero: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      entradilla: z.string().min(1),
      botonPrincipal: enlace,
      botonSecundario: enlace,
      nota: z.string().min(1),
    }),
    idiomas: z.object({
      titular: z.string().min(1),
      puntos: z.array(punto).min(1),
    }),
    servicios: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      tarjetas: z.array(z.object({ nombre: z.string().min(1), texto: z.string().min(1), ruta: z.string().min(1) })).min(1),
      llamada: z.object({ texto: z.string().min(1), boton: enlace }),
    }),
    planes: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      planes: z
        .array(
          z.object({
            nombre: z.string().min(1),
            lema: z.string().min(1),
            puntos: z.array(z.string().min(1)).min(1),
            destacado: z.boolean().default(false),
          }),
        )
        .min(1),
      nota: z.string().min(1),
      boton: enlace,
    }),
    proceso: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      pasos: z.array(punto).min(1),
    }),
    casos: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      casos: z.array(
        z.object({ categoria: z.string().min(1), nombre: z.string().min(1), texto: z.string().min(1), ruta: z.string().min(1) }),
      ).min(1),
      cita: z.object({ texto: z.string().min(1), autoria: z.string().min(1) }),
    }),
    sobreMi: z.object({
      etiqueta: z.string().min(1),
      titular: z.string().min(1),
      parrafos: z.array(z.string().min(1)).min(1),
    }),
    // Bloque final: título, texto y botón; a su derecha, «Qué pasa después»
    contacto: z.object({
      titular: z.string().min(1),
      texto: z.string().min(1),
      boton: enlace,
    }),
  }),
});

// Una página (archivo) de src/content/paginas/
const pagina = <T extends z.ZodRawShape>(archivo: string, campos: T) =>
  defineCollection({
    loader: glob({ pattern: `${archivo}.md`, base: './src/content/paginas' }),
    schema: z.object({ ...seo, ...campos }),
  });

// Servicios (/servicios/<archivo>/): misma plantilla, distinto contenido. Las secciones van en orden; las
// marcadas «mitad» seguidas comparten banda en dos columnas. Tipo «cuadros» (viñetas) o «pasos» (01, 02…).
const servicios = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/servicios' }),
  schema: z.object({
    ...seo,
    nombre: texto,
    orden: z.number().int(),
    ...cabeza,
    // Lema del servicio: frase justo debajo del h1 (la etiqueta queda «Servicio 0N»)
    lema: z.string().optional(),
    boton: enlace,
    precio: z.object({
      etiqueta: texto,
      // Con cifra: «Desde» / cifra grande / «sin IVA». Sin cifra: `texto` a tamaño de titular.
      antes: z.string().optional(),
      cifra: z.string().optional(),
      despues: z.string().optional(),
      texto: z.string().optional(),
      puntos: z.array(texto).default([]),
    }),
    secciones: z
      .array(
        z.object({
          etiqueta: z.string().optional(),
          titular: texto,
          tipo: z.enum(['cuadros', 'pasos']).default('cuadros'),
          puntos: z.array(texto).default([]),
          parrafos: z.array(texto).default([]),
          tabla: z
            .object({
              leyenda: texto,
              columnas: z.array(texto).min(2),
              filas: z.array(z.object({ celdas: z.array(texto).min(2) })).min(1),
            })
            .optional(),
          mitad: z.boolean().default(false),
        }),
      )
      .min(1),
    cita: cita.optional(),
    preguntas,
    otros: z.object({ etiqueta: z.string().optional(), titular: texto }),
    // En /servicios/diagnostico/ la franja final lleva el formulario completo (sin botón); en los demás, el botón
    cta: cta.extend({ boton: enlace.optional() }),
    formulario: z
      .object({
        sistemas: z.array(texto).min(1),
        registros: z.array(texto).min(1),
        bibliotecas: z.object({ leyenda: texto, opciones: z.array(texto).min(2) }),
        // «¿Qué te interesa?»: casillas; `clave` es el valor de ?servicio= que la preselecciona
        intereses: z.object({
          leyenda: texto,
          opciones: z.array(z.object({ texto, clave: z.string().optional() })).min(1),
        }),
        aceptacion: conEnlacePolitica,
        privacidad: conEnlacePolitica,
        boton: texto,
      })
      .optional(),
  }),
});

const planes = pagina('planes', {
  ...cabeza,
  boton: enlace,
  incluye: z.object({ etiqueta: z.string().optional(), titular: texto, puntos: z.array(punto).min(1) }),
  comparativa: z.object({
    etiqueta: z.string().optional(),
    titular: texto,
    leyenda: texto,
    planes: z.array(z.object({ nombre: texto, lema: texto, destacado: z.boolean().default(false) })).min(2),
    filas: z.array(z.object({ concepto: texto, valores: z.array(texto).min(2) })).min(1),
    nota: z.array(texto).default([]),
  }),
  bloques: z.array(z.object({ etiqueta: texto, titular: texto, parrafos: z.array(texto).min(1), oscuro: z.boolean().default(false) })),
  cita: cita.optional(),
  preguntas,
  // `aviso`: línea justo encima del botón de la franja final
  cta: cta.extend({ aviso: texto.optional() }),
});

const casos = pagina('casos', {
  ...cabeza,
  nota: z.string().optional(),
  apartados: z.object({ reto: texto, hicimos: texto, resultado: texto }),
  casos: z
    .array(
      z.object({
        // Ancla de /casos/#… (la usan las tarjetas de la portada)
        ancla: z.string().regex(/^[a-z0-9-]+$/, 'Solo minúsculas, números y guiones'),
        nombre: texto,
        categoria: texto,
        reto: texto,
        hicimos: texto,
        resultado: texto,
        cita: cita.optional(),
      }),
    )
    .min(1),
  // «También confían en Entretecas»: no se muestra hasta que `visible` sea true y haya centros
  confian: z.object({ visible: z.boolean().default(false), titular: texto, centros: z.array(texto).default([]) }),
  cta,
});

const sobreMi = pagina('sobre-mi', {
  ...cabeza,
  boton: enlace,
  // Hueco reservado para la fotografía: texto alternativo de la futura imagen; `imagen` vacío = hueco
  foto: z.object({ alt: texto, imagen: z.string().optional() }),
  como: z.object({ etiqueta: z.string().optional(), titular: texto, puntos: z.array(texto).min(1) }),
  columnas: z.array(z.object({ titular: texto, parrafos: z.array(texto).min(1), enlace: enlace.optional() })),
  cita: cita.optional(),
  cta,
});

const contacto = pagina('contacto', {
  ...cabeza,
  formulario: z.object({
    aceptacion: conEnlacePolitica,
    privacidad: conEnlacePolitica,
    boton: texto,
  }),
  otros: z.array(texto).min(1),
});

// Aviso legal, privacidad y cookies (/<archivo>/). Cuerpo en Markdown; admite las marcas de rellenar()
const legal = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/legal' }),
  schema: z.object({
    ...seo,
    h1: texto,
    actualizado: z.coerce.date(),
  }),
});

export const collections = { portada, servicios, planes, casos, sobreMi, contacto, legal };
