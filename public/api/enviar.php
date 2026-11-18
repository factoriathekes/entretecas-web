<?php
// Envío de los dos formularios de la web (diagnóstico y contacto) por la API transaccional de Brevo.
// Recibe POST de /servicios/diagnostico/ y /contacto/, valida en el servidor, manda el aviso a
// DESTINATARIO y redirige (303) a la página de gracias. Si algo falla, devuelve el mismo formulario con los
// datos conservados y un mensaje claro: todo funciona sin JavaScript.
//
// La clave de Brevo NO está aquí ni en el repositorio: se lee de ../config/formulario-entretecas.php (junto a la carpeta de la web), fuera de la carpeta pública
// (plantilla: deploy/config-formulario.ejemplo.php). Este script nunca la muestra ni la registra.
//
// Compatible con PHP 7.4 o superior. Necesita la extensión cURL y mbstring.

declare(strict_types=1);

// ---------------------------------------------------------------------------------------------------------
// Ajustes
// ---------------------------------------------------------------------------------------------------------

// Fichero de configuración con la clave de Brevo y carpeta de los contadores: se calculan a partir de la carpeta
// pública (dirname(DOCUMENT_ROOT)), es decir, la carpeta que contiene la de la web. Así sirven igual para staging y
// producción y no llevan el usuario de la cuenta. Con Raiola: ~/config/formulario-entretecas.php y
// ~/tmp/entretecas-formulario/.
function ruta_fuera_de_la_web(string $relativa): string
{
    $raiz = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($raiz === '') {
        $raiz = dirname(__DIR__); // la carpeta pública es la que contiene api/
    }
    return dirname($raiz) . '/' . $relativa;
}

const DESTINATARIO = 'rai@entretecas.com';
const REMITENTE = 'rai@entretecas.com';          // debe ser un remitente validado en Brevo
const NOMBRE_REMITENTE = 'Web Entretecas';
const HOST_PRUEBAS = 'staging.entretecas.com';   // sus avisos llevan «[PRUEBAS]» delante del asunto
const HOSTS_PERMITIDOS = ['entretecas.com', 'www.entretecas.com', 'staging.entretecas.com'];

const SEGUNDOS_MINIMOS = 3;      // tiempo mínimo entre cargar la página y enviar
const SEGUNDOS_MAXIMOS = 7200;   // una marca más vieja se considera caducada
const ENVIOS_POR_HORA = 5;       // por IP
const BYTES_MAXIMOS = 40000;     // tamaño máximo de la petición

// Prueba local: si existe esta variable de entorno (solo se puede poner en el servidor), el aviso se añade al
// fichero que indica en vez de llamarse a Brevo, y no hace falta el fichero de configuración. En Raiola no existe.
const VAR_SIMULAR = 'ENTRETECAS_FORM_SIMULAR';
const VAR_TMP_PRUEBA = 'ENTRETECAS_FORM_TMP';

// Rangos de Cloudflare (https://www.cloudflare.com/ips/): solo si la petición llega por ahí se fía de la IP
// que declara CF-Connecting-IP. Si no, todos los visitantes compartirían la IP de Cloudflare y el límite por IP
// bloquearía a todos a la vez.
const RANGOS_CLOUDFLARE = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

// Los formularios que se aceptan: página de gracias, página del formulario (para volver a mostrarlo) e id
const FORMULARIOS = [
    'diagnostico' => [
        'etiqueta' => 'Diagnóstico',
        'ruta' => '/servicios/diagnostico/',
        'gracias' => '/servicios/diagnostico/gracias/',
        'pagina' => '/servicios/diagnostico/index.html',
        'id' => 'diagnostico',
    ],
    'contacto' => [
        'etiqueta' => 'Contacto',
        'ruta' => '/contacto/',
        'gracias' => '/contacto/gracias/',
        'pagina' => '/contacto/index.html',
        'id' => 'formulario-contacto',
    ],
];

