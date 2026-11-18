<?php

// Datos de ejemplo completos y ficticios para probar y enseñar la aplicación.
//
// Lo carga todo a través de la API, como lo harían las personas desde la aplicación
// (administrador, monitores y familias). Así los datos pasan las mismas validaciones y
// permisos, los archivos los escribe el servidor web con su usuario y van a sus carpetas
// de storage/. Crea:
//   - monitores (uno también de tesorería) y familias (tutores) con sus educandos,
//     repartidos por todas las secciones; dos educandos dados de baja (uno ya vencido);
//   - eventos de la ronda anterior y de la actual: sábados, acampadas, excursiones,
//     festival, reunión de familias, con sus secciones convocadas y circular;
//   - asistencias: respuestas de las familias (algunas pendientes) y circulares
//     entregadas; en los sábados pasados, la asistencia que apuntan los monitores;
//   - fichas de inscripción entregadas por las familias y revisadas por los monitores;
//   - una plantilla nueva en el catálogo de documentos base;
//   - fotos en la galería de los eventos pasados;
//   - facturas con su tique, en los tres estados;
//   - el inventario y las listas de material de inventario-ejemplo.php.
// Los nombres, DNI, teléfonos y correos son inventados (dominio .test).
//
// Uso: php server/pruebas/datos-ejemplo.php <url-base> <correo-admin> <contraseña> --confirmar
// Ejemplo: php server/pruebas/datos-ejemplo.php http://localhost/Ingeriar/public usuario1@ingeriar.test demo-1 --confirmar
// Si ya se cargaron (existe el primer monitor de ejemplo), no hace nada.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/dibujos.php';

$argumentos = array_values(array_filter(array_slice($argv, 1), fn($argumento) => !str_starts_with($argumento, '--')));
if (count($argumentos) !== 3 || !in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, "Uso: php server/pruebas/datos-ejemplo.php <url-base> <correo-admin> <contraseña> --confirmar\n");
    exit(2);
}
[$base, $correoAdmin, $contrasenaAdmin] = $argumentos;
$base = rtrim($base, '/');

// Contraseña de todas las cuentas de ejemplo (cumple la política: 8+ caracteres y un número).
const CONTRASENA_EJEMPLO = 'Seeonee-2026';
const DOMINIO_MONITORES = '@monitores.ingeriar.test';
const DOMINIO_FAMILIAS = '@familias.ingeriar.test';

mt_srand(2026);
$carpetaTemporal = sys_get_temp_dir() . '/ingeriar-ejemplo-' . bin2hex(random_bytes(4));
mkdir($carpetaTemporal);

// ---------- Cliente HTTP con su propia sesión ----------

class Cliente
{
    private string $cookies;

    public function __construct(private string $base)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'ingeriar-sesion');
    }

    public function __destruct()
    {
        if (is_file($this->cookies)) {
            unlink($this->cookies);
        }
    }

    public function peticion(string $ruta, array|string|null $cuerpo = null, bool $json = false, bool $obligatoria = true): array
    {
        $curl = curl_init($this->base . $ruta);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
        ]);
        if ($cuerpo !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($cuerpo) : $cuerpo);
            if ($json) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }
        $texto = (string) curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $datos = json_decode($texto, true);
        if ($obligatoria && ($codigo !== 200 || (is_array($datos) && isset($datos['error'])))) {
            throw new RuntimeException("{$ruta} → {$codigo}: " . substr($texto, 0, 300));
        }
        return is_array($datos) ? $datos : ['codigo' => $codigo, 'texto' => $texto];
    }

    public function get(string $ruta): array
    {
        return $this->peticion($ruta);
    }

    public function json(string $ruta, array $datos, bool $obligatoria = true): array
    {
        return $this->peticion($ruta, $datos, true, $obligatoria);
    }

    public function formulario(string $ruta, array $campos, bool $obligatoria = true): array
    {
        return $this->peticion($ruta, $campos, false, $obligatoria);
    }

    public function entrar(string $correo, string $contrasena): self
    {
        $this->json('/api/login.php', ['email' => $correo, 'contrasena' => $contrasena]);
        return $this;
    }
}

