// Texto en línea de los contenidos: admite **negrita** y [enlace](/ruta/) además de las marcas de rellenar().
// Escapa el HTML antes de convertir: en los contenidos no se escribe HTML a mano.
import { rellenar } from './sitio';

const escapar = (texto: string) => texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

export const enLinea = (texto: string) =>
  escapar(rellenar(texto))
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, enlace: string, ruta: string) => {
      if (!/^(\/|#|https:\/\/|mailto:)/.test(ruta)) throw new Error(`Enlace no válido en un texto: ${ruta}`);
      return `<a href="${ruta}">${enlace}</a>`;
    });
