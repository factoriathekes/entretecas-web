# Bitácora de trabajo — entretecas.com

Formato y criterio en `~/factoria-thekes/_templates/bitacora-trabajo-plantilla.md`.
Una entrada cuando se toca producción (DNS, hosting, publicación) o hay un incidente. Más reciente arriba.

---

## 2026-11-18 (4) — Preparación de la primera publicación en staging

- **Qué se tocó**: `public/api/enviar.php` (rutas de configuración y contadores), `public/api/salud.php` (nuevo), `public/api/.htaccess`, `.github/workflows/despliegue.yml` (reescrito), `deploy/preparar.sh`, `deploy/htaccess-staging`, `deploy/htaccess-staging-acceso` (nuevo), `deploy/secretos-github.example`, plantilla de configuración y `CLAUDE.md`. Local; **no se ha publicado nada ni en staging ni en producción**; sin backup (no aplica) ni commit.
- **Script**: la ruta de `formulario-entretecas.php` y la carpeta de contadores salen de `dirname($_SERVER['DOCUMENT_ROOT'])` (sin usuario escrito). Probado con PHP 7.4.33 (`php:7.4-cli`) en modo simulado: las cinco pruebas de la vez anterior (diagnóstico y contacto válidos → 303; honeypot → 303 sin aviso; demasiado rápido → 429; correo no válido → 422) y las extra (origen, método, tipo, tamaño, cabeceras, listas, límite de 5 por hora) dan el mismo resultado que con PHP 8.5. Con configuración falsa y sin simulación, las rutas se resuelven junto a la carpeta de la web y Brevo responde 401, que el script trata como fallo de envío (503).
- **Cloudflare**: los 22 rangos del script coinciden exactamente con https://www.cloudflare.com/ips-v4 y /ips-v6 (consultados hoy). Sin cambios.
- **salud.php**: solo CLI (web → 404, y `api/.htaccess` lo bloquea); `GET /v3/smtp/templates?limit=1`; sin salida si va bien, una línea con el código HTTP y el `code` de Brevo si falla. Probado con clave falsa (401), sin fichero de configuración y con un servidor simulado (éxito, sin salida). La clave real no se ha usado.
- **Staging**: X-Robots-Tag y robots.txt siempre; la contraseña pasa a ser opcional (antes el despliegue fallaba sin `STAGING_AUTH_*`). Probado con Apache local: 15 páginas 200, 404 propia, cabeceras de seguridad y CSP presentes, X-Robots-Tag en todas las respuestas, ninguna redirección antigua salta (devuelven 404) y http/www de staging van a https://staging.entretecas.com.
- **Workflow**: solo `workflow_dispatch`; job staging con `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_DIR_STAGING`; job producción preparado y desactivado (`if: false`, secreto `FTP_DIR_PRODUCCION`). Cambio de comportamiento: ya no se despliega con cada push a `staging`.

---

## 2026-11-18 (3) — Envío de los formularios (`public/api/enviar.php`)

