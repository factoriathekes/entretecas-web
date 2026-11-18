// Genera dist/api/listas.php en el build: las listas cerradas del formulario de diagnóstico (sistemas, registros,
// bibliotecas e intereses), tomadas de src/content/servicios/diagnostico.md. public/api/enviar.php las lee para
// validar, así el servidor y el formulario no se desajustan nunca. No es una página: solo un PHP que devuelve un
// array; public/api/.htaccess impide abrirlo desde la web.
import type { APIRoute } from 'astro';
import { getEntry } from 'astro:content';

export const GET: APIRoute = async () => {
  const entrada = await getEntry('servicios', 'diagnostico');
  const f = entrada?.data.formulario;
  if (!f) throw new Error('Falta el formulario en src/content/servicios/diagnostico.md');

  const listas = {
    sistemas: f.sistemas,
    registros: f.registros,
    bibliotecas: f.bibliotecas.opciones,
    intereses: f.intereses.opciones.map((opcion) => opcion.texto),
  };
  // Cadena PHP entre comillas simples: se escapan \ y '
  const json = JSON.stringify(listas).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  return new Response(`<?php\n// Generado en el build (src/pages/api/listas.php.ts). No editar.\nreturn json_decode('${json}', true);\n`, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
