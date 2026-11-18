<?php
// PLANTILLA, sin datos reales. La copia real va en el servidor (Raiola), FUERA de la carpeta pública:
//
//     ~/config/formulario-entretecas.php      (es decir, junto a public_html, no dentro)
//
// Se crea a mano una sola vez (cPanel → Administrador de archivos, en la raíz de la cuenta, carpeta «config»;
// si no existe, se crea) y se vuelve a tocar solo si cambia la clave. El despliegue no la sube ni la borra.
// No se versiona: no copies aquí la clave real. Permisos recomendados: 600.
// La leen public/api/enviar.php y public/api/salud.php en dirname(DOCUMENT_ROOT)/config/formulario-entretecas.php,
// es decir, junto a la carpeta de la web de cada entorno (así vale para staging y producción).
//
// Los avisos de producción y de staging.entretecas.com usan este mismo fichero (misma cuenta de Raiola);
// los de staging llevan «[PRUEBAS]» en el asunto.
return [
    // Clave de la API de Brevo (Brevo → SMTP y API → Claves de API → «Generar una nueva clave»).
    // El remitente rai@entretecas.com debe estar validado en Brevo (Remitentes, dominios e IP dedicadas).
    'brevo_api_key' => 'PEGA_AQUI_LA_CLAVE_DE_BREVO',

    // Cadena aleatoria larga (mínimo 16 caracteres) que firma la marca de tiempo del formulario y disimula las
    // IP del límite de envíos. No es de Brevo: la inventas tú, por ejemplo con «openssl rand -hex 32» en tu Mac.
    'secreto' => 'GENERA_UNA_CADENA_ALEATORIA_LARGA',
];