date_default_timezone_set('Europe/Madrid');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------------------------------------

function simulando(): ?string
{
    $ruta = getenv(VAR_SIMULAR);
    return ($ruta !== false && $ruta !== '') ? $ruta : null;
}

function sin_cache(): void
{
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

// Respuesta mínima, sin detalles internos
function corta(int $estado, string $texto = 'No se puede procesar la solicitud.', array $cabeceras = []): void
{
    http_response_code($estado);
    sin_cache();
    foreach ($cabeceras as $cabecera) {
        header($cabecera);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $texto, "\n";
    exit;
}

function registrar(string $mensaje): void
{
    // Nunca datos personales ni la clave: solo qué ha pasado
    error_log('[formulario-entretecas] ' . $mensaje);
}

function ruta_config(): ?string
{
    $archivo = ruta_fuera_de_la_web('config/formulario-entretecas.php');
    return @is_file($archivo) ? $archivo : null;
}

// Devuelve la configuración ['brevo_api_key' => …, 'secreto' => …] o null si falta o está sin rellenar
function cargar_config(): ?array
{
    if (simulando() !== null) {
        $secreto = getenv('ENTRETECAS_FORM_SECRETO');
        return ['brevo_api_key' => '', 'secreto' => $secreto ? $secreto : 'secreto-solo-para-pruebas'];
    }
    $ruta = ruta_config();
    if ($ruta === null) {
        registrar('no se encuentra config/formulario-entretecas.php junto a la carpeta de la web');
        return null;
    }
    $config = require $ruta;
    $clave = is_array($config) ? ($config['brevo_api_key'] ?? '') : '';
    $secreto = is_array($config) ? ($config['secreto'] ?? '') : '';
    if (!is_string($clave) || !is_string($secreto) || $clave === '' || strlen($secreto) < 16
        || strpos($clave, 'PEGA_') === 0 || strpos($secreto, 'GENERA_') === 0) {
        registrar('la configuración está incompleta o sin rellenar');
        return null;
    }
    return ['brevo_api_key' => $clave, 'secreto' => $secreto];
}

function carpeta_limite(): ?string
{
    $prueba = getenv(VAR_TMP_PRUEBA);
    if ($prueba) {
        return $prueba;
    }
    if (simulando() !== null) {
        return sys_get_temp_dir() . '/entretecas-formulario-prueba';
    }
    return ruta_fuera_de_la_web('tmp/entretecas-formulario');
}

function ip_en_rangos(string $ip, array $rangos): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($rangos as $rango) {
        list($red, $bits) = explode('/', $rango);
        $binRed = inet_pton($red);
        if ($binRed === false || strlen($binRed) !== strlen($bin)) {
            continue;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $resto = $bits % 8;
        if (substr($bin, 0, $bytes) !== substr($binRed, 0, $bytes)) {
            continue;
        }
        if ($resto === 0) {
            return true;
        }
        $mascara = (0xFF << (8 - $resto)) & 0xFF;
        if ((ord($bin[$bytes]) & $mascara) === (ord($binRed[$bytes]) & $mascara)) {
            return true;
        }
    }
    return false;
}

function ip_cliente(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && ip_en_rangos($ip, RANGOS_CLOUDFLARE)) {
        return $cf;
    }
    return $ip !== '' ? $ip : 'desconocida';
}

// ---------------------------------------------------------------------------------------------------------
// Origen: solo el propio dominio (y el de pruebas)
// ---------------------------------------------------------------------------------------------------------

// Devuelve el host (sin puerto) de Origin o, si no viene, de Referer; null si no es de un dominio permitido
function host_de_origen(): ?string
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origen === '') {
        $origen = $_SERVER['HTTP_REFERER'] ?? '';
    }
    if (!is_string($origen) || $origen === '') {
        return null;
    }
    $partes = parse_url($origen);
    if (!is_array($partes) || empty($partes['host']) || !in_array($partes['scheme'] ?? '', ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower($partes['host']);
    $permitidos = HOSTS_PERMITIDOS;
    if (simulando() !== null) {
        $permitidos[] = 'localhost';
        $permitidos[] = '127.0.0.1';
    }
    return in_array($host, $permitidos, true) ? $host : null;
}

// ---------------------------------------------------------------------------------------------------------
// Marca de tiempo firmada
// ---------------------------------------------------------------------------------------------------------

function emitir_token(string $secreto): string
{
    $t = (string) time();
    return $t . '.' . hash_hmac('sha256', 'entretecas-formulario|' . $t, $secreto);
}

// 'ok' | 'rapido' | 'falta' (no viene, mal firmada o caducada)
function estado_token(string $token, string $secreto): string
{
    if (!preg_match('/^(\d{9,12})\.([0-9a-f]{64})$/', $token, $m)) {
        return 'falta';
    }
    if (!hash_equals(hash_hmac('sha256', 'entretecas-formulario|' . $m[1], $secreto), $m[2])) {
        return 'falta';
    }
    $edad = time() - (int) $m[1];
    if ($edad < 0 || $edad > SEGUNDOS_MAXIMOS) {
        return 'falta';
    }
    return $edad < SEGUNDOS_MINIMOS ? 'rapido' : 'ok';
}

// ---------------------------------------------------------------------------------------------------------
// Validación
// ---------------------------------------------------------------------------------------------------------

// Listas permitidas (sistemas, registros, bibliotecas, intereses), generadas en el build desde el contenido
// de la web (src/pages/api/listas.php.ts): así nunca se desajustan con el formulario.
function cargar_listas(): array
{
    $archivo = __DIR__ . '/listas.php';
    $listas = @is_file($archivo) ? require $archivo : null;
    if (!is_array($listas) || empty($listas['sistemas']) || empty($listas['registros'])
        || empty($listas['bibliotecas']) || empty($listas['intereses'])) {
        registrar('falta api/listas.php o está incompleto (¿se ha hecho el build?)');
        corta(500, 'El formulario no está disponible ahora mismo.');
    }
    return $listas;
}

// Texto de una línea: sin saltos de línea ni caracteres de control (va a asunto y cabeceras)
function limpiar_linea(string $valor): string
{
    $valor = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $valor);
    return trim((string) preg_replace('/ {2,}/', ' ', (string) $valor));
}