function archivo(string $nombre, string $contenido, string $tipo): CURLFile
{
    global $carpetaTemporal;
    $ruta = $carpetaTemporal . '/' . bin2hex(random_bytes(6)) . '-' . $nombre;
    file_put_contents($ruta, $contenido);
    return new CURLFile($ruta, $tipo, $nombre);
}

function sinAcentos(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    return trim((string) preg_replace('/[^a-z0-9]+/', '.', strtolower($texto)), '.');
}

// DNI ficticio con su letra de control correcta.
function dni(int $numero): string
{
    return sprintf('%08d', $numero) . 'TRWAGMYFPDXBNJZSQVHLCKE'[$numero % 23];
}

function fechaHora(DateTimeImmutable $dia, string $hora): string
{
    return $dia->format('Y-m-d') . ' ' . $hora . ':00';
}

// Sección de un educando en una fecha: edad al empezar esa ronda (1 de septiembre).
function seccionEn(string $nacimiento, DateTimeImmutable $fecha, array $secciones): ?string
{
    $inicio = (int) $fecha->format('n') >= 9 ? (int) $fecha->format('Y') : (int) $fecha->format('Y') - 1;
    $edad = (new DateTimeImmutable($nacimiento))->diff(new DateTimeImmutable($inicio . '-09-01'))->y;
    foreach ($secciones as $seccion) {
        if ($edad >= (int) $seccion['edad_minima'] && $edad <= (int) $seccion['edad_maxima']) {
            return $seccion['codigo'];
        }
    }
    return null;
}

$admin = (new Cliente($base))->entrar($correoAdmin, $contrasenaAdmin);
$tabla = fn(string $nombre) => $admin->get('/api/superapi.php?ruta=tabla&tabla=' . urlencode($nombre))['registros'] ?? [];

$usuariosExistentes = array_column($tabla('usuarios'), 'email');
if (in_array('marta.rodriguez' . DOMINIO_MONITORES, $usuariosExistentes, true)) {
    echo "Los datos de ejemplo ya están cargados (existe marta.rodriguez" . DOMINIO_MONITORES . ").\n";
    exit(0);
}
$catalogos = $admin->get('/api/superapi.php?ruta=catalogos');
$secciones = array_values(array_filter($catalogos['secciones'] ?? [], fn($seccion) => (int) $seccion['activo'] === 1
    && $seccion['edad_minima'] !== null && $seccion['edad_maxima'] !== null));
if (!$secciones) {
    fwrite(STDERR, "No se han podido leer las secciones del catálogo.\n");
    exit(1);
}

$hoy = new DateTimeImmutable('today');
$telefono = 611000000 + mt_rand(0, 9999) * 10;

// ---------- Monitores ----------

echo "Monitores\n";
$monitores = [
    ['Marta', 'Rodríguez Sanz', ['scouter', 'tesoreria']],
    ['Javier', 'López Martín', ['scouter']],
    ['Lucía', 'Fernández Gil', ['scouter']],
    ['Pablo', 'Navarro Ruiz', ['scouter']],
    ['Carmen', 'Ortega Díaz', ['scouter']],
    ['Diego', 'Romero Castillo', ['scouter']],
    ['Irene', 'Molina Vega', ['scouter']],
    ['Sergio', 'Herrero Prieto', ['scouter']],
];
$clientesMonitores = [];
foreach ($monitores as [$nombre, $apellidos, $roles]) {
    $correo = sinAcentos($nombre . ' ' . explode(' ', $apellidos)[0]) . DOMINIO_MONITORES;
    $admin->formulario('/api/superapi.php?ruta=crear', ['tabla' => 'usuarios', 'datos' => json_encode([
        'nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $correo,
        'telefono' => (string) ($telefono += 7), 'contraseña' => CONTRASENA_EJEMPLO, 'roles' => $roles,
    ])]);
    $clientesMonitores[] = (new Cliente($base))->entrar($correo, CONTRASENA_EJEMPLO);
    echo "  {$nombre} {$apellidos} ({$correo})" . (in_array('tesoreria', $roles, true) ? ' · tesorería' : '') . "\n";
}
$tesoreria = $clientesMonitores[0];
$idsUsuarios = array_column($tabla('usuarios'), 'id', 'email');
$idMonitor = fn(int $indice) => (int) $idsUsuarios[sinAcentos($monitores[$indice][0] . ' ' . explode(' ', $monitores[$indice][1])[0]) . DOMINIO_MONITORES];

