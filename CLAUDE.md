# CLAUDE.md — entretecas-web (entretecas.com)

Hereda `~/factoria-thekes/CLAUDE.md` y `entretecas/CLAUDE.md` (este último trata de
MARC21 y Koha: no aplica a la web). Este repo es independiente (`factoriathekes/entretecas-web`)
y está ignorado por el repo factoria-thekes.

**Convenciones de Astro**: las de `alimento-web/CLAUDE.md` §4 aplican aquí aunque este
repo no las herede, en particular la de SVG exportados de Illustrator (mirar el peso;
solución probada en `alimento-web/mediadiamond/greenvillage/scripts/svg-illustrator.mjs`).
Los SVG del paquete actual están limpios (sin `<i:aipgf>`).

**Logos**: salen de `~/factoria-thekes/thekes/marketing/identidad-thekes/logos/` (única fuente; `LEEME.txt` explica
versiones y usos). Se copian tal cual a `src/assets/logos/`, `scripts/` (logo blanco para `og.png`) y `public/`
(favicons e iconos); si cambian, copiar de nuevo y regenerar `og.png` con `npm run og`.

## Contexto

- Web propia de Entretecas (marca de Factoría Thekes): servicios para bibliotecas y archivos.
- Encargo y fuente de verdad inicial: `~/factoria-thekes/thekes/marketing/paquete-web/`
  (`BRIEF.md`, `textos/Textos web Entretecas.md`, `diseno/entretecas-*.html`); logos, ver abajo.
  Los textos de la portada son los de `diseno/entretecas-portada.html` (definitivos, decisión de Rai).
- Una vez construida, la fuente de verdad es este repo: `src/content/` y `src/data/sitio.json`.
- Inicio: 2026-11-06.

## Estado

- [x] Base: Astro, Literata local, tokens, layout con cabecera y pie, favicons, sitemap, metadatos,
      colecciones, `sitio.json` con esquema, Pages CMS, workflows y plantillas de secretos.
- [x] Portada (`src/pages/index.astro` + `src/content/portada/portada.md`), con el formulario solo maquetado.
- [x] Páginas interiores: `/servicios/diagnostico/`, `/servicios/migracion/`, `/servicios/implantacion/`,
      `/servicios/archivos/` (una plantilla), `/planes/`, `/casos/`, `/sobre-mi/`, `/contacto/`.
- [ ] Envío de los formularios (diagnóstico y contacto): hecho en `public/api/enviar.php` (PHP + API transaccional
      de Brevo) y probado en local en modo simulado (2026-11-18). **Pendiente**: crear `~/config/formulario-entretecas.php`
      en Raiola (plantilla `deploy/config-formulario.ejemplo.php`), validar `rai@entretecas.com` como remitente en
      Brevo y probar de extremo a extremo en staging (correo real con «[PRUEBAS]»).
- [ ] **BORRADOR** — Páginas legales (`/aviso-legal/`, `/privacidad/`, `/cookies/`) redactadas el 2026-11-06 con
      los datos de `sitio.json`; **pendientes de revisión de Rai**. Queda un hueco entre corchetes: datos del
      Registro Mercantil (aviso legal), hasta que Rai los pase. Privacidad: correo en Google Workspace (Google
      Ireland Ltd.), única transferencia posible a EE. UU. (cláusulas contractuales tipo y Marco de Privacidad
      de Datos UE-EE. UU.); Raiola (España) y Brevo (Francia) tratan en la UE. Cookies: la web no usa ninguna (comprobado en `dist/`: sin recursos de terceros ni
      almacenamiento).
- [ ] Redirecciones 301 de URL antiguas (lista de Rai) y de entretecas.es.
- [ ] Revisión en staging (escritorio y móvil), formulario de extremo a extremo, redirecciones → lanzamiento.

## Stack y entornos

- **Astro estático** (sin framework de interfaz; JS solo en menú móvil y formulario). Variante
  «Astro + Raiola (FTPS con Actions)» del CLAUDE.md global. CSS propio, sin Tailwind.