// Texto de varias líneas: saltos normalizados y sin otros caracteres de control
function limpiar_texto(string $valor): string
{
    $valor = str_replace(["\r\n", "\r"], "\n", $valor);
    $valor = preg_replace('/[^\P{Cc}\n\t]+/u', '', $valor);
    $valor = preg_replace('/\n{4,}/', "\n\n\n", (string) $valor);
    return trim((string) $valor);
}

function escalar($valor): ?string
{
    return is_string($valor) && mb_check_encoding($valor, 'UTF-8') ? $valor : null;
}

// Devuelve [valores limpios, errores por campo]
function validar(string $tipo, array $entrada, array $listas): array
{
    $v = [];
    $e = [];

    $linea = function (string $campo, int $max, bool $obligatorio, string $falta, string $largo) use (&$v, &$e, $entrada): void {
        $crudo = $entrada[$campo] ?? '';
        $texto = escalar($crudo);
        if ($texto === null) {
            $v[$campo] = '';
            $e[$campo] = $falta;
            return;
        }
        $texto = limpiar_linea($texto);
        $v[$campo] = $texto;
        if ($texto === '' && $obligatorio) {
            $e[$campo] = $falta;
        } elseif (mb_strlen($texto) > $max) {
            $e[$campo] = $largo;
        }
    };

    $linea('nombre', 100, true, 'Escribe tu nombre.', 'El nombre es demasiado largo (máximo 100 caracteres).');
    $linea('correo', 150, true, 'Escribe tu correo.', 'El correo es demasiado largo (máximo 150 caracteres).');
    if (!isset($e['correo']) && !filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) {
        $e['correo'] = 'Revisa el correo: debe ser como nombre@biblioteca.es.';
    }
    $linea('telefono', 30, false, 'Revisa el teléfono.', 'El teléfono es demasiado largo (máximo 30 caracteres).');
    if (!isset($e['telefono']) && $v['telefono'] !== '' && !preg_match('/^\+?[0-9 ().\-]{6,30}$/', $v['telefono'])) {
        $e['telefono'] = 'Revisa el teléfono: solo números, espacios y un + al principio.';
    }
    $linea('entidad', 150, true, 'Escribe el nombre de tu biblioteca o entidad.', 'El nombre de la entidad es demasiado largo (máximo 150 caracteres).');

    // Mensaje (varias líneas)
    $mensaje = escalar($entrada['mensaje'] ?? '');
    $v['mensaje'] = $mensaje === null ? '' : limpiar_texto($mensaje);
    if ($v['mensaje'] === '') {
        $e['mensaje'] = 'Cuéntame en pocas palabras qué necesitas.';
    } elseif (mb_strlen($v['mensaje']) > 5000) {
        $e['mensaje'] = 'El mensaje es demasiado largo (máximo 5.000 caracteres).';
    }

    // Casilla de privacidad: obligatoria
    $v['privacidad'] = (escalar($entrada['privacidad'] ?? '') === 'acepto') ? 'acepto' : '';
    if ($v['privacidad'] === '') {
        $e['privacidad'] = 'Para enviar el formulario, acepta la política de privacidad.';
    }

    // Solo el diagnóstico: tres desplegables obligatorios con lista cerrada y las casillas de intereses
    $v['sistema'] = $v['registros'] = $v['bibliotecas'] = '';
    $v['interes'] = [];
    if ($tipo === 'diagnostico') {
        $cerrados = [
            'sistema' => ['sistemas', 'Elige tu sistema actual.'],
            'registros' => ['registros', 'Elige el número aproximado de registros.'],
            'bibliotecas' => ['bibliotecas', 'Elige si es una biblioteca o varias.'],
        ];
        foreach ($cerrados as $campo => $datos) {
            $valor = escalar($entrada[$campo] ?? '');
            if ($valor !== null && in_array($valor, $listas[$datos[0]], true)) {
                $v[$campo] = $valor;
            } else {
                $e[$campo] = $datos[1];
            }
        }
        $marcadas = $entrada['interes'] ?? [];
        if (!is_array($marcadas) || count($marcadas) > count($listas['intereses'])) {
            $e['interes'] = 'Alguna de las opciones elegidas no es válida.';
        } else {
            foreach ($marcadas as $marcada) {
                $marcada = escalar($marcada);
                if ($marcada === null || !in_array($marcada, $listas['intereses'], true)) {
                    $e['interes'] = 'Alguna de las opciones elegidas no es válida.';
                    continue;
                }
                $v['interes'][] = $marcada;
            }
            $v['interes'] = array_values(array_unique($v['interes']));
        }
    }
    return [$v, $e];
}