- **Qué se tocó** (local; no es producción, no se ha desplegado; sin backup ni commit): nuevos `public/api/enviar.php`, `public/api/.htaccess`, `src/pages/api/listas.php.ts`, `deploy/config-formulario.ejemplo.php`, `src/pages/contacto/gracias.astro` y `src/pages/servicios/diagnostico/gracias.astro`; cambios en `FormularioContacto.astro` (action, campos ocultos, script), `astro.config.mjs` (gracias fuera del sitemap) y `CLAUDE.md`. Se quitó del componente la segunda trampa `web` (estaba duplicada, con el mismo `id`).
- **Funcionamiento**: POST a `/api/enviar.php` → aviso a `rai@entretecas.com` por la API de Brevo (remitente «Web Entretecas», Reply-To del visitante; asunto `[Diagnóstico|Contacto] Nombre — Entidad`, con `[PRUEBAS]` si el Origin es `staging.entretecas.com`) → 303 a la página de gracias. Si algo falla, el servidor devuelve la propia página del formulario (leída de `dist/`, solo se modifica el `<form>`) con el mensaje, los campos marcados y los datos conservados. La clave de Brevo se lee de `~/config/formulario-entretecas.php`; no está en el repo ni en la carpeta pública y el script no la muestra ni la registra (el log solo lleva el código HTTP y el `code` de Brevo).
- **Seguridad**: solo POST (el único GET es `?token=1`, que entrega la marca de tiempo firmada); Origin o Referer de `entretecas.com`, `www.` o `staging.`; tipo de contenido, tamaño y número de campos acotados; trampa `web` (se responde como si hubiera ido bien, sin enviar); marca de tiempo firmada con HMAC (mín. 3 s, máx. 2 h; sin JS o sin marca válida se pide un segundo clic con marca nueva); validación de todos los campos con longitudes máximas, correo con `FILTER_VALIDATE_EMAIL` y listas cerradas generadas en el build; saltos de línea y caracteres de control eliminados en nombre, entidad y correo; 5 envíos por IP y hora (fuera de la carpeta pública; la IP se guarda como HMAC; con Cloudflare por delante se usa `CF-Connecting-IP` solo si la petición llega desde sus rangos); respuestas de error sin detalles internos; `Cache-Control: no-store`.
- **CSP**: `form-action 'self'` y `connect-src 'self'` ya estaban en `deploy/htaccess` y cubren el formulario y la petición de la marca: sin cambios. `deploy/preparar.sh` sigue pasando con las páginas nuevas (un solo hash de script en línea). La caché larga solo afecta a `_astro/`; `public/api/.htaccess` añade `no-store` a `enviar.php` y bloquea `listas.php` desde la web.
- **Prueba en local** (PHP 8.5 `php -S`, modo simulado: el aviso se escribe en un fichero): diagnóstico válido → 303 a `/servicios/diagnostico/gracias/` y aviso con todos los campos e intereses; contacto válido → 303 a `/contacto/gracias/`; honeypot relleno → 303 sin aviso; envío inmediato → 429 con el formulario y los datos conservados; correo no válido → 422 con el campo marcado. Además: sin Origin/Referer, Origin ajeno o `null` → 403; GET → 405; JSON → 415; saltos de línea en nombre/entidad neutralizados y en el correo rechazados; valores fuera de lista rechazados; mensaje largo rechazado; petición enorme → 413; sin marca → 200 con aviso y, con la marca nueva, 303; el sexto envío por IP en una hora → 429; el resto de la página devuelta es idéntico al original.
- **Sin probar**: la llamada real a Brevo (no hay clave en esta máquina ni se ha tocado), PHP 7.4 (solo PHP 8.5 local; el código evita sintaxis de 8.x), cURL y `posix` en Raiola y el comportamiento tras Cloudflare. El primer envío real, en staging, con Rai.
- **Pendiente de Rai**: crear `~/config/formulario-entretecas.php` en Raiola (clave de Brevo y un secreto aleatorio), confirmar `rai@entretecas.com` como remitente en Brevo, y revisar la política de privacidad: no menciona que se guarda un resumen de la IP (HMAC) una hora para limitar envíos.

---

## 2026-11-18 (2) — Redirecciones de la web antigua en `deploy/htaccess`

- **Qué se tocó** (local; no es producción, no se ha desplegado; sin backup ni commit): `deploy/htaccess` y `entretecas/textos-web/redirecciones.csv` (columna «destino final», URL completas; motivos de `/servicios/` y del alojamiento con la decisión de Rai).
- **Reglas** (primeras del fichero, antes de la de entretecas.es y de la de https/sin www): 301 a la URL nueva completa en un solo salto para `/alojamiento-optimizado-para-pmb/` → `/planes/`, `/servicios/` → `/#servicios` (NE), `/servicios/migracion-de-registros-bibliograficos/` → `/servicios/migracion/`, `/politica-de-privacidad/` → `/privacidad/`, `/politica-de-cookies/` → `/cookies/`, `sitemap_index.xml` y `wp-sitemap.xml` → `/sitemap-index.xml`. 410 para `page-sitemap.xml`, `xmlrpc.php`, `wp-login.php`, `/feed/`, `/comments/feed/` y todo `/wp-content/`, `/wp-includes/`, `/wp-admin/`. `ErrorDocument 410 /404.html`. `/`, `/contacto/` y `/aviso-legal/` no necesitan regla. Cada regla lleva condición de host `(www.)entretecas.com`, para que en staging no salten a producción.
- **Corrección en la regla de https/sin www**: se le añadió `RewriteCond %{ENV:REDIRECT_STATUS} ^$`. Sin ella, un 410 de una URL entrada por http o con www se convertía en un 301 hacia `/404.html` (la petición interna de la página de error volvía a pasar por la regla).
- **Prueba**: Apache 2.4.59 local (mod_rewrite, headers, ssl; http y https), con el build de `astro build` y `deploy/preparar.sh produccion` en una carpeta temporal. Las 17 filas del CSV × {http, https} × {www, sin www}: 0 fallos. Cada URL con equivalente llega en 1 salto (0 si ya entra por https sin www) y acaba en 200; los 410 responden directos con la página 404 de la web. Staging no redirige.
- **Sin probar**: el comportamiento en Raiola (LiteSpeed). Comprobar tras el primer despliegue de producción, con Rai, que los 410 muestran la página 404 y los 301 son de un salto. `entretecas.es` sigue con su regla propia (las URL antiguas entrando por `.es` hacen dos saltos).