- **Local**: `npm run dev` (Node 22, `.nvmrc`). `npm run build` valida `sitio.json` y las colecciones.
- **Staging**: https://staging.entretecas.com, publicación **solo a mano** (Actions → despliegue → Run workflow →
  entorno `staging`; desde 2026-11-18 ya no se lanza con el push). Siempre con `X-Robots-Tag: noindex, nofollow`,
  `robots.txt` con `Disallow: /` y sin analítica; la contraseña de acceso es **opcional** (solo si existen los
  secretos `STAGING_AUTH_*`). Las redirecciones de la web antigua llevan condición de host y no saltan en staging.
- **Producción**: https://entretecas.com. El job `produccion` del workflow está **preparado pero desactivado**
  (`if: false`; necesita el secreto `FTP_DIR_PRODUCCION`). Activarlo requiere OK de Rai (raíz §4.2.3) y entrada en la bitácora.
- **Flujo**: trabajo en `staging`; Rai lanza el workflow a mano para publicar en staging; cuando está revisado, lleva `staging` a `main`.
- **Workflow**: `.github/workflows/despliegue.yml`. `npm ci`, `npm run build`, `deploy/preparar.sh`
  y subida de `dist/` por FTPS con SamKirkland/FTP-Deploy-Action (solo sube lo cambiado y solo borra
  lo que subió ella, según `.ftp-deploy-sync-state.json` en el servidor). No borrar ese archivo
  ni activar `dangerous-clean-slate`.
- **Secretos de GitHub**: nombres en `deploy/secretos-github.example`. Los valores los introduce Rai;
  Claude Code no los lee ni los muestra.
- **Envío de formularios**: `public/api/enviar.php` llama a la API de Brevo (no SMTP). La clave y el secreto de la
  marca de tiempo van en `~/config/formulario-entretecas.php` de la cuenta de Raiola (fuera de la carpeta pública,
  sin versionar, se crea a mano una vez; plantilla `deploy/config-formulario.ejemplo.php`). Staging y producción
  comparten cuenta y fichero; los avisos de `staging.entretecas.com` llevan «[PRUEBAS]». `config/entretecas-smtp.example.php`
  es de un plan anterior (SMTP) y ya no se usa. El script calcula esas rutas como `dirname($_SERVER['DOCUMENT_ROOT']) . '/config/…'` y `'/tmp/entretecas-formulario/'` (la carpeta que contiene la de la web de cada entorno; asume que staging y producción cuelgan directamente del HOME). `public/api/salud.php` (solo CLI; 404 desde la web; bloqueado en `api/.htaccess`) comprueba cada semana la clave de Brevo por cron de cPanel y solo escribe una línea si falla. Los contadores del límite por IP (5/hora; solo HMAC de la IP y marcas
  de hora) viven en `~/tmp/entretecas-formulario/`, también fuera de la carpeta pública, y se borran a la hora.
  Las listas permitidas (sistemas, registros, bibliotecas, intereses) las genera el build
  (`src/pages/api/listas.php.ts` → `dist/api/listas.php`) desde `diagnostico.md`: no se duplican a mano. Prueba local:
  `ENTRETECAS_FORM_SIMULAR=/ruta/avisos.txt ENTRETECAS_FORM_TMP=/ruta/limite php -S 127.0.0.1:8099 -t dist`
  (el aviso se escribe en ese fichero en vez de llamar a Brevo). Gracias: `/contacto/gracias/` y
  `/servicios/diagnostico/gracias/` (noindex, fuera del sitemap; textos en la propia página, no en Pages CMS).

## Estructura

- `src/data/sitio.json` + `src/data/sitio.ts`: datos que cambian (precios, plazos, correos, titular,
  cifras, menú) y su esquema zod. Importar siempre desde `sitio.ts` (valida y da los formateadores
  `euros()` y `textoPlazo()`), nunca escribir esos datos a mano en una página.