// ---------------------------------------------------------------------------------------------------------
// Límite por IP
// ---------------------------------------------------------------------------------------------------------

// Cuenta el envío de esta IP en la última hora. false si ya ha llegado al máximo. Se guarda un resumen de la IP
// (HMAC con el secreto, no la IP) y solo las marcas de hora; los ficheros de más de una hora se borran.
// Devuelve null si no se puede escribir en la carpeta (no se envía: mejor un aviso que un buzón sin protección).
function permitir_envio(string $secreto): ?bool
{
    $carpeta = carpeta_limite();
    if ($carpeta === null || (!is_dir($carpeta) && !@mkdir($carpeta, 0700, true) && !is_dir($carpeta)) || !is_writable($carpeta)) {
        registrar('no se puede escribir en la carpeta del límite por IP');
        return null;
    }
    foreach (glob($carpeta . '/*.json') ?: [] as $viejo) {
        if (@filemtime($viejo) < time() - 3600) {
            @unlink($viejo);
        }
    }
    $archivo = $carpeta . '/' . substr(hash_hmac('sha256', ip_cliente(), $secreto), 0, 32) . '.json';
    $f = @fopen($archivo, 'c+');
    if ($f === false || !flock($f, LOCK_EX)) {
        registrar('no se puede abrir el contador del límite por IP');
        return null;
    }
    $marcas = json_decode((string) stream_get_contents($f), true);
    $marcas = is_array($marcas) ? array_values(array_filter($marcas, function ($t) {
        return is_int($t) && $t > time() - 3600;
    })) : [];
    $permitido = count($marcas) < ENVIOS_POR_HORA;
    if ($permitido) {
        $marcas[] = time();
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, json_encode($marcas));
    }
    flock($f, LOCK_UN);
    fclose($f);
    return $permitido;
}