// ---------- Familias y educandos ----------

echo "Familias y educandos\n";
// [nombre del tutor, apellidos de la familia, [[nombre, edad al empezar la ronda actual], ...], baja]
$familias = [
    ['Ana', 'García Pérez', [['Hugo', 5], ['Lola', 8]]],
    ['Raúl', 'Martínez Soler', [['Martina', 6]]],
    ['Elena', 'Sánchez Ibáñez', [['Leo', 7], ['Vera', 10]]],
    ['Tomás', 'Gómez Arias', [['Daniel', 8]]],
    ['Beatriz', 'Jiménez Lara', [['Sara', 9], ['Marcos', 12]]],
    ['Óscar', 'Ruiz Carmona', [['Nora', 10]]],
    ['Paula', 'Hernández Rey', [['Iker', 11], ['Alma', 14]]],
    ['Luis', 'Díaz Montero', [['Carla', 11]]],
    ['Silvia', 'Moreno Pastor', [['Adrián', 12]]],
    ['Andrés', 'Álvarez Nieto', [['Julia', 13], ['Mateo', 6]]],
    ['Rocío', 'Muñoz Esteban', [['Pablo', 13]]],
    ['Fernando', 'Romero Bravo', [['Claudia', 14]]],
    ['Isabel', 'Alonso Cano', [['Álvaro', 15], ['Emma', 9]]],
    ['Jorge', 'Gutiérrez Vidal', [['Inés', 15]]],
    ['Natalia', 'Torres Crespo', [['Gonzalo', 16]]],
    ['Ricardo', 'Domínguez Peña', [['Lucía', 17], ['Bruno', 12]]],
    ['Marina', 'Vázquez Ramos', [['Diego', 18]]],
    ['Alberto', 'Serrano Fuentes', [['Valeria', 7]], '-5 months'],
    ['Cristina', 'Blanco Méndez', [['Samuel', 14]], '-6 years'],
];
$inicioRonda = new DateTimeImmutable(((int) $hoy->format('n') >= 9 ? $hoy->format('Y') : (int) $hoy->format('Y') - 1) . '-09-01');
$numeroDni = 71000000 + mt_rand(0, 99999);
$educandosCreados = [];
foreach ($familias as $indice => $familia) {
    [$tutor, $apellidos, $hijos] = $familia;
    $baja = isset($familia[3]) ? $hoy->modify($familia[3])->format('Y-m-d') : null;
    foreach ($hijos as [$nombre, $edad]) {
        // Nace en un día al azar del año que le da esa edad al empezar la ronda.
        $nacimiento = $inicioRonda->modify('-' . ($edad + 1) . ' years')->modify('+' . mt_rand(1, 364) . ' days');
        if ($baja !== null && str_starts_with($familia[3], '-6')) {
            $nacimiento = $nacimiento->modify('-6 years');
        }
        $numeroDni += mt_rand(11, 997);
        $admin->formulario('/api/superapi.php?ruta=crear', ['tabla' => 'educandos', 'datos' => json_encode([
            'nombre' => $nombre, 'apellidos' => $apellidos, 'DNI' => dni($numeroDni),
            'naz' => $nacimiento->format('Y-m-d'), 'fecha_baja' => $baja,
        ])]);
        $educandosCreados[] = ['familia' => $indice, 'dni' => dni($numeroDni), 'nombre' => $nombre . ' ' . $apellidos];
    }
}
$idsPorDni = array_column($tabla('educandos'), null, 'DNI');
$educandos = [];
foreach ($educandosCreados as $creado) {
    $registro = $idsPorDni[$creado['dni']];
    $educandos[] = $creado + ['id' => (int) $registro['id'], 'naz' => $registro['naz'], 'baja' => $registro['fecha_baja'] ?? null];
}