---

## 2026-11-18 — Inventario de la web antigua para las redirecciones

- **Qué se tocó**: solo se lee la web publicada (entretecas.com, WordPress + Elementor 3.29 + Yoast, PHP 7.4, detrás de Cloudflare). Peticiones GET, 1 por segundo, sin formularios, sin seguir redirecciones a ciegas. No se toca el servidor, la web nueva ni `.htaccess`. Resultado en `entretecas/textos-web/redirecciones.csv`. Sin backup (no aplica) ni commit.
- **Hallazgos**: el sitemap solo lista 5 páginas (`sitemap_index.xml` → `page-sitemap.xml`; `sitemap.xml` da 404, `wp-sitemap.xml` da 301). El rastreo a tres niveles solo añade aviso legal, privacidad y cookies. No hay entradas, categorías, etiquetas ni PDF. Host canónico actual: `www` (la raíz sin www da 301 a www); la web nueva es sin www, así que el `.htaccess` debe unir ambos saltos.
- **Pendiente**: reglas en `deploy/htaccess` (esperan OK de Rai). Subdominios `demo.` (302) y `cursos.` (401) no entran.

---

## 2026-11-17 (11) — Revisión final de rendimiento, SEO y seguridad

- **Qué se tocó** (local, sin commit): `global.css` y `tokens.css` (fuente), `Base.astro` (precarga, twitter, JSON-LD por página), `astro.config.mjs`, `Migas.astro`, `[servicio].astro`, `index.astro`, `FormularioContacto.astro` (honeypot), `Revela.astro` (lecturas antes de escrituras), `MesaTaller.astro` (solo `is:inline` → script de módulo; la lógica no cambia), `deploy/htaccess` y `deploy/preparar.sh`, `scripts/subconjunto-fuente.sh` y `fuente-caracteres.txt`, `CLAUDE.md`. Nuevo `src/data/datos-estructurados.ts`, `src/assets/fonts/literata-opsz.woff2`. Textos, diseño y comportamiento visible, sin cambios.
- **Fuente**: subconjunto propio de Literata (latino, wght 400-700, opsz): 75 KB, en vez de 7 ficheros del paquete (el navegador solo bajaba 1-2, ~110 KB). Un único `preload` (el fichero variable sirve titular y cuerpo), `font-display: swap`, reserva «Literata Reserva» sobre Georgia/Georgia Bold con `size-adjust` (109,3 % / 99,3 %) y overrides calculados con el texto real. Paquete `@fontsource-variable/literata` retirado. Regenerar: `scripts/subconjunto-fuente.sh` (necesita fonttools y brotli).
- **Imágenes**: la web no usa imágenes raster (logos SVG con hash, `og.png` y los iconos en `public/`); no había nada que pasar a `astro:assets`/AVIF. Si se añaden fotos, usar `astro:assets`.
- **CSS y JS**: todo el CSS en línea (`inlineStylesheets: 'always'`, ~30 KB sin comprimir por página; así no hay hoja que bloquee la pintura), y los scripts de los componentes en ficheros con hash y módulos diferidos (`assetsInlineLimit: 0`; 4 ficheros de 0,6-1 KB). Sin dependencias en el bundle: las únicas dependencias son Astro y el sitemap.
- **SEO**: ya estaban title/description únicos (ninguno repetido), canónico, `lang="es"`, un h1, sitemap-index, robots con el sitemap y 404 propia (noindex). Añadido: Twitter Card completa (title, description, image), `og:image:type`, y JSON-LD por página: `ProfessionalService` solo en la portada, `Service` en las tres páginas de servicio y `BreadcrumbList` en todas las que tienen migas (antes la organización iba en todas).
- **.htaccess** (`deploy/htaccess`, producción y staging): https y sin www en un salto (ya estaba), HSTS 1 año (sin subdominios ni preload, como antes), CSP, `X-Frame-Options: DENY` + `frame-ancestors 'none'`, `nosniff`, Referrer-Policy, Permissions-Policy ampliada, COOP `same-origin`. Caché: HTML 5 min con revalidación; xml/txt/manifest 1 h; iconos e imágenes sin hash 1 semana; `_astro/` 1 año `immutable` (su `.htaccess`, ya existente). Compresión Brotli + gzip.
- **CSP**: `default-src 'self'; script-src 'self' 'sha256-…'; style-src 'self' 'unsafe-inline'; img-src/font-src/connect-src/manifest-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests`. Sin `'unsafe-inline'` en scripts. Único script en línea: el de la clase `.js`; `deploy/preparar.sh` calcula su hash de `dist/` y lo pone en la CSP, y **falla si hay otro script en línea o hashes distintos** (así un script nuevo en línea no llega a producción roto). `style-src 'unsafe-inline'` es necesario por el CSS en línea y los `style="--min:…"` de las rejillas; decisión consciente.
- **Prueba de CSP**: servidor local con las cabeceras reales del `.htaccess` generado y Chrome headless en 10 rutas (escritorio y móvil): consola limpia (el único mensaje es el 404 esperado de una URL inexistente); animación de la mesa en marcha; formulario con validación y preselección `?servicio=archivos` funcionando.
- **Formularios**: honeypot (`web`, fuera de pantalla, `tabindex=-1`, `aria-hidden`; si llega relleno, el script no hace nada) y validación en el navegador (ya estaba). **Pendiente del servidor al activar el envío (`/api/contacto.php`)**: descartar en silencio si `web` viene con contenido; validar todo de nuevo (nombre, correo con `filter_var`, longitudes máximas, listas cerradas para sistema/registros/bibliotecas/`interes[]`, aceptación obligatoria); aceptar solo POST y `Content-Type` de formulario; comprobar `Origin`/`Referer` de la propia web; limitar por IP (p. ej. pocos envíos por minuto/hora) y tamaño del cuerpo; saneado antes de montar el correo (sin saltos de línea en cabeceras: inyección de cabeceras SMTP); SMTP fuera de la carpeta pública (ya previsto); no registrar datos personales en logs más de lo necesario; respuesta genérica sin eco del contenido; valorar un token temporal/CSRF o un captcha invisible si aparece spam.
- **npm audit**: 9-10 vulnerabilidades «high», todas por **picomatch ≤ 4.0.7** (ReDoS en extglobs), arrastrada por astro, vite, tinyglobby, fdir y unstorage. Es una herramienta de build (no viaja a la web publicada). No hay versión parcheada de picomatch publicada y la «solución» de `npm audit` es `astro@5.3.0` (cambio mayor hacia atrás): **no aplicada**. Hecho solo lo que no cambia versiones mayores: `npm update` (astro 7.4.7 → 7.4.12, sitemap 3.7.4 → 3.7.5) y retirada de `@fontsource-variable/literata`. Revisar de nuevo cuando salga picomatch 4.0.8.
- **Secretos**: sin ficheros `.env`, claves, certificados, `.htpasswd` ni `*.local.*` en el árbol (fuera de `node_modules`, `dist`, `.git`) ni patrones de secreto en el contenido; el repo no tiene commits aún. Nada que reportar.
- **Lighthouse local** (servidor con las cabeceras del `.htaccess`, Lighthouse 13.6.0, dos tandas completas): **100/100/100/100 en las cinco páginas, móvil y escritorio; CLS 0** (antes 0,002-0,025); LCP 0,8 s móvil / 0,2-0,3 s escritorio. Solo queda por debajo de 1 el «network-dependency-tree» (informativo, sin peso en la nota: es la cadena HTML → fuente, inevitable con fuente propia).
- **Nota**: la nota real en producción puede variar algo (red, HTTP/2, Brotli de Raiola). Antes de publicar: lanzar `deploy/preparar.sh produccion` (lo hace el workflow) y comprobar que Raiola acepta las cabeceras (`mod_headers`); si no, la CSP no se aplica y habría que pedirla por el panel.