// ---------------------------------------------------------------------------------------------------------
// Mensaje y envío
// ---------------------------------------------------------------------------------------------------------

function construir_aviso(string $tipo, array $v, string $host, string $pagina): array
{
    $etiqueta = FORMULARIOS[$tipo]['etiqueta'];
    $asunto = ($host === HOST_PRUEBAS ? '[PRUEBAS] ' : '') . '[' . $etiqueta . '] ' . $v['nombre'] . ' — ' . $v['entidad'];
    $asunto = limpiar_linea($asunto);
    if (mb_strlen($asunto) > 200) {
        $asunto = mb_substr($asunto, 0, 199) . '…';
    }

    $lineas = [
        'Formulario: ' . $etiqueta,
        'Fecha: ' . date('d/m/Y H:i:s') . ' (hora de España)',
        'Página de origen: https://' . $host . $pagina,
        '',
        'Nombre: ' . $v['nombre'],
        'Correo: ' . $v['correo'],
        'Teléfono: ' . ($v['telefono'] !== '' ? $v['telefono'] : '(no indicado)'),
        'Biblioteca o entidad: ' . $v['entidad'],
    ];
    if ($tipo === 'diagnostico') {
        $lineas[] = 'Sistema actual: ' . $v['sistema'];
        $lineas[] = 'Número aproximado de registros: ' . $v['registros'];
        $lineas[] = 'Bibliotecas: ' . $v['bibliotecas'];
        $lineas[] = '¿Qué te interesa?: ' . ($v['interes'] ? implode('; ', $v['interes']) : '(ninguna marcada)');
    }
    $lineas[] = 'Política de privacidad: aceptada';
    $lineas[] = '';
    $lineas[] = ($tipo === 'diagnostico' ? '¿Qué necesitas?' : 'Mensaje') . ':';
    $lineas[] = $v['mensaje'];

    return [$asunto, implode("\n", $lineas) . "\n"];
}

