import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import sitio from './src/data/sitio.json' with { type: 'json' };

// URLs con barra final (/servicios/diagnostico/), como fija el brief: cada página es una carpeta con index.html
export default defineConfig({
  site: sitio.url,
  trailingSlash: 'always',
  build: {
    format: 'directory',
    // Todo el CSS en línea: ninguna hoja bloquea la pintura (el HTML ya viaja comprimido y se revalida).
    inlineStylesheets: 'always',
  },
  // Sin scripts en línea: los de los componentes salen a ficheros con hash (módulos diferidos), así la CSP
  // no necesita 'unsafe-inline' en script-src. (El único script en línea es el de la clase .js, con hash.)
  vite: { build: { assetsInlineLimit: 0 } },
  integrations: [sitemap({
      // Fuera del sitemap: la 404 y las páginas de gracias de los formularios (también llevan noindex)
      filter: (pagina) => !pagina.endsWith('/404/') && !pagina.endsWith('/gracias/'),
    })],
});