## 2026-11-17 (10) — /servicios/diagnostico/: sin scroll automático y precio antes del formulario en móvil

- **Qué se tocó**: `FormularioContacto.astro` (se quita el desplazamiento automático de la entrada (9); la preselección solo marca la casilla y la página se queda arriba) y `servicios/[servicio].astro` (CSS de la cabecera con formulario). Local; sin commit.
- **Orden**: por debajo de 1000 px (móvil y tablet, donde la cabecera se apila): título, entradilla, recuadro de precio, formulario, «Qué pasa después». Desde 1000 px, el precio sigue a la derecha. Solo afecta a la cabecera que lleva el formulario (`:has(.pedir)`); `CabezaPagina` no cambia.
- **Comprobado** (390, 820, 999, 1000 y 1280 px): `scrollY` 0 con `?servicio=migracion` y casilla marcada; un solo formulario; sin desbordes; orden correcto en móvil (captura revisada).

## 2026-11-17 (9) — /servicios/diagnostico/: formulario en la cabecera

- **Qué se tocó**: `servicios/[servicio].astro` (solo la rama del diagnóstico, la que lleva `formulario`; el resto de servicios no cambia), `diagnostico.md` (botón del cierre) y el script de preselección de `FormularioContacto.astro`. Sin textos nuevos, colores ni tipografías. Local; sin commit.
- **Cabecera**: el formulario completo (con «¿Qué te interesa?») sustituye al botón «Pedir diagnóstico» y, debajo, va «Qué pasa después». `#pedir` está en el contenedor del formulario. El recuadro de precio sigue a la derecha en escritorio y en móvil queda al final de la cabecera.
- **Cierre**: sin formulario; franja «Empecemos por un diagnóstico.» + «Cuéntame qué sistema tienes… {respuestaContacto}…» + botón «Pedir diagnóstico» → `#pedir` con desplazamiento suave (solo sin movimiento reducido).
- **Preselección**: la cabecera es alta (la casilla quedaba en y=897 con pantalla de 800). Si tras marcar la casilla queda por debajo de la primera pantalla, el script la centra en pantalla (salto directo). Efecto en todas las páginas con formulario completo; en /contacto/ no actúa.
- **Comprobado**: un solo `<form>`; `?servicio=migracion` deja la casilla marcada a 498 px (escritorio); orden en móvil: h1, entradilla, formulario, «Qué pasa después», precio; el botón final sube a `#pedir` quedando a 92 px con la cabecera fija en 76 px (no tapa); sin desbordes.

