<?php
// Comprobación semanal de la clave de Brevo (caduca tras 90 días sin uso). Solo se ejecuta desde la línea de
// comandos, lanzada por el cron de cPanel; desde la web da 404 (y api/.htaccess la bloquea además).
//
// Hace una consulta mínima de solo lectura a la API transaccional de Brevo con la misma clave que usa enviar.php.
// Si va bien, no escribe nada. Si falla, escribe UNA línea con el código de error (nunca la clave) y cPanel la
// manda por correo, porque el cron solo envía correo cuando el script saca algo por pantalla.
//
// Compatible con PHP 7.4 o superior. Necesita la extensión cURL.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado';
    exit;
}

// Consulta de solo lectura: lista una plantilla transaccional como máximo (no envía ni cambia nada)
const URL_CONSULTA = 'https://api.brevo.com/v3/smtp/templates?limit=1&offset=0';

function fallo(string $detalle): void
{
    echo '[salud-brevo] ERROR ' . date('Y-m-d H:i') . ' ' . $detalle . PHP_EOL;
    exit(1);
}

// La configuración vive junto a la carpeta de la web, igual que para enviar.php: ../config/formulario-entretecas.php
$archivo = dirname(__DIR__, 2) . '/config/formulario-entretecas.php';
if (!is_file($archivo)) {
    fallo('no se encuentra config/formulario-entretecas.php');
}
$config = require $archivo;
$clave = is_array($config) && is_string($config['brevo_api_key'] ?? null) ? $config['brevo_api_key'] : '';
if ($clave === '' || strpos($clave, 'PEGA_') === 0) {
    fallo('la configuración no tiene la clave de Brevo');
}
if (!function_exists('curl_init')) {
    fallo('falta la extensión cURL de PHP');
}

$ch = curl_init(URL_CONSULTA);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['accept: application/json', 'api-key: ' . $clave],
]);
$respuesta = curl_exec($ch);
$codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errno = curl_errno($ch);
curl_close($ch);
unset($clave, $config);

if ($respuesta === false) {
    fallo('sin respuesta de Brevo (error de red cURL ' . $errno . ')');
}
if ($codigo >= 200 && $codigo < 300) {
    exit(0); // todo bien: no se escribe nada
}

// Código que devuelve Brevo en el cuerpo (p. ej. «unauthorized»); solo letras, cifras y guiones bajos
$datos = json_decode((string) $respuesta, true);
$codigoBrevo = is_array($datos) && isset($datos['code']) && is_string($datos['code']) ? $datos['code'] : '';
$codigoBrevo = substr(preg_replace('/[^A-Za-z0-9_]/', '', $codigoBrevo), 0, 40);
// «message» de Brevo (no lleva la clave): sin saltos de línea y recortado a 300 caracteres
$mensaje = is_array($datos) && isset($datos['message']) && is_string($datos['message']) ? $datos['message'] : '';
$mensaje = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $mensaje)), 0, 300);
fallo('Brevo respondió HTTP ' . $codigo . ($codigoBrevo !== '' ? ' (' . $codigoBrevo . ')' : '') . ($mensaje !== '' ? ' — ' . $mensaje : ''));