$clientesFamilias = [];
foreach ($familias as $indice => [$tutor, $apellidos]) {
    $correo = sinAcentos($tutor . ' ' . explode(' ', $apellidos)[0]) . DOMINIO_FAMILIAS;
    $hijos = array_values(array_filter($educandos, fn($educando) => $educando['familia'] === $indice));
    $admin->formulario('/api/superapi.php?ruta=crear', ['tabla' => 'usuarios', 'datos' => json_encode([
        'nombre' => $tutor, 'apellidos' => $apellidos, 'email' => $correo,
        'telefono' => (string) ($telefono += 7), 'contraseña' => CONTRASENA_EJEMPLO, 'roles' => ['tutor'],
        'educandos_relacionados' => array_column($hijos, 'id'),
    ])]);
    $clientesFamilias[$indice] = (new Cliente($base))->entrar($correo, CONTRASENA_EJEMPLO);
    echo "  Familia {$apellidos}: " . implode(', ', array_map(fn($hijo) => explode(' ', $hijo['nombre'])[0]
        . ($hijo['baja'] ? ' (baja ' . $hijo['baja'] . ')' : ''), $hijos)) . " · tutor {$tutor} ({$correo})\n";
}
$activos = array_values(array_filter($educandos, fn($educando) => $educando['baja'] === null));

// ---------- Eventos ----------

echo "Eventos\n";
$sabadoSiguiente = fn(DateTimeImmutable $dia) => $dia->format('N') === '6' ? $dia : $dia->modify('next saturday');
$todas = array_column($secciones, 'codigo');
$eventos = [];
$nuevoEvento = function (string $nombre, DateTimeImmutable $inicio, DateTimeImmutable $fin, string $tipo, array $convoca,
    bool $circular, string $lugar, string $municipio, int $responsable, bool $conFotos = false) use (&$eventos) {
    $eventos[] = compact('nombre', 'inicio', 'fin', 'tipo', 'convoca', 'circular', 'lugar', 'municipio', 'responsable', 'conFotos');
};
// Sábados de la ronda actual: desde el inicio hasta ocho semanas después de hoy.
for ($dia = $sabadoSiguiente($inicioRonda->modify('+14 days')); $dia <= $hoy->modify('+8 weeks'); $dia = $dia->modify('+1 week')) {
    $nuevoEvento('Actividad de sábado ' . $dia->format('d/m'), $dia->setTime(10, 30), $dia->setTime(13, 30), 'sabado',
        [], false, 'Local del grupo', 'Valencia', $idMonitor(mt_rand(1, 7)), $dia < $hoy && mt_rand(0, 2) === 0);
}
$dia = fn(string $desplazamiento) => $hoy->modify($desplazamiento);
// Ronda actual: pasados (con fotos y circulares) y próximos.
$nuevoEvento('Reunión de familias de inicio de ronda', $dia('-55 days')->setTime(18, 0), $dia('-55 days')->setTime(19, 30),
    'reunión de familias', [], false, 'Local del grupo', 'Valencia', $idMonitor(0));
$nuevoEvento('Excursión de Manada a la Albufera', $dia('-25 days')->setTime(9, 0), $dia('-25 days')->setTime(18, 0),
    'excursion', ['manada'], true, 'Parque natural de la Albufera', 'Valencia', $idMonitor(2), true);
$nuevoEvento('Ruta de Colonia por el río', $dia('-11 days')->setTime(10, 0), $dia('-11 days')->setTime(14, 0),
    'excursion', ['colonia'], true, 'Jardín del Turia', 'Valencia', $idMonitor(4), true);
$nuevoEvento('Acampada de otoño de Tropa y Posta', $sabadoSiguiente($dia('+2 days'))->modify('-1 day')->setTime(18, 0),
    $sabadoSiguiente($dia('+2 days'))->modify('+1 day')->setTime(17, 0), 'acampada', ['tropa', 'posta'], true,
    'Albergue de la Calderona', 'Serra', $idMonitor(1));