## 2026-11-17 (8) — Menú fijo y dinamismo sutil

- **Qué se tocó**: `Cabecera.astro` (fija + compacta), `Revela.astro` (nuevo, enganchado en `Base.astro`), `global.css` (aparición, hover de tarjetas, transición de botones, `scroll-margin-top`), `tokens.css` (`--alto-cabecera: 76px`), `Seccion`/`BloqueCTA`/`casos.astro` (margen de anclas). Sin textos, colores, tipografías, estructura ni mesa. Local; sin commit.
- **Menú fijo**: `position: sticky; top: 0` en escritorio y, con JavaScript, en móvil (sin JS el menú va desplegado y tapar media pantalla sería peor). Con scroll > 8 px: línea/sombra muy suave, logo al 85 % y contenido 5 px más abajo con `top: -10px`; transición de 0,2 s. La altura de la cabecera en el flujo no cambia (85,7 px siempre), así que no hay saltos. Anclas con `scroll-margin-top: var(--alto-cabecera) + 1rem` (global y en bloques/casos). El menú móvil sigue abriendo/cerrando y se cierra con Escape devolviendo el foco al botón (probado).
- **Aparición**: un solo `IntersectionObserver` (sin librerías), 10 px + fade en 0,4 s, una vez. Solo oculta lo que está por debajo de la primera pantalla y solo si hay JS; con `prefers-reduced-motion` o sin JS no se oculta nada. Al acabar quita las clases (no estorba al hover). En secciones con tarjetas se animan las tarjetas y los bloques que no las contienen, no ambos.
- **Hover/foco**: tarjetas de servicio y caso (`.tarjeta-enlace`), planes y casos: elevación de 3 px (las enlazadas conservan el borde de marca), también con foco de teclado. Botones: transición de 0,2 s en color de fondo y texto.
- **Comprobado**: compila (13 páginas, todas 200); anclas `#pedir` y `#cunef` quedan a 92 px con la cabecera en 76 px (no tapadas) en escritorio y en móvil; sin desbordes horizontales; en móvil el bloque negro sigue encima del formulario. **Lighthouse local, portada, móvil** (3 pasadas, 13.6.0): rendimiento 100, CLS 0,004, LCP 1,1 s, TBT 0 ms. Ese 0,004 es el `h1` del hero por el cambio de tipografía al cargar; lo ocasiona la carga de la fuente, no los scripts: con los scripts de módulo quitados da igual (0,004).
- **Nota**: la extensión de Chrome falló a mitad de la revisión; las capturas finales se hicieron con Chrome headless (puppeteer-core).