- `src/content.config.ts`: colecciones `portada`, `servicios` (`src/content/servicios/*.md`, plantilla
  `src/pages/servicios/[servicio].astro`), `planes`, `casos`, `sobreMi` y `contacto` (un archivo cada una en
  `src/content/paginas/`) y `legal` (`src/content/legal/*.md`, plantilla `src/pages/[legal].astro`). Todo el
  texto va en el frontmatter salvo en las legales (cuerpo Markdown). **Si cambia un campo, cambiarlo también en
  `.pages.yml`**, que repite los campos.
- Textos de las páginas interiores: admiten las marcas de `rellenar()` y, vía `enLinea()` (`src/data/texto.ts`),
  `**negrita**` y `[enlace](/ruta/)`. Las legales admiten las marcas con los datos del titular (`Rellenar.astro`).
- `src/styles/tokens.css`: sistema visual del brief. Idéntico en `thekes-web`: si cambia, en los dos.
- `src/components/`: piezas reutilizables para todas las páginas: `Boton` (variantes marca, tinta, borde,
  papel), `EnlaceFlecha`, `Etiqueta`, `EncabezadoSeccion`, `Seccion` (fondos papel, blanco, tinta, marca),
  `TarjetaServicio`, `TarjetaPlan`, `TarjetaCaso`, `Cita` (extracto o testimonio de varios párrafos),
  `CabezaPagina` y `Migas` (cabecera de las páginas interiores), `Lista` (viñetas cuadradas o pasos 01, 02…),
  `Preguntas` (`<details>`), `Rellenar`, `BloqueCTA` (franja de marca solo con el
  titular; texto y formulario debajo, sobre papel), `FormularioContacto` (sin envío todavía; valida en el navegador) y `MesaTaller` (animación del hero).
- Textos con cifras: marcas `{anos}`, `{bibliotecas}`, `{correo}`, `{respuestaContacto}`… que rellena
  `rellenar()` de `sitio.ts` con `sitio.json`.
- `src/assets/logos/`: SVG de `identidad-thekes/logos/`, sin recomponer. Reducido en la cabecera, completo en el pie.
- `public/og.png`: imagen para redes, generada con `npm run og` (`scripts/generar-og.mjs`).
- `deploy/`: `htaccess` (https sin www, entretecas.es → .com, URL con barra final, cabeceras, caché),
  `htaccess-assets`, `htaccess-staging`, `htpasswd.py`, `preparar.sh` (copiados de Esquí & Friends).

## Pendientes

- [ ] Fotografía para «Sobre mí» (hueco «[Fotografía]» en la portada y en `/sobre-mi/`: `foto.imagen` en
      `sobre-mi.md`); lista de «También confían en Entretecas» (`confian` en `casos.md`: `visible: true` y los
      centros); lista de URL antiguas.
- [ ] Datos entre corchetes de los textos: quedan pendientes, sin inventar.
- [ ] Descripción por defecto de `sitio.json`: provisional (frase de la marca en thekes.es).

## Registro

Bitácora (`bitacora-trabajo.md`) según raíz §4.5: entrada cuando se toca producción (DNS, hosting,
publicación) o hay un incidente. Histórico y documentación larga en `docs/`.

## Decisiones

- 2026-11-06 — Páginas interiores (Rai): textos de «Textos web Entretecas.md»; de `diseno/` salen la estructura y
  lo que los textos no tienen (etiquetas y titulares de sección como «Te sirve si…», títulos de los puntos de
  planes, lemas de los planes, titulares de las franjas finales). Donde el diseño resume un texto, manda el .md.
  Testimonios según su nota de uso: completos en `/casos/`, extracto de Rosa San Martín en Diagnóstico y Sobre mí,
  de Irene Ariza en Planes. Comparativa de planes: tabla desde 760 px y una tarjeta por plan por debajo.
  `/contacto/`: el título de la página es el del formulario (filete encima); campos por parejas nombre + correo,
  entidad + sistema, registros + bibliotecas, con el teléfono (opcional) a media anchura en su propia fila.
  Los tres desplegables (sistema, registros, bibliotecas) empiezan en «Elige una opción» sin valor y son
  obligatorios, con su mensaje de error (también el de sistema en la portada); bibliotecas pasa de botones de
  opción a desplegable. El sistema de la portada también es obligatorio (Rai). Lemas de servicio en el campo `lema`: justo
  debajo del h1, en tinta, 22 → 30 px, unos 36 caracteres por línea; la etiqueta queda «Servicio 0N».
  «Otros servicios» reutiliza las tarjetas de la portada. Botones: texto en #FFFFFF (excepción anotada en BRIEF).