$nuevoEvento('Travesía de Rutas por la sierra', $dia('+17 days')->setTime(8, 0), $dia('+19 days')->setTime(19, 0),
    'acampada', ['rutas'], true, 'Sierra de Espadán', 'Eslida', $idMonitor(3));
$nuevoEvento('Excursión de Colonia a la granja escuela', $dia('+24 days')->setTime(9, 30), $dia('+24 days')->setTime(17, 0),
    'excursion', ['colonia'], true, 'Granja escuela El Romeral', 'Bétera', $idMonitor(4));
$nuevoEvento('Festival de Navidad', $dia('+31 days')->setTime(17, 0), $dia('+31 days')->setTime(20, 0),
    'festival', [], false, 'Salón de actos del barrio', 'Valencia', $idMonitor(5));
$nuevoEvento('Acampada de invierno de grupo', $dia('+60 days')->setTime(18, 0), $dia('+62 days')->setTime(17, 0),
    'acampada', [], true, 'Campamento Los Pinos', 'Alcublas', $idMonitor(0));
// Ronda anterior (para la galería y el historial).
$anterior = fn(string $fecha) => new DateTimeImmutable(((int) $inicioRonda->format('Y') - 1) . '-' . $fecha);
$siguienteAnio = fn(string $fecha) => new DateTimeImmutable($inicioRonda->format('Y') . '-' . $fecha);
$nuevoEvento('Festival de Navidad', $anterior('12-20 17:00'), $anterior('12-20 20:00'), 'festival', [], false,
    'Salón de actos del barrio', 'Valencia', $idMonitor(5), true);
$nuevoEvento('Acampada de primavera', $siguienteAnio('04-10 18:00'), $siguienteAnio('04-12 17:00'), 'acampada', [], true,
    'Albergue de la Calderona', 'Serra', $idMonitor(1), true);
$nuevoEvento('Campamento de verano', $siguienteAnio('07-15 10:00'), $siguienteAnio('07-29 18:00'), 'acampada', [], true,
    'Campamento Los Pinos', 'Alcublas', $idMonitor(0), true);

// Los eventos pasados de esta ronda con circular se crean primero con una fecha futura (las
// familias solo pueden responder y entregar la circular antes de que empiece) y después se
// les pone su fecha. En los de la ronda anterior la asistencia la apunta un monitor.
$fechaProvisional = $dia('+90 days');
foreach ($eventos as $indice => &$evento) {
    $pasadoConCircular = $evento['circular'] && $evento['fin'] < $hoy && $evento['inicio'] >= $inicioRonda;
    $duracion = $evento['inicio']->diff($evento['fin']);
    $inicio = $pasadoConCircular ? $fechaProvisional->modify('+' . $indice . ' days')->setTime(10, 0) : $evento['inicio'];
    $evento['provisional'] = $pasadoConCircular ? [$inicio, $inicio->add($duracion)] : null;
    $evento['datos'] = [
        'nombre' => $evento['nombre'], 'tipo' => $evento['tipo'], 'circular' => $evento['circular'] ? '1' : '0',
        'lugar' => $evento['lugar'], 'municipio' => $evento['municipio'], 'comunidad' => 'Comunitat Valenciana',
        'id_responsable' => $evento['responsable'], 'convocatorias' => $evento['convoca'],
    ];
    $admin->formulario('/api/superapi.php?ruta=crear', ['tabla' => 'eventos', 'datos' => json_encode($evento['datos'] + [
        'fecha_inicio' => $inicio->format('Y-m-d H:i:s'),
        'fecha_fin' => ($pasadoConCircular ? $inicio->add($duracion) : $evento['fin'])->format('Y-m-d H:i:s'),
    ])]);
}
unset($evento);
$registrosEventos = $tabla('eventos');
foreach ($eventos as &$evento) {
    $inicio = ($evento['provisional'][0] ?? $evento['inicio'])->format('Y-m-d H:i:s');
    foreach ($registrosEventos as $registro) {
        if ($registro['nombre'] === $evento['nombre'] && $registro['fecha_inicio'] === $inicio) {
            $evento['id'] = (int) $registro['id'];
        }
    }
}
unset($evento);
echo '  ' . count($eventos) . " eventos\n";