// true si se ha enviado. Registra el motivo (sin datos personales ni clave) si falla.
function enviar_aviso(array $config, string $asunto, string $texto, string $correoVisitante, string $nombreVisitante): bool
{
    $carga = [
        'sender' => ['name' => NOMBRE_REMITENTE, 'email' => REMITENTE],
        'to' => [['email' => DESTINATARIO]],
        'replyTo' => ['email' => $correoVisitante, 'name' => $nombreVisitante],
        'subject' => $asunto,
        'textContent' => $texto,
    ];

    $archivoPrueba = simulando();
    if ($archivoPrueba !== null) {
        $registro = "=== " . date('c') . " ===\n"
            . 'From: ' . NOMBRE_REMITENTE . ' <' . REMITENTE . ">\n"
            . 'To: ' . DESTINATARIO . "\n"
            . 'Reply-To: ' . $nombreVisitante . ' <' . $correoVisitante . ">\n"
            . 'Subject: ' . $asunto . "\n\n" . $texto . "\n";
        return @file_put_contents($archivoPrueba, $registro, FILE_APPEND | LOCK_EX) !== false;
    }

    if (!function_exists('curl_init')) {
        registrar('falta la extensión cURL de PHP');
        return false;
    }
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $config['brevo_api_key']],
        CURLOPT_POSTFIELDS => json_encode($carga, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fallo = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    if ($codigo >= 200 && $codigo < 300) {
        return true;
    }
    // Solo el código HTTP y el «code» de Brevo (p. ej. unauthorized, invalid_parameter): ni cuerpo ni cabeceras
    $respuesta = is_string($cuerpo) ? json_decode($cuerpo, true) : null;
    $motivo = is_array($respuesta) && isset($respuesta['code']) && is_string($respuesta['code']) ? $respuesta['code'] : '';
    // «message» de Brevo (no lleva la clave): sin saltos de línea y recortado a 300 caracteres. Por si acaso cita algún
    // dato del visitante, se tapan su correo y su nombre antes de guardarlo.
    $texto = is_array($respuesta) && isset($respuesta['message']) && is_string($respuesta['message']) ? $respuesta['message'] : '';
    foreach ([$carga['replyTo']['email'] ?? '', $carga['replyTo']['name'] ?? ''] as $dato) {
        if (is_string($dato) && $dato !== '') {
            $texto = str_ireplace($dato, '[dato]', $texto);
        }
    }
    $texto = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $texto)), 0, 300);
    registrar('Brevo no ha aceptado el aviso: HTTP ' . $codigo . ($motivo !== '' ? ' ' . substr($motivo, 0, 60) : '') . ($texto !== '' ? ' — ' . $texto : '') . ($fallo !== '' ? ' (cURL: ' . substr($fallo, 0, 100) . ')' : ''));
    return false;
}

// ---------------------------------------------------------------------------------------------------------
// Respuestas
// ---------------------------------------------------------------------------------------------------------

function redirigir_gracias(string $tipo): void
{
    sin_cache();
    header('Location: ' . FORMULARIOS[$tipo]['gracias'], true, 303);
    exit;
}

