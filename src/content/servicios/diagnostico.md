---
# Textos: «Textos web Entretecas.md» (sección Diagnóstico). Etiquetas y titulares de sección de
# diseno/entretecas-servicio.html. Marcas entre llaves: se rellenan con src/data/sitio.json.
nombre: Diagnóstico
orden: 1
titulo: Diagnóstico de tu biblioteca · Entretecas
descripcion: Antes de migrar o cambiar de sistema, un análisis de tu catálogo y tus datos, con informe y propuesta cerrada. Su coste se descuenta del proyecto.
etiqueta: Servicio 01
lema: El primer paso.
h1: Diagnóstico de tu biblioteca
entradilla:
  - 'Antes de proponerte nada, necesito entender tu biblioteca: qué sistema tienes, cómo están tus datos y qué necesitas. El diagnóstico es ese primer paso. Nos da una foto clara de tu situación y me permite hacer una propuesta cerrada, sin sorpresas.'
boton:
  texto: Pedir diagnóstico
  ruta: '#pedir'
precio:
  etiqueta: Precio y plazo
  antes: Desde
  cifra: '{diagnosticoDesde}'
  despues: sin IVA
  puntos:
    - 'El precio final depende de tu sistema actual, del volumen, tipología y estado. Con la información del formulario te confirmo antes de empezar.'
    - Si seguimos adelante con el proyecto, el coste del diagnóstico se descuenta del total.
    - 'Recibes el informe y la propuesta {plazoDiagnostico} desde que tengo tus datos, según el volumen y el análisis que necesites.'
secciones:
  - etiqueta: Para quién es
    titular: Te sirve si…
    mitad: true
    puntos:
      - Quieres cambiar de programa y no sabes por dónde empezar.
      - Tu catálogo está en una hoja de cálculo o en una base de datos propia.
      - Tu sistema se ha quedado antiguo o nadie lo mantiene.
      - Quieres unir varias bibliotecas en un catálogo colectivo.
      - Necesitas presentar una propuesta con alcance y coste claros.
  - etiqueta: Qué necesito de ti
    titular: Muy poco.
    mitad: true
    puntos:
      - Una exportación de tus datos (todos o una muestra) o un acceso de consulta a tu sistema.
      - Aproximadamente una hora para la reunión inicial.
  - etiqueta: Qué incluye
    titular: Cinco pasos, un resultado que te quedas.
    tipo: pasos
    puntos:
      - '**Una reunión inicial** por videollamada para conocer tu biblioteca y lo que necesitas. Una hora, aproximadamente.'
      - '**Revisión de tu biblioteca y/o datos** a partir de una exportación o de un acceso a tu sistema.'
      - '**Análisis de calidad**: nivel de descripción, autoridades, materias, estado de los registros, duplicados, etc.'
      - '**Un informe** con tu situación, los riesgos y el sistema que te recomiendo, explicado sin tecnicismos.'
      - '**Una propuesta cerrada** con alcance, plazos y costes, lista para valorar.'
# Extracto literal con […] (nota «Uso de los testimonios»: Rosa San Martín en Diagnóstico y Sobre mí)
cita:
  texto: Es sorprendente su capacidad para analizar cualquier proyecto y/o problema que le presentes y exponerte —de forma muy didáctica— una pluralidad de soluciones creativas, planteándote los pros y contras de cada una de ellas […]
  autoria: Rosa San Martín · Responsable de la Biblioteca de CUNEF
preguntas:
  etiqueta: Preguntas frecuentes
  titular: Lo que suelen preguntarme.
  preguntas:
    - pregunta: ¿Por qué es de pago?
      respuesta: Porque es un trabajo real de análisis, y porque te deja un resultado útil decidas lo que decidas.
    - pregunta: ¿Y si después no contrato el proyecto?
      respuesta: El informe es tuyo. Puedes usarlo para decidir o como base para pedir otras propuestas.
    - pregunta: ¿Puedo seguir usando mi sistema mientras tanto?
      respuesta: Sí. Siempre que se pueda, trabajo sobre una copia de tus datos. Si no es posible sacarla, uso un acceso de solo consulta a tu sistema y no modifico nada. Si tampoco hay acceso de consulta, acordamos contigo cómo hacerlo antes de empezar.
otros:
  etiqueta: Otros servicios
  titular: Después del diagnóstico.
cta:
  titular: Empecemos por un diagnóstico.
  texto: Cuéntame qué sistema tienes y qué necesitas. Te respondo en {respuestaContacto} como máximo.
  boton:
    texto: Pedir diagnóstico
    ruta: '#pedir'
# Formulario completo de la cabecera (ancla #pedir)
formulario:
  sistemas:
    - PMB
    - Koha
    - AtoM
    - Otro sistema
    - Hoja de cálculo o base de datos
    - Todavía ninguno
  registros:
    - Menos de 5.000
    - De 5.000 a 20.000
    - De 20.000 a 100.000
    - Más de 100.000
    - No lo sé
  bibliotecas:
    leyenda: Bibliotecas
    opciones:
      - Una
      - Varias (catálogo colectivo)
  # Casillas «¿Qué te interesa?» (varias, ninguna obligatoria). `clave` = valor de ?servicio= que la marca.
  intereses:
    leyenda: ¿Qué te interesa?
    opciones:
      - texto: Migración del catálogo
        clave: migracion
      - texto: Implantación de PMB o Koha
        clave: implantacion
      - texto: Archivos con AtoM
        clave: archivos
      - texto: Plan gestionado
        clave: plan
      - texto: Formación
        clave: formacion
      - texto: Aún no lo sé
  # Mismo texto legal que la portada y /contacto/. Lo que va entre corchetes enlaza a la política de privacidad.
  aceptacion: Acepto la [política de privacidad]
  privacidad: Usaré tus datos solo para responderte. Más información en la [política de privacidad].
  boton: Enviar
---