- 2026-11-06 — Formulario de contacto (Rai): sobre papel, sin panel; se retira el tinte #EEF8F3 y su token
  (`--color-tinte-accion`, también en thekes-web). Sin filete en la portada (el título está en la franja verde); con la prop
  `titulo` (para `/contacto/`), filete de 4 × 56 px encima del título. Campos en blanco, borde 1 px `--color-borde-campo` #7E8583
  (3,8:1 sobre blanco, 3,5:1 sobre papel), radio 4 px; hover, borde en tinta; foco, borde verde de 2 px
  (1 px más por dentro con `box-shadow`, sin cambiar de tamaño) y el anillo de foco general; error, borde de
  2 px y mensaje en `--color-error` #B3401C (5,4:1 sobre papel), con `aria-describedby` y `aria-invalid`,
  foco al primer campo con error. Una columna de 640 px como máximo, etiquetas encima; desde 640 px, los
  `.campo--par` de dos en dos (nombre + correo y entidad + sistema en la portada; registros + bibliotecas en `/contacto/`), el resto
  a todo el ancho; orden del HTML = orden visual. «Enviar» a la izquierda, botón principal (`Boton` sin variante: verde, 19 px en negrita). El cuerpo de
  `BloqueCTA` pasa de blanco a papel.
- 2026-11-06 — Papel #F7F8F6 (Rai, elegido entre #F6F8F8, #F4F6F7 y #F7F8F6): `--color-papel` en `tokens.css`,
  `theme-color` y `manifest.webmanifest`. Sustituye a #FDFDFB.
  Grises ajustados al papel frío (Rai): `--color-texto-2` #3A403F (9,9:1 sobre papel), `--color-texto-3` #585F5D
  (6,1:1), `--color-linea` #DDE2E0. Sustituyen a #3E3A33, #5E584D y #ECE8E0.
  También fríos el borde de los campos `--color-borde-campo` #7E8583 (3,8:1 sobre blanco, 3,5:1 sobre papel)
  y los textos de las bandas oscuras `--color-texto-claro` #E3E7E5 y `--color-texto-claro-2` #C2C8C6. Logos,
  favicons e iconos sustituidos por los nuevos de `identidad-thekes/logos/` (papel #F7F8F6 y grises fríos);
  `og.png` regenerado.
- 2026-11-06 — Animación del hero (Rai): `MesaTaller.astro` sustituye al bloque verde con el símbolo.
  Reconstruida del prototipo `paquete-web/diseno/animacion/mesa-entretecas-blanco.html` con sus mismos tiempos,
  posiciones y formas; colores de los tokens (nuevo `--color-vacio` #C4CBD1 para la caja vacía, también en
  thekes-web). Una pasada; arranca con el 50 % visible y hasta entonces queda en el primer fotograma (el
  prototipo mostraba un instante la caja terminada); botón «Pausa» / «Seguir» / «Ver de nuevo» con la regla de
  enlaces, debajo del dibujo y alineado a la derecha, en una fila de 44 px reservada desde el principio (sin
  salto al aparecer); con movimiento reducido o sin JavaScript, la caja terminada, sin botón ni fila. 440 px
  como máximo. Mientras espera, la mesa vacía (confirmado por Rai). Fondo blanco (Rai; se probó #EEF8F3).
- 2026-11-06 — Coherencia del sistema (Rai): sin verde claro (ni los tonos `-2` de las marcas) en líneas
  y filetes; cada pieza lleva un único acento en el color de marca. Tarjetas de servicio y de caso y bloques
  sin caja (puntos de «idiomas», pasos de «Cómo trabajamos»): filete de 4 × 56 px con extremos de 2 px encima
  del contenido (`Filete.astro`, `.filete`); el número de la tarjeta de servicio va sin cuadrado de etiqueta
  para no sumar un segundo acento. Plan destacado y citas, sin cambios. (El tinte de zona de acción #EEF8F3 se retiró después: ver la decisión del formulario.) Tarjetas de servicio y caso enlazadas
  enteras (`.tarjeta-enlace`): un solo enlace con el título como nombre accesible, borde en color de marca
  al pasar el ratón o con foco, sin sombras, y «Ver servicio →» / «Ver caso →» fijo y sin partir.
  Servicios de la portada en 3 columnas (2 en tableta, 1 en móvil) con una sexta tarjeta en color de marca:
  «¿No sabes por dónde empezar?» y «Pedir diagnóstico» en papel. Los casos enlazan a `/casos/#<centro>`
  (anclas que hay que crear al construir `/casos/`).
- 2026-11-06 — Portada (Rai): `<title>` «Entretecas · PMB, Koha y AtoM: implantación, migración y soporte»;
  testimonio, el extracto de Irene Ariza («Ha respondido a nuestras demandas… […]»), como dice la nota
  de uso de los testimonios; el formulario de la portada lleva también la casilla de privacidad obligatoria
  y el texto legal de `/contacto/` bajo el botón. Los campos extra de `/contacto/` (teléfono, registros,
  bibliotecas) siguen solo en esa página.
- 2026-11-06 — Un solo color por marca, el del logo; sin tonos más oscuros (Rai). Resuelto así:
  botones con fondo de color con texto de 19 px en negrita como mínimo (texto grande, basta 3:1;
  `--texto-boton`), también el de la cabecera, que cabe (menú plegable por debajo de 960 px); `.boton--tinta`
  para donde no quepa o no deba ir color; etiquetas en tinta con un cuadrado de 10 px en color de marca;
  el color de marca como texto solo en titulares grandes. Sin `--color-marca-texto`.
- 2026-11-06 — Enlaces y elementos pequeños (Rai): enlaces en tinta con subrayado de 2 px en color de
  marca y 3 px de separación (3 px de grosor al pasar el ratón; el texto sigue en tinta); foco de 2 px en
  color de marca con 2 px de separación en todo lo interactivo (en papel sobre fondo de marca); enlaces
  «Ver… →» con `EnlaceFlecha.astro` (flecha en color de marca); menú en tinta con la página actual
  subrayada. El color de marca, libre en elementos gráficos (viñetas, iconos, filetes, bordes: 3:1).
  Tokens en `tokens.css` (`--subrayado*`, `--foco*`).
- 2026-11-06 — Modelo de publicación de Esquí & Friends desde el primer día; Pages CMS escribe en
  `staging` — evita que una edición desde el navegador llegue a producción sin revisión.
- 2026-11-06 — URL con barra final (`trailingSlash: 'always'`, `build.format: 'directory'`) — las rutas
  del brief la llevan.
- 2026-11-06 — Literata con eje óptico (`@fontsource-variable/literata/opsz.css`), como el diseño de
  referencia (Google Fonts con `opsz`).
- 2026-11-17 — Fuente alojada como subconjunto propio (`src/assets/fonts/literata-opsz.woff2`, latino, wght 400-700 +
  opsz; se regenera con `scripts/subconjunto-fuente.sh`) en lugar del paquete `@fontsource-variable/literata`
  (retirado); con reserva ajustada a Georgia para CLS 0. CSS en línea y sin scripts en línea salvo el de la clase `.js`
  (CSP con su hash: lo calcula `deploy/preparar.sh`). Si se añade otro script en línea, el despliegue falla a propósito.