// ---------- Asistencias y circulares ----------

echo "Asistencias y circulares\n";
$respuestas = 0;
$circulares = 0;
$convocados = function (array $evento) use ($activos, $secciones): array {
    return array_values(array_filter($activos, function ($educando) use ($evento, $secciones) {
        $seccion = seccionEn($educando['naz'], $evento['inicio'], $secciones);
        return $seccion !== null && (!$evento['convoca'] || in_array($seccion, $evento['convoca'], true));
    }));
};
foreach ($eventos as $evento) {
    $futuroOProvisional = $evento['fin'] >= $hoy || $evento['provisional'] !== null;
    foreach ($convocados($evento) as $educando) {
        $azar = mt_rand(1, 100);
        if ($futuroOProvisional) {
            // Responde la familia; en los próximos, algunas aún no han contestado.
            $pasado = $evento['provisional'] !== null;
            if (!$pasado && $azar > 70) {
                continue;
            }
            $estado = $azar <= ($pasado ? 88 : 58) ? 'si' : 'no';
            $familia = $clientesFamilias[$educando['familia']];
            $familia->json('/api/superapi.php?ruta=asistencia_tutor', [
                'evento' => $evento['id'], 'educando' => $educando['id'], 'estado' => $estado,
            ]);
            $respuestas++;
            // Circular: casi todas en los pasados; en los próximos, algunas faltan.
            if ($estado === 'si' && $evento['circular'] && ($pasado || mt_rand(1, 100) <= 65)) {
                $familia->formulario("/api/archivo.php?tipo=entrega&evento={$evento['id']}&educando={$educando['id']}", [
                    'archivo' => archivo('circular.pdf', crearPdf('Autorización de participación', [
                        'Actividad: ' . $evento['nombre'],
                        'Educando: ' . $educando['nombre'],
                        'Autorizo su participación en la actividad indicada.',
                        'Documento de ejemplo con datos ficticios.',
                    ]), 'application/pdf'),
                ]);
                $circulares++;
            }
        } else {
            // Pasados sin circular o de la ronda anterior: la asistencia la apunta un monitor.
            $admin->formulario('/api/superapi.php?ruta=crear', ['tabla' => 'educandos_asistencias', 'datos' => json_encode([
                'id_educando' => $educando['id'], 'id_evento' => $evento['id'], 'estado' => $azar <= 85 ? 'si' : 'no',
            ])], false);
            $respuestas++;
        }
    }
}
// Los eventos pasados con circular vuelven a su fecha real.
foreach ($eventos as $evento) {
    if ($evento['provisional'] !== null) {
        $admin->formulario('/api/superapi.php?ruta=editar', ['tabla' => 'eventos', 'id' => (string) $evento['id'],
            'datos' => json_encode($evento['datos'] + [
                'fecha_inicio' => $evento['inicio']->format('Y-m-d H:i:s'),
                'fecha_fin' => $evento['fin']->format('Y-m-d H:i:s'),
            ])]);
    }
}
echo "  {$respuestas} respuestas, {$circulares} circulares entregadas\n";

// ---------- Plantilla nueva y fichas de inscripción ----------

echo "Documentación\n";
$admin->formulario('/api/documentos.php?accion=guardar_plantilla', [
    'codigo' => 'autorizacion_campamento', 'nombre' => 'Autorización para el campamento de verano',
    'obligatorio' => '0', 'activo' => '1', 'orden' => '120',
    'plantilla' => archivo('plantilla-campamento.pdf', crearPdf('Autorización para el campamento de verano', [
        'Nombre del educando: ______________________________',
        'Sección: ____________   Fechas: ______________________',
        'Persona de contacto durante el campamento: __________',
        'Teléfono: ______________',
        'Plantilla de ejemplo del grupo.',
    ]), 'application/pdf'),
]);
echo "  Plantilla «Autorización para el campamento de verano»\n";