## 2026-11-17 (7) — Ajustes: bloque final de la portada, «¿Qué te interesa?» y preselección

- **Qué se tocó**: `portada.md` y `index.astro` (texto nuevo, botón «Pedir diagnóstico» con el mismo tamaño que el del inicio, sin el enlace «¿Prefieres escribirme?»), `FormularioContacto.astro` (fieldset «¿Qué te interesa?» con casillas `interes[]` y script de preselección), `diagnostico.md`, `planes.md`/`planes.astro`, enlaces de los servicios con `?servicio=`, `content.config.ts` y `.pages.yml`. Local; sin backup (no aplica) ni commit.
- **Preselección**: `?servicio=migracion|implantacion|archivos|plan|formacion` marca la casilla con esa clave; sin parámetro o con uno no válido, no marca nada; sin JavaScript el formulario funciona igual. El canónico sigue siendo `/servicios/diagnostico/` (se genera solo con la ruta). Los botones del menú y de casos no llevan parámetro.
- **Planes** (decisión de Rai): sus dos botones pasan a «Pedir diagnóstico» → `/servicios/diagnostico/?servicio=plan` y se añade, encima del botón de la franja final, «Todos los planes empiezan con un diagnóstico: …».
- **Incidente**: `.pages.yml` tenía bloques duplicados y un campo `formulario` mal colocado en `sobreMi` por ediciones mías anteriores con un `index` mal anclado. Reparado y validado (8 secciones, campos en su sitio). La web compilada no estaba afectada; el CMS sí.
- **Comprobado**: compila (13 páginas); preselección probada con clic desde Migración y desde Planes, y por URL con Archivos; `FormData` incluye todas las casillas marcadas; parámetro inválido o ausente, ninguna marcada.

---

## 2026-11-17 (6) — Navegación: dos formularios y bloque «Qué pasa después» único

- **Qué se tocó**: menú y bloque negro (`sitio.json` + esquema), `QuePasaDespues.astro` (nuevo), `FormularioContacto.astro` (dos variantes), `index.astro`, `contacto.astro`, `servicios/[servicio].astro`, `content.config.ts`, `.pages.yml` y los enlaces de `src/content/**`. Local; sin backup (no aplica) ni commit. Sin cambios de colores, tipografías ni animación.
- **Formularios**: solo dos. Completo (sistema, registros, bibliotecas) al final de `/servicios/diagnostico/` (`#pedir`); corto solo en `/contacto/`. Portada sin formulario.
- **`/contacto/`**: h1 «Hablamos», entradilla, formulario corto, correo y línea legal; sin «Qué pasa después».
- **Bloque negro**: un solo componente, usado en el bloque final de la portada (`#diagnostico`, dos columnas) y junto al formulario completo (derecha desde 1040 px; en móvil, justo debajo del título).
- **Botones**: «Pedir diagnóstico» del inicio de la portada → `#diagnostico` (desplazamiento suave salvo movimiento reducido); menú y páginas de servicios, planes y casos → `/servicios/diagnostico/`; dentro del diagnóstico → `#pedir`. «Contacto» → `/contacto/`.
- **Incidente**: un script mío vació `src/pages/servicios/[servicio].astro` al fallar una edición (el repo no tiene commits). Se restauró íntegro desde la lectura previa del fichero y se reaplicaron los cambios; compila y las páginas de servicio dan 200.
- **Comprobado**: compila (13 páginas); `<form` solo en `/contacto/` y `/servicios/diagnostico/`; bloque negro solo en la portada y el diagnóstico; 0 anclas internas rotas.

---

## 2026-11-17 (5) — Navegación y formularios