// Vuelve a mostrar el formulario: la propia página construida (la de /contacto/ o la de /servicios/diagnostico/)
// con el mensaje arriba, los campos con error marcados y los datos escritos conservados. Solo se toca el
// <form>; el resto del HTML se sirve tal cual (así sigue valiendo el hash de la CSP).
function mostrar_formulario(string $tipo, array $valores, array $errores, string $aviso, int $estado, string $token): void
{
    $datos = FORMULARIOS[$tipo];
    $raiz = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $html = $raiz !== '' ? @file_get_contents($raiz . $datos['pagina']) : false;
    $encontrado = is_string($html)
        && preg_match('~<form\b[^>]*\bid="' . preg_quote($datos['id'], '~') . '"[^>]*>.*?</form>~s', $html, $m, PREG_OFFSET_CAPTURE);

    http_response_code($estado);
    sin_cache();
    header('Content-Type: text/html; charset=utf-8');

    if (!$encontrado) {
        // Sin la página construida (no debería pasar): mensaje mínimo con la vuelta al formulario
        $volver = htmlspecialchars($datos['ruta'], ENT_QUOTES, 'UTF-8');
        $texto = htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Formulario · Entretecas</title>'
            . '<main><p>' . $texto . '</p><p><a href="' . $volver . '">Volver al formulario</a></p></main></html>';
        exit;
    }

    $fragmento = $m[0][0];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $fragmento, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xp = new DOMXPath($dom);
    $form = $xp->query('//form')->item(0);

    $control = function (string $nombre) use ($xp): ?DOMElement {
        $nodo = $xp->query('//*[@name="' . $nombre . '"]')->item(0);
        return $nodo instanceof DOMElement ? $nodo : null;
    };

    // Datos conservados
    foreach (['nombre', 'correo', 'telefono', 'entidad'] as $campo) {
        $nodo = $control($campo);
        if ($nodo) {
            $nodo->setAttribute('value', (string) ($valores[$campo] ?? ''));
        }
    }
    $nodo = $control('mensaje');
    if ($nodo) {
        $nodo->textContent = (string) ($valores['mensaje'] ?? '');
    }
    foreach (['sistema', 'registros', 'bibliotecas'] as $campo) {
        $nodo = $control($campo);
        if ($nodo) {
            foreach ($xp->query('.//option', $nodo) as $opcion) {
                $opcion->removeAttribute('selected');
                if ($opcion->getAttribute('value') !== '' && $opcion->getAttribute('value') === ($valores[$campo] ?? '')) {
                    $opcion->setAttribute('selected', 'selected');
                }
            }
        }
    }
    foreach ($xp->query('//input[@name="interes[]"]') as $casilla) {
        $casilla->removeAttribute('checked');
        if (in_array($casilla->getAttribute('value'), $valores['interes'] ?? [], true)) {
            $casilla->setAttribute('checked', 'checked');
        }
    }
    $nodo = $control('privacidad');
    if ($nodo) {
        $nodo->removeAttribute('checked');
        if (($valores['privacidad'] ?? '') === 'acepto') {
            $nodo->setAttribute('checked', 'checked');
        }
    }
    $nodo = $control('t');
    if ($nodo) {
        $nodo->setAttribute('value', $token);
    }

    // Campos con error: marcados, con su mensaje, y el foco en el primero
    $primero = true;
    foreach ($errores as $campo => $mensaje) {
        $nodo = $control($campo === 'interes' ? 'interes[]' : $campo);
        if (!$nodo) {
            continue;
        }
        $nodo->setAttribute('aria-invalid', 'true');
        $salida = $xp->query('//*[@id="' . $nodo->getAttribute('aria-describedby') . '"]')->item(0);
        if ($nodo->getAttribute('aria-describedby') !== '' && $salida) {
            $salida->textContent = $mensaje;
        }
        if ($primero && $campo !== 'interes') {
            $nodo->setAttribute('autofocus', 'autofocus');
            $primero = false;
        }
    }

    // Aviso general, arriba del formulario (copia del párrafo de estado, que lleva los estilos de la web)
    $estadoOriginal = $xp->query('//p[contains(concat(" ", normalize-space(@class), " "), " formulario__estado ")]')->item(0);
    if ($estadoOriginal instanceof DOMElement && $form instanceof DOMElement) {
        $aviso_nodo = $estadoOriginal->cloneNode(false);
        $aviso_nodo->setAttribute('role', 'alert');
        $aviso_nodo->removeAttribute('aria-live');
        $aviso_nodo->textContent = $aviso . ($errores && isset($errores['interes']) ? ' ' . $errores['interes'] : '');
        $form->insertBefore($aviso_nodo, $form->firstChild);
        if ($primero) { // sin campo concreto a enfocar (aviso de confirmación, límite…): el botón de envío
            $boton = $xp->query('//button[@type="submit"]')->item(0);
            if ($boton instanceof DOMElement) {
                $boton->setAttribute('autofocus', 'autofocus');
            }
        }
    }

    $nuevo = $dom->saveHTML($form);
    $pagina = substr($html, 0, $m[0][1]) . $nuevo . substr($html, $m[0][1] + strlen($fragmento));
    // Esta página es una respuesta a un envío: que no se indexe ni se enlace como la original
    $pagina = preg_replace('~</head>~i', '<meta name="robots" content="noindex"></head>', $pagina, 1);
    echo $pagina;
    exit;
}

// ---------------------------------------------------------------------------------------------------------
// Flujo
// ---------------------------------------------------------------------------------------------------------

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