$revisores = array_slice($clientesMonitores, 1, 4);
$entregadas = 0;
$revisadas = 0;
foreach ($activos as $educando) {
    $familia = $clientesFamilias[$educando['familia']];
    $documentos = $familia->get('/api/documentos.php?accion=educando&id=' . $educando['id'])['documentos'] ?? [];
    $mayor = (new DateTimeImmutable($educando['naz']))->diff($hoy)->y >= 18;
    foreach ($documentos as $documento) {
        if (!$documento['obligatorio'] && !($mayor && str_ends_with($documento['codigo'], '_mayor'))) {
            continue;
        }
        if ($mayor && str_ends_with($documento['codigo'], '_menor')) {
            continue;
        }
        if (mt_rand(1, 100) > 82) {
            continue;
        }
        $familia->formulario('/api/documentos.php?accion=subir', [
            'id_educando' => (string) $educando['id'], 'codigo' => $documento['codigo'],
            'archivo' => archivo($documento['codigo'] . '.pdf', crearPdf($documento['nombre'], [
                'Educando: ' . $educando['nombre'],
                'Fecha de nacimiento: ' . $educando['naz'],
                'Documento rellenado de ejemplo con datos ficticios.',
            ]), 'application/pdf'),
        ]);
        $entregadas++;
    }
    // Un monitor revisa casi todo: la mayoría bien y alguna con correcciones.
    $revisor = $revisores[array_rand($revisores)];
    foreach ($revisor->get('/api/documentos.php?accion=educando&id=' . $educando['id'])['documentos'] ?? [] as $documento) {
        $azar = mt_rand(1, 100);
        if ($documento['entrega'] === null || $azar > 85) {
            continue;
        }
        $revisor->formulario('/api/documentos.php?accion=revisar', $azar <= 75
            ? ['id' => (string) $documento['entrega']['id'], 'estado' => 'revisado']
            : ['id' => (string) $documento['entrega']['id'], 'estado' => 'corregir',
                'observaciones' => 'Falta la firma de la madre, padre o tutor legal.']);
        $revisadas++;
    }
}
echo "  {$entregadas} fichas entregadas, {$revisadas} revisadas\n";

// ---------- Galería ----------

echo "Galería\n";
$fotos = 0;
foreach ($eventos as $evento) {
    if (!$evento['conFotos']) {
        continue;
    }
    $cantidad = mt_rand(4, 9);
    $campos = ['evento' => (string) $evento['id']];
    for ($i = 0; $i < $cantidad; $i++) {
        $campos["fotos[{$i}]"] = archivo('foto-' . ($i + 1) . '.png', dibujarPaisaje($evento['id'] * 100 + $i), 'image/png');
    }
    $clientesMonitores[mt_rand(1, 7)]->formulario('/api/galeria.php?accion=subir', $campos);
    $fotos += $cantidad;
    echo "  {$cantidad} fotos en «{$evento['nombre']}» ({$evento['inicio']->format('d/m/Y')})\n";
}

// ---------- Facturas ----------

