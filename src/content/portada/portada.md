---
# Textos definitivos: diseno/entretecas-portada.html (decisión de Rai, 2026-11-06).
# Las marcas entre llaves ({anos}, {bibliotecas}, {correo}, {respuestaContacto}) se rellenan con src/data/sitio.json.
titulo: 'Entretecas · PMB, Koha y AtoM: implantación, migración y soporte'
descripcion: Implanto, migro y mantengo el sistema de gestión de tu biblioteca con software libre. Yo me ocupo de la parte técnica; tú, de la biblioteca.

hero:
  etiqueta: PMB · Koha · AtoM
  titular: Tu catálogo, en manos de un técnico que también es bibliotecario.
  entradilla: Implanto, migro y mantengo el sistema de gestión de tu biblioteca con software libre. Yo me ocupo de la parte técnica; tú, de la biblioteca.
  botonPrincipal:
    texto: Pedir diagnóstico
    ruta: '#diagnostico'
  botonSecundario:
    texto: Ver servicios
    ruta: '#servicios'
  nota: '{anos} años de experiencia · Más de {bibliotecas} bibliotecas confían hoy en Entretecas'

idiomas:
  titular: El bibliotecario sabe lo que necesita. El técnico sabe cómo instalarlo. Yo hablo los dos idiomas.
  puntos:
    - titulo: Bibliotecario de formación
      texto: Catalogación, autoridades, materias, clasificación. Entiendo cómo trabaja tu biblioteca antes de tocar el sistema.
    - titulo: Técnico por trayectoria
      texto: Instalo, migro y mantengo el sistema, y te explico lo que necesitas saber.
    - titulo: Siempre la misma persona
      texto: Sin centralitas ni intermediarios. Hablas conmigo desde el primer correo hasta el día a día.

servicios:
  etiqueta: Servicios
  titular: Desde el primer análisis al día a día.
  tarjetas:
    - nombre: Diagnóstico
      texto: Antes de proponer nada, analizo tu catálogo, tus datos y lo que necesitas. Recibes un informe completo y una propuesta cerrada. Si seguimos adelante, su coste se descuenta del proyecto.
      ruta: /servicios/diagnostico/
    - nombre: Migración del catálogo
      texto: Paso tu catálogo desde otro programa, una hoja de cálculo o una base de datos a PMB o Koha. Lo probamos y validamos juntos antes de ponerlo en marcha.
      ruta: /servicios/migracion/
    - nombre: Implantación
      texto: Instalo y configuro el sistema, adapto el catálogo en línea a la imagen de tu centro y te enseño a usarlo.
      ruta: /servicios/implantacion/
    - nombre: Archivos con AtoM
      texto: Descripción según ISAD(G), migración de inventarios y un portal de consulta en línea para tu archivo, publicando solo lo que decidas.
      ruta: /servicios/archivos/
    - nombre: Plan gestionado
      texto: Alojamiento, copias de seguridad, actualizaciones y soporte por una cuota fija. Tú te ocupas de la biblioteca; yo, del sistema.
      ruta: /planes/
  # Sexta posición de la rejilla: tarjeta en color de marca
  llamada:
    texto: ¿No sabes por dónde empezar?
    boton:
      texto: Pedir diagnóstico
      ruta: /servicios/diagnostico/

planes:
  etiqueta: Planes gestionados
  titular: Un coste fijo y previsible. El alojamiento va incluido.
  planes:
    - nombre: Esencial
      lema: Tu sistema alojado, seguro y al día.
      puntos:
        - Alojamiento y copias de seguridad
        - Actualizaciones y seguridad
        - Catálogo en línea con la imagen de tu centro
        - Resolución de incidencias del sistema
    - nombre: Acompañado
      lema: Todo el plan Esencial y alguien con quien contar cada mes.
      destacado: true
      puntos:
        - 'Una hora al mes para tus peticiones: cargas de usuarios, informes, ajustes'
        - Informe trimestral del estado de tu sistema
        - Guías de uso
    - nombre: Profesional
      lema: Para bibliotecas con más actividad.
      puntos:
        - Tres horas al mes para tus peticiones
        - Catálogo en línea personalizado
        - Una sesión de formación al año
  nota: 'Cada biblioteca es distinta: el precio depende de tu sistema y de lo que necesites. Te lo detallo en la propuesta. PMB o Koha para bibliotecas, o AtoM, si tu fondo es archivístico.'
  boton:
    texto: Pedir propuesta
    ruta: /contacto/

proceso:
  etiqueta: Cómo trabajamos
  titular: Sin sorpresas, de principio a fin.
  pasos:
    - titulo: Diagnóstico
      texto: Empezamos por entender tu biblioteca y tus datos.
    - titulo: Propuesta cerrada
      texto: Alcance, plazos y precio claros, lista para presentar y valorar.
    - titulo: Puesta en marcha
      texto: Migramos e implantamos, con pruebas antes de cada paso.
    - titulo: Mantenimiento
      texto: Tu sistema al día, con un informe periódico de su estado.

casos:
  etiqueta: Casos
  titular: Algunas bibliotecas con las que trabajo.
  casos:
    - categoria: Koha · catálogo colectivo
      nombre: Catálogo Colectivo de Bibliotecas Agustinianas
      texto: Migración de más de 465.000 registros de seis bibliotecas a un único catálogo Koha actualizado y con soporte para todas.
      ruta: /casos/#agustinianas
    - categoria: PMB · colegio
      nombre: Colegio Estudio
      texto: Implantación de PMB y migración de registros, con soporte técnico y acompañamiento en la gestión bibliotecaria desde 2011.
      ruta: /casos/#colegio-estudio
    - categoria: AtoM · Fundación Lito
      nombre: Fundación Manuel Fernández «Lito»
      texto: Implantación de AtoM personalizado. Conversión de descripciones a ISAD(G) y migraciones periódicas.
      ruta: /casos/#fundacion-lito
    - categoria: Consultoría · universidad
      nombre: Cunef
      texto: Asesoría en la implantación y gestión de Ex Libris Alma, y soporte técnico de la web de la biblioteca.
      ruta: /casos/#cunef
  # Extracto literal con […] donde se corta (nota «Uso de los testimonios» de los textos); completo en /casos/
  cita:
    texto: Ha respondido a nuestras demandas con rapidez y eficacia, tanto durante la implementación del OPAC, como en nuestras necesidades de soporte en el largo plazo […]
    autoria: Irene Ariza · Colegio Estudio

sobreMi:
  etiqueta: Sobre mí
  titular: Rai Alcázar
  parrafos:
    - Bibliotecario de formación, técnico por trayectoria. Desde hace más de {anos} años ayudo a bibliotecas, archivos y centros de documentación a dar el salto al software libre mediante la implantación, migración de datos y soporte técnico de sistemas como Koha, PMB o AtoM.
    - 'Hablo el idioma de tu biblioteca y el de tus servidores: uno el conocimiento documental con la solvencia informática para ofrecer soluciones a medida a colegios, universidades, fundaciones y asociaciones sin departamento técnico propio. Trabajo principalmente en España, en castellano y en catalán, con un trato siempre directo conmigo.'

# Bloque final (ancla #diagnostico): sin formulario; a la derecha, «Qué pasa después» (sitio.json)
contacto:
  titular: Empecemos por un diagnóstico.
  texto: Antes de proponerte nada, necesito entender tu biblioteca. El diagnóstico nos da una foto clara de tu situación y me permite hacer una propuesta cerrada, sin sorpresas.
  boton:
    texto: Pedir diagnóstico
    ruta: /servicios/diagnostico/
---