// GET: solo entrega la marca de tiempo firmada que el JavaScript de la página pone en el formulario
if ($metodo === 'GET' && ($_GET['token'] ?? '') === '1' && count($_GET) === 1) {
    $config = cargar_config();
    if ($config === null) {
        corta(503, 'El formulario no está disponible ahora mismo.');
    }
    sin_cache();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['t' => emitir_token($config['secreto'])]);
    exit;
}

if ($metodo !== 'POST') {
    corta(405, 'Método no permitido.', ['Allow: POST']);
}

// Solo desde el propio dominio
$host = host_de_origen();
if ($host === null) {
    corta(403);
}

// Forma de la petición
$tipoContenido = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($tipoContenido, 'application/x-www-form-urlencoded') !== 0 && strpos($tipoContenido, 'multipart/form-data') !== 0) {
    corta(415);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > BYTES_MAXIMOS || count($_POST) > 40) {
    corta(413);
}

$tipo = escalar($_POST['formulario'] ?? '');
if ($tipo === null || !isset(FORMULARIOS[$tipo])) {
    corta(400);
}

// Campo trampa: un bot lo rellena. Se descarta y se le responde como si hubiera ido bien (no se le da pistas).
if (($_POST['web'] ?? '') !== '') {
    redirigir_gracias($tipo);
}

$listas = cargar_listas();
list($valores, $errores) = validar($tipo, $_POST, $listas);

$config = cargar_config();
if ($config === null) {
    // Sin clave o mal configurado: el visitante solo ve el aviso genérico (el motivo está en el log)
    mostrar_formulario($tipo, $valores, [], 'No he podido enviar el mensaje. Inténtalo más tarde o escribe a ' . DESTINATARIO . '.', 503, '');
}

$token = (string) (escalar($_POST['t'] ?? '') ?? '');
$estadoToken = estado_token($token, $config['secreto']);
$tokenParaVolver = $estadoToken === 'falta' ? emitir_token($config['secreto']) : $token;

if ($errores) {
    mostrar_formulario($tipo, $valores, $errores, 'No se ha enviado el mensaje. Revisa los campos marcados.', 422, $tokenParaVolver);
}
if ($estadoToken === 'falta') {
    // Sin JavaScript (o con la página abierta mucho tiempo): se pide confirmar con un segundo clic, ya con marca nueva
    mostrar_formulario($tipo, $valores, [], 'Por seguridad, confirma el envío: revisa tus datos y pulsa «Enviar» otra vez.', 200, $tokenParaVolver);
}
if ($estadoToken === 'rapido') {
    mostrar_formulario($tipo, $valores, [], 'Has enviado el formulario demasiado rápido. Espera un momento y pulsa «Enviar» otra vez.', 429, $token);
}

$permitido = permitir_envio($config['secreto']);
if ($permitido === null) {
    mostrar_formulario($tipo, $valores, [], 'No he podido enviar el mensaje. Inténtalo más tarde o escribe a ' . DESTINATARIO . '.', 503, $token);
}
if ($permitido === false) {
    mostrar_formulario($tipo, $valores, [], 'Has enviado varios mensajes en poco tiempo. Inténtalo de nuevo dentro de una hora o escribe a ' . DESTINATARIO . '.', 429, $token);
}

// Página de origen: la que declara el formulario, solo si tiene forma de ruta de la web
$pagina = escalar($_POST['pagina'] ?? '') ?? '';
if (!preg_match('~^/[A-Za-z0-9/_-]{0,100}$~', $pagina)) {
    $pagina = FORMULARIOS[$tipo]['ruta'];
}

list($asunto, $texto) = construir_aviso($tipo, $valores, $host, $pagina);
if (!enviar_aviso($config, $asunto, $texto, $valores['correo'], $valores['nombre'])) {
    mostrar_formulario($tipo, $valores, [], 'No he podido enviar el mensaje. Inténtalo de nuevo en unos minutos o escribe a ' . DESTINATARIO . '.', 503, $token);
}

redirigir_gracias($tipo);