echo "Facturas\n";
$compras = [
    ['Comida para la acampada de primavera', 'comida', 286.40, '04-08'],
    ['Bombonas de gas para cocinas de campamento', 'gas', 74.90, '04-02'],
    ['Autobús al campamento de verano', 'otros', 1180.00, '07-10'],
    ['Compra de comida del campamento (semana 1)', 'comida', 642.35, '07-14'],
    ['Compra de comida del campamento (semana 2)', 'comida', 598.10, '07-21'],
    ['Gasolina de la furgoneta: campamento', 'gasolina', 96.20, '07-16'],
    ['Agua embotellada para el campamento', 'agua', 58.80, '07-15'],
    ['Material de manualidades de Colonia', 'material', 42.15, '10-03'],
    ['Cuerdas y mosquetones para Tropa', 'material', 133.70, '10-07'],
    ['Factura de la luz del local', 'luz', 61.43, '10-12'],
    ['Envío de pañoletas nuevas', 'envíos', 12.50, '10-15'],
    ['Merienda de la excursión de Manada', 'comida', 48.60, '10-20'],
    ['Gasolina de la furgoneta: excursión', 'gasolina', 54.00, '10-24'],
    ['Pilas y linternas', 'material', 39.95, '11-02'],
    ['Comida para la acampada de otoño', 'comida', 212.30, '11-10'],
    ['Bombonas de gas para la acampada de otoño', 'gas', 49.90, '11-12'],
    ['Factura de la luz del local', 'luz', 58.12, '11-12'],
    ['Agua para la acampada de otoño', 'agua', 22.40, '11-14'],
    ['Material de botiquín', 'material', 67.25, '11-15'],
    ['Decoración del Festival de Navidad', 'material', 35.80, '11-16'],
];
$facturas = 0;
foreach ($compras as $indice => [$concepto, $categoria, $precio, $mesDia]) {
    // Las de abril y julio son de la ronda anterior; el resto, de la actual.
    $fecha = ((int) substr($mesDia, 0, 2) < 9 ? $siguienteAnio($mesDia . ' 11:00') : $inicioRonda->modify('+0 days')
        ->setDate((int) $inicioRonda->format('Y'), (int) substr($mesDia, 0, 2), (int) substr($mesDia, 3, 2))->setTime(11, 0));
    if ($fecha > $hoy) {
        continue;
    }
    $monitor = $clientesMonitores[1 + $indice % 7];
    $monitor->formulario('/api/facturas.php?accion=crear', [
        'numero_fact' => (string) (2000 + $indice * 37), 'CIF_prov' => 'B' . sprintf('%08d', 46000000 + $indice * 1231),
        'fecha_emision' => $fecha->format('Y-m-d\TH:i'), 'fecha_compra' => $fecha->format('Y-m-d\TH:i'),
        'concepto' => $concepto, 'precio' => number_format($precio, 2, '.', ''), 'categoria' => $categoria,
        'imagen' => archivo('tique.png', dibujarTique(500 + $indice), 'image/png'),
    ]);
    $facturas++;
}
// Tesorería revisa y paga: lo de la ronda anterior, pagado; lo reciente, a medias.
$listado = $tesoreria->get('/api/facturas.php?accion=listar')['facturas'] ?? [];
$conceptos = array_column($compras, 0);
foreach ($listado as $factura) {
    if (!in_array($factura['concepto'], $conceptos, true)) {
        continue;
    }
    $antigua = new DateTimeImmutable($factura['fecha_compra']) < $inicioRonda;
    $azar = mt_rand(1, 100);
    $estado = $antigua || $azar <= 35 ? 'pagada' : ($azar <= 65 ? 'revisada' : null);
    if ($estado !== null) {
        $tesoreria->formulario('/api/facturas.php?accion=estado', ['id' => (string) $factura['id'], 'estado' => 'revisada']);
        if ($estado === 'pagada') {
            $tesoreria->formulario('/api/facturas.php?accion=estado', ['id' => (string) $factura['id'], 'estado' => 'pagada']);
        }
    }
}
echo "  {$facturas} facturas con su tique\n";

// ---------- Inventario ----------

echo "Inventario\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/inventario-ejemplo.php') . ' '
    . escapeshellarg($base) . ' ' . escapeshellarg(sinAcentos('Javier López') . DOMINIO_MONITORES) . ' '
    . escapeshellarg(CONTRASENA_EJEMPLO) . ' --confirmar');

array_map('unlink', glob($carpetaTemporal . '/*'));
rmdir($carpetaTemporal);
echo "\nHecho. Todas las cuentas de ejemplo entran con la contraseña " . CONTRASENA_EJEMPLO . ".\n"
    . "Tesorería: marta.rodriguez" . DOMINIO_MONITORES . " · Familia de ejemplo: ana.garcia" . DOMINIO_FAMILIAS . "\n";
