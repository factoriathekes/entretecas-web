// Datos estructurados (JSON-LD, schema.org). La organización va solo en la portada; cada página de servicio
// añade su Service (en [servicio].astro) y las migas su BreadcrumbList (en Migas.astro).
import { sitio } from './sitio';

export const idOrganizacion = `${sitio.url}/#entretecas`;

export function organizacion() {
  return {
    '@context': 'https://schema.org',
    '@type': 'ProfessionalService',
    '@id': idOrganizacion,
    name: sitio.nombre,
    url: sitio.url,
    description: sitio.descripcion,
    email: sitio.correo,
    logo: `${sitio.url}/icon-512.png`,
    image: `${sitio.url}/og.png`,
    areaServed: { '@type': 'Country', name: 'España' },
    knowsAbout: ['PMB', 'Koha', 'AtoM', 'Migración de catálogos', 'Sistemas integrados de gestión bibliotecaria'],
    parentOrganization: {
      '@type': 'Organization',
      name: sitio.titular.nombreComercial,
      legalName: sitio.titular.razonSocial,
      taxID: sitio.titular.cif,
      url: sitio.titular.web,
    },
  };
}

export function servicio(datos: { nombre: string; descripcion: string; url: string }) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Service',
    name: datos.nombre,
    description: datos.descripcion,
    url: datos.url,
    serviceType: datos.nombre,
    provider: { '@type': 'ProfessionalService', '@id': idOrganizacion, name: sitio.nombre, url: sitio.url },
    areaServed: { '@type': 'Country', name: 'España' },
  };
}

export function migas(items: { nombre: string; url: string }[]) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, i) => ({ '@type': 'ListItem', position: i + 1, name: item.nombre, item: item.url })),
  };
}