- **Qué se tocó**: menú (`sitio.json`), enlaces «Pedir diagnóstico» de los `src/content/**`, `FormularioContacto.astro` (dos variantes: corta y completa), `index.astro`, `contacto.astro`, `servicios/[servicio].astro`, `content.config.ts` y `.pages.yml`. Local; sin backup (no aplica) ni commit. Sin cambios de colores, tipografías ni animación.
- **Menú**: Servicios, Planes, Casos, Sobre mí, Contacto (`/contacto/`) y el botón «Pedir diagnóstico», en escritorio y móvil.
- **Diagnóstico**: la franja final de `/servicios/diagnostico/` lleva el formulario completo (ancla `#pedir`, título «Empecemos por un diagnóstico»). Todos los «Pedir diagnóstico» y el botón del menú van a `/servicios/diagnostico/#pedir`.
- **Portada y `/contacto/`**: el bloque pasa a «Hablamos» (portada, id `hablamos`) con el formulario corto y «¿Lo tienes claro? Pide un diagnóstico». `/contacto/`: h1 «Hablamos» y formulario corto. «Pedir propuesta» de la portada va a `/contacto/`.
- **Rejilla**: tres columnas desde 960 px, dos desde 640 px (el tercero de cada fila a todo el ancho) y una en móvil. Campos y protección (trampa, casilla de privacidad) sin cambios; el envío sigue sin activar.
- **Comprobado**: compila (13 páginas), sin enlaces a `#contacto`, `#escuela-suiza` ni `#anastasio-de-gracia`, y ningún botón de diagnóstico a `/contacto/`.

---

## 2026-11-17 (4) — Animación de la mesa: lupa continua

- **Qué se tocó**: solo `src/components/MesaTaller.astro`, tomado de `textos-web/mesa-lupa-referencia.html`: `@keyframes mesa-lupa` (51 fotogramas, lineal), su regla (3,40 s, retraso 1,30 s), retrasos de los libros (1,84 / 2,33 / 2,86 s) y de la hoja (3,48 s), y la frase de la lupa en el texto alternativo. Colores, variables, tamaño, controles, arranque y estado final, sin cambios. `DURACION` sigue en 6300 ms (el final lo marca «cuelga», 6,2 s). Local; sin backup (no aplica) ni commit.
- **Comprobado**: compila (13 páginas); en local la lupa cruza la caja, los libros y la hoja se colocan, la caja queda en verde y aparece «Ver de nuevo».

---

## 2026-11-17 (3) — Textos: dos ajustes

- **Qué se tocó**: solo textos en `src/content/` (Implantación y portada). Local; sin backup (no aplica) ni commit.
- **Cambios**: Implantación, «**Configuración** adaptada a tu biblioteca:» → «**Configuración**:» (era la etiqueta en negrita de la lista, no una frase); portada, Sobre mí, «Trabajo en toda España» → «Trabajo principalmente en España».

---

## 2026-11-17 (2) — Textos: cierre de pendientes de la revisión de Rai

- **Qué se tocó**: solo textos en `src/content/` y el plazo de respuesta en `src/data/sitio.json` (3 días laborables). Local; no es producción. Sin backup (no aplica) ni commit.
- **Cambios**: caso Anastasio de Gracia → Fundación Manuel Fernández «Lito» (ancla `#fundacion-lito`, tarjeta de la portada con su texto y enlace); «Otro sistema» en los dos formularios; Colegio Estudio 2011; Agustinianas 465.000 en la portada; Cunef «En 2012»; Archivos «(lo vemos en el diagnóstico)»; siete erratas corregidas. Sin enlaces a `#escuela-suiza` ni `#anastasio-de-gracia`.

---

## 2026-11-17 — Textos: revisión de Rai (`textos-revisados.docx`)

- **Qué se tocó**: solo textos en `src/content/` (portada, casos, planes, sobre-mi, y los cuatro servicios). Sin cambios de diseño, componentes ni estructura. Local; no es producción.
- **Backup**: no aplica (sin BD ni producción; todo en git local, sin commit).
- **Sustitución**: Escuela Suiza de Barcelona → Fundación Anastasio de Gracia (AtoM) en `/casos/` (ancla `#anastasio-de-gracia`). En la portada, pendiente (ver abajo).
- **Pendiente de Rai**: tarjeta de la portada (el documento trae Fundación Lito, no Anastasio de Gracia; el enlace `/casos/#escuela-suiza` ya no existe); inconsistencias del documento (plazo de respuesta 3/2 días, 2010/2011, 420 000/465.000, Cunef 2021/2012); erratas sin corregir.

---

_Sin entradas de producción: todavía no se ha tocado producción._
