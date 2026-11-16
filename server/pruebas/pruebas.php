<?php

// Pruebas automáticas de Ingeriar (E-07).
//
// Comprueba por HTTP el login, las contraseñas, los permisos por rol, la copia de
// seguridad, el instalador y la galería. Crea sus propios usuarios, educando y
// eventos de prueba (correos @pruebas.ingeriar.test) y los borra al terminar.
//
// Uso (desde la raíz del proyecto, con las mismas variables de entorno que Apache):
//   INGERIAR_DB_PASSWORD=... php server/pruebas/pruebas.php http://localhost/ingeriar --confirmar
//   Añadir --markdown para obtener la tabla de resultados para docs/PLAN-PRUEBAS.md.
//
// Ejecutar SOLO contra una base de datos de pruebas: escribe y borra datos.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../auth/contrasenas.php';

$argumentos = array_slice($argv, 1);
$base = rtrim((string) ($argumentos[0] ?? ''), '/');
if ($base === '' || str_starts_with($base, '--') || !in_array('--confirmar', $argumentos, true)) {
    fwrite(STDERR, "Uso: php server/pruebas/pruebas.php <url-base> --confirmar [--markdown]\n"
        . "Ejemplo: php server/pruebas/pruebas.php http://localhost --confirmar\n"
        . "Escribe y borra datos de prueba: úsalo solo con una base de datos de pruebas.\n");
    exit(2);
}
$formatoMarkdown = in_array('--markdown', $argumentos, true);

const DOMINIO_PRUEBAS = '@pruebas.ingeriar.test';
const CONTRASENA_PRUEBAS = 'pruebas-2026';

// ---------- Cliente HTTP con su propia sesión (una cookie por rol) ----------

class ClienteHttp
{
    private string $cookies;

    public function __construct(private string $base)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'ingeriar-cookies-');
    }

    public function __destruct()
    {
        if (is_file($this->cookies)) {
            unlink($this->cookies);
        }
    }

    public function get(string $ruta): array
    {
        return $this->peticion($ruta, null);
    }

    public function post(string $ruta, array|string $campos): array
    {
        return $this->peticion($ruta, $campos);
    }

    public function json(string $ruta, array $datos): array
    {
        return $this->peticion($ruta, json_encode($datos), ['Content-Type: application/json']);
    }

    public function iniciarSesion(string $email): int
    {
        return $this->json('/api/login.php', [
            'email' => $email,
            'contrasena' => CONTRASENA_PRUEBAS,
        ])['codigo'];
    }

    private function peticion(string $ruta, array|string|null $cuerpo, array $cabeceras = []): array
    {
        $cabecerasRespuesta = [];
        $curl = curl_init($this->base . $ruta);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_HTTPHEADER => $cabeceras,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HEADERFUNCTION => function ($curl, string $linea) use (&$cabecerasRespuesta): int {
                $partes = explode(':', $linea, 2);
                if (count($partes) === 2) {
                    $cabecerasRespuesta[strtolower(trim($partes[0]))] = trim($partes[1]);
                }
                return strlen($linea);
            },
        ]);
        if ($cuerpo !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $cuerpo);
        }
        $texto = curl_exec($curl);
        $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        if ($texto === false) {
            throw new RuntimeException('No se pudo conectar con ' . $this->base . ': ' . $error);
        }
        return [
            'codigo' => $codigo,
            'texto' => $texto,
            'json' => json_decode($texto, true),
            'cabeceras' => $cabecerasRespuesta,
        ];
    }
}

// ---------- Registro de resultados ----------

$resultados = [];

function prueba(string $id, string $descripcion, callable $comprobacion): void
{
    global $resultados;
    try {
        $detalle = $comprobacion();
        $superada = $detalle === true || $detalle === null;
        $mensaje = is_string($detalle) ? $detalle : '';
    } catch (Throwable $error) {
        $superada = false;
        $mensaje = $error->getMessage();
    }
    $resultados[] = compact('id', 'descripcion', 'superada', 'mensaje');
    echo ($superada ? '  OK     ' : '  FALLO  ') . $id . '  ' . $descripcion
        . ($superada || $mensaje === '' ? '' : '  → ' . $mensaje) . "\n";
}

// Devuelve true o un texto que explica qué se esperaba.
function esperar(array $respuesta, int $codigo): bool|string
{
    return $respuesta['codigo'] === $codigo
        ? true
        : "se esperaba {$codigo} y llegó {$respuesta['codigo']}: " . substr($respuesta['texto'], 0, 120);
}

// PNG mínimo válido generado sin la extensión gd.
function crearPng(string $ruta): void
{
    $ancho = 8;
    $alto = 8;
    $filas = str_repeat("\x00" . str_repeat("\xC8\x1E\x1E", $ancho), $alto);
    $trozo = static fn(string $tipo, string $datos): string =>
        pack('N', strlen($datos)) . $tipo . $datos . pack('N', crc32($tipo . $datos));
    file_put_contents($ruta, "\x89PNG\r\n\x1a\n"
        . $trozo('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0))
        . $trozo('IDAT', gzcompress($filas))
        . $trozo('IEND', ''));
}

// ---------- Preparación de datos de prueba ----------

$db = obtenerConexion();

function limpiarDatosPrueba(PDO $db): void
{
    $eventos = $db->query("SELECT id FROM eventos WHERE nombre LIKE 'Prueba automática%'")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($eventos as $evento) {
        $fotos = $db->prepare('SELECT archivo FROM galeria_fotos WHERE id_evento = ?');
        $fotos->execute([$evento]);
        foreach ($fotos->fetchAll(PDO::FETCH_COLUMN) as $archivo) {
            $ruta = __DIR__ . '/../../storage/galeria/' . basename($archivo);
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        $db->prepare('DELETE FROM eventos WHERE id = ?')->execute([$evento]);
    }
    $db->prepare("DELETE FROM educandos WHERE DNI = '99999999Z'")->execute();
    $db->prepare('DELETE FROM usuarios WHERE email LIKE ?')->execute(['%' . DOMINIO_PRUEBAS]);
}

function crearUsuario(PDO $db, string $nombre, string $rol, string $telefono, ?string $hash = null): int
{
    $db->prepare(
        "INSERT INTO usuarios (nombre, apellidos, email, telefono, `contraseña`, rol)
         VALUES (?, 'Prueba', ?, ?, ?, ?)"
    )->execute([
        $nombre,
        $nombre . DOMINIO_PRUEBAS,
        $telefono,
        $hash ?? cifrarContrasena(CONTRASENA_PRUEBAS),
        $rol,
    ]);
    return (int) $db->lastInsertId();
}

function crearEvento(PDO $db, string $nombre, string $seccion): int
{
    $db->prepare(
        "INSERT INTO eventos (nombre, fecha_inicio, fecha_fin, tipo)
         VALUES (?, '2026-10-10 10:00:00', '2026-10-10 13:00:00', 'sabado')"
    )->execute([$nombre]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO eventos_secciones (id_evento, seccion) VALUES (?, ?)')
        ->execute([$id, $seccion]);
    return $id;
}

limpiarDatosPrueba($db);
$imagen = tempnam(sys_get_temp_dir(), 'ingeriar-png-');
$falsa = tempnam(sys_get_temp_dir(), 'ingeriar-falsa-');
crearPng($imagen);
file_put_contents($falsa, '<?php echo "no soy una imagen"; ?>');

try {
    crearUsuario($db, 'admin', 'administrador', '699000001');
    crearUsuario($db, 'scouter', 'scouter', '699000002');
    $tutorId = crearUsuario($db, 'tutor', 'tutor', '699000003');
    $antiguoId = crearUsuario($db, 'antiguo', 'tutor', '699000004', hash('sha256', CONTRASENA_PRUEBAS));
    crearUsuario($db, 'enclaro', 'tutor', '699000005', CONTRASENA_PRUEBAS);

    // Educando con 11 años al empezar la ronda 2026-2027: sección tropa.
    $db->prepare(
        "INSERT INTO educandos (nombre, apellidos, DNI, naz)
         VALUES ('Educando', 'Prueba', '99999999Z', '2015-03-01')"
    )->execute();
    $educandoId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO usuarios_educandos (id_usuario, id_educando) VALUES (?, ?)')
        ->execute([$tutorId, $educandoId]);

    $eventoTropa = crearEvento($db, 'Prueba automática tropa', 'tropa');
    $eventoColonia = crearEvento($db, 'Prueba automática colonia', 'colonia');

    $anonimo = new ClienteHttp($base);
    $admin = new ClienteHttp($base);
    $scouter = new ClienteHttp($base);
    $tutor = new ClienteHttp($base);

    echo "Pruebas contra {$base}\n\nAutenticación\n";

    prueba('AUT-01', 'Login con contraseña correcta', fn() => $admin->iniciarSesion('admin' . DOMINIO_PRUEBAS) === 200
        ?: 'no se pudo iniciar sesión');
    $scouter->iniciarSesion('scouter' . DOMINIO_PRUEBAS);
    $tutor->iniciarSesion('tutor' . DOMINIO_PRUEBAS);
    prueba('AUT-02', 'Login con contraseña incorrecta → 401', fn() => esperar(
        $anonimo->json('/api/login.php', ['email' => 'admin' . DOMINIO_PRUEBAS, 'contrasena' => 'otra-1234']), 401));
    prueba('AUT-03', 'Login con email inexistente → 401 (mismo mensaje)', function () use ($anonimo) {
        $respuesta = $anonimo->json('/api/login.php', ['email' => 'nadie' . DOMINIO_PRUEBAS, 'contrasena' => 'otra-1234']);
        return esperar($respuesta, 401) === true && ($respuesta['json']['error'] ?? '') === 'Email o contraseña incorrectos'
            ?: 'respuesta distinta: ' . $respuesta['texto'];
    });
    prueba('AUT-04', 'Login sin datos → 400', fn() => esperar($anonimo->json('/api/login.php', []), 400));
    prueba('AUT-05', 'Login por GET → 405', fn() => esperar($anonimo->get('/api/login.php'), 405));
    prueba('AUT-06', 'Usuario con sha256 antiguo entra y queda migrado a password_hash', function () use ($base, $db, $antiguoId) {
        $codigo = (new ClienteHttp($base))->iniciarSesion('antiguo' . DOMINIO_PRUEBAS);
        $stmt = $db->prepare('SELECT `contraseña` FROM usuarios WHERE id = ?');
        $stmt->execute([$antiguoId]);
        $hash = (string) $stmt->fetchColumn();
        return $codigo === 200 && str_starts_with($hash, '$2y$') ?: "código {$codigo}, hash " . substr($hash, 0, 7);
    });
    prueba('AUT-07', 'Contraseña guardada en claro no da acceso → 401', fn() => (new ClienteHttp($base))
        ->iniciarSesion('enclaro' . DOMINIO_PRUEBAS) === 401 ?: 'permitió el acceso');
    prueba('AUT-08', 'API sin sesión → 401', fn() => esperar($anonimo->get('/api/superapi.php?ruta=usuario_actual'), 401));

    echo "\nContraseñas\n";
    $nuevoUsuario = fn(string $contrasena, string $email = 'nuevo') => $admin->post('/api/superapi.php?ruta=crear', [
        'tabla' => 'usuarios',
        'datos' => json_encode([
            'nombre' => 'Nuevo', 'apellidos' => 'Prueba', 'email' => $email . DOMINIO_PRUEBAS,
            'telefono' => '699000099', 'contraseña' => $contrasena, 'rol' => 'tutor',
        ]),
    ]);
    prueba('CON-01', 'Alta con contraseña de menos de 8 caracteres → 400', fn() => esperar($nuevoUsuario('abc1'), 400));
    prueba('CON-02', 'Alta con contraseña solo de letras → 400', fn() => esperar($nuevoUsuario('abcdefgh'), 400));
    prueba('CON-03', 'Alta sin contraseña → 400', fn() => esperar($nuevoUsuario(''), 400));
    prueba('CON-04', 'Alta válida guarda un hash de password_hash', function () use ($nuevoUsuario, $db) {
        $resultado = esperar($nuevoUsuario('scouts2026'), 200);
        $hash = (string) $db->query("SELECT `contraseña` FROM usuarios WHERE email = 'nuevo" . DOMINIO_PRUEBAS . "'")->fetchColumn();
        return $resultado === true && str_starts_with($hash, '$2y$') ?: ($resultado === true ? 'hash ' . substr($hash, 0, 7) : $resultado);
    });
    prueba('CON-05', 'Editar sin contraseña conserva la actual', function () use ($admin, $db) {
        $stmt = $db->query("SELECT id, `contraseña` FROM usuarios WHERE email = 'nuevo" . DOMINIO_PRUEBAS . "'");
        $antes = $stmt->fetch();
        $respuesta = $admin->post('/api/superapi.php?ruta=editar', [
            'tabla' => 'usuarios', 'id' => $antes['id'],
            'datos' => json_encode([
                'nombre' => 'Editado', 'apellidos' => 'Prueba', 'email' => 'nuevo' . DOMINIO_PRUEBAS,
                'telefono' => '699000099', 'contraseña' => '', 'rol' => 'tutor',
            ]),
        ]);
        $despues = $db->query('SELECT `contraseña` FROM usuarios WHERE id = ' . (int) $antes['id'])->fetchColumn();
        return esperar($respuesta, 200) === true && $despues === $antes['contraseña'] ?: 'la contraseña cambió';
    });
    foreach (['CON-06' => [$admin, 'administrador'], 'CON-07' => [$scouter, 'scouter']] as $id => [$cliente, $rol]) {
        prueba($id, "La tabla usuarios no envía contraseñas ({$rol})", function () use ($cliente) {
            $respuesta = $cliente->get('/api/superapi.php?ruta=tabla&tabla=usuarios');
            return esperar($respuesta, 200) === true && !str_contains($respuesta['texto'], 'contrase')
                ?: 'aparece la columna contraseña';
        });
    }

    echo "\nPermisos por rol\n";
    prueba('PER-01', 'Tutor no puede leer usuarios → 403', fn() => esperar($tutor->get('/api/superapi.php?ruta=tabla&tabla=usuarios'), 403));
    prueba('PER-02', 'Tutor solo ve sus educandos', function () use ($tutor, $educandoId) {
        $registros = $tutor->get('/api/superapi.php?ruta=tabla&tabla=educandos')['json']['registros'] ?? null;
        $ids = array_map('intval', array_column((array) $registros, 'id'));
        return $ids === [$educandoId] ?: 'recibió ' . count($ids) . ' educandos';
    });
    prueba('PER-03', 'Tutor no puede crear eventos → 403', fn() => esperar($tutor->post('/api/superapi.php?ruta=crear', [
        'tabla' => 'eventos', 'datos' => json_encode(['nombre' => 'X', 'tipo' => 'sabado']),
    ]), 403));
    prueba('PER-04', 'Scouter no puede crear usuarios → 403', fn() => esperar($scouter->post('/api/superapi.php?ruta=crear', [
        'tabla' => 'usuarios', 'datos' => json_encode(['nombre' => 'X']),
    ]), 403));
    prueba('PER-05', 'Scouter no puede borrar usuarios → 403', fn() => esperar($scouter->post('/api/superapi.php?ruta=borrar', [
        'tabla' => 'usuarios', 'id' => $tutorId,
    ]), 403));
    prueba('PER-06', 'Tutor solo tiene la entidad educandos', fn() => ($tutor->get('/api/superapi.php?ruta=entidades')['json'] ?? null) === ['educandos']
        ?: 'lista de entidades distinta');
    prueba('PER-07', 'Scouter no puede guardar la configuración → 403', fn() => esperar($scouter->post('/api/superapi.php?ruta=configuracion', [
        'configuracion' => json_encode(['nombre_grupo' => 'X']),
    ]), 403));
    prueba('PER-08', 'Tutor no puede consultar educandos de otro usuario → 403', fn() => esperar(
        $tutor->get('/api/superapi.php?ruta=educandos_usuario&usuario=1'), 403));

    echo "\nCopia de seguridad (salida.php)\n";
    prueba('SAL-01', 'Sin sesión → 401', fn() => esperar($anonimo->get('/servicios/salida.php'), 401));
    prueba('SAL-02', 'Scouter → 403', fn() => esperar($scouter->get('/servicios/salida.php'), 403));
    prueba('SAL-03', 'Administrador descarga la copia sin contraseñas', function () use ($admin) {
        $respuesta = $admin->get('/servicios/salida.php');
        return esperar($respuesta, 200) === true && isset($respuesta['json']['usuarios'])
            && !array_key_exists('contraseña', $respuesta['json']['usuarios'][0] ?? [])
            ?: 'copia incorrecta o con contraseñas';
    });

    echo "\nInstalador\n";
    prueba('INS-01', 'Instalación en uso, sin sesión → 403', fn() => esperar($anonimo->post('/api/instalar.php', ['modelo' => "x\n- a INT"]), 403));
    prueba('INS-02', 'Instalación en uso, como tutor → 403', fn() => esperar($tutor->post('/api/instalar.php', ['modelo' => "x\n- a INT"]), 403));

    echo "\nGalería\n";
    $subir = fn(ClienteHttp $cliente, int $evento, string $archivo, string $nombre) => $cliente->post(
        '/api/galeria.php?accion=subir',
        ['evento' => (string) $evento, 'fotos[0]' => new CURLFile($archivo, 'image/png', $nombre)]
    );
    prueba('GAL-01', 'Tutor no puede subir fotos → 403', fn() => esperar($subir($tutor, $eventoTropa, $imagen, 'a.png'), 403));
    prueba('GAL-02', 'Archivo que no es imagen → 415', fn() => esperar($subir($scouter, $eventoTropa, $falsa, 'falsa.jpg'), 415));
    prueba('GAL-03', 'Scouter sube fotos válidas', function () use ($subir, $scouter, $eventoTropa, $eventoColonia, $imagen) {
        $primera = esperar($subir($scouter, $eventoTropa, $imagen, 'tropa.png'), 200);
        $segunda = esperar($subir($scouter, $eventoColonia, $imagen, 'colonia.png'), 200);
        return $primera === true && $segunda === true ?: (is_string($primera) ? $primera : $segunda);
    });
    $fotoDe = function (int $evento) use ($db): int {
        $stmt = $db->prepare('SELECT id FROM galeria_fotos WHERE id_evento = ? LIMIT 1');
        $stmt->execute([$evento]);
        return (int) $stmt->fetchColumn();
    };
    prueba('GAL-04', 'El tutor ve el evento de su sección y no el de otra', function () use ($tutor, $eventoTropa, $eventoColonia) {
        $texto = json_encode($tutor->get('/api/galeria.php?accion=listar')['json']);
        $eventos = [];
        foreach (json_decode($texto, true)['rondas'] ?? [] as $ronda) {
            foreach ($ronda['tipos'] as $tipo) {
                $eventos = [...$eventos, ...array_column($tipo['eventos'], 'id')];
            }
        }
        return in_array($eventoTropa, $eventos, true) && !in_array($eventoColonia, $eventos, true)
            ?: 'eventos visibles: ' . implode(', ', $eventos);
    });
    prueba('GAL-05', 'El tutor abre una foto de su sección', function () use ($tutor, $fotoDe, $eventoTropa) {
        $respuesta = $tutor->get('/api/galeria.php?accion=ver&id=' . $fotoDe($eventoTropa));
        return esperar($respuesta, 200) === true && ($respuesta['cabeceras']['content-type'] ?? '') === 'image/png'
            ?: 'no se sirvió la imagen';
    });
    prueba('GAL-06', 'El tutor no abre una foto de otra sección → 403', fn() => esperar(
        $tutor->get('/api/galeria.php?accion=ver&id=' . $fotoDe($eventoColonia)), 403));
    prueba('GAL-07', 'Ver una foto sin sesión → 401', fn() => esperar(
        $anonimo->get('/api/galeria.php?accion=ver&id=' . $fotoDe($eventoTropa)), 401));
    prueba('GAL-08', 'Tutor no puede borrar fotos → 403', fn() => esperar(
        $tutor->post('/api/galeria.php?accion=borrar', ['id' => (string) $fotoDe($eventoTropa)]), 403));
    prueba('GAL-09', 'Scouter borra una foto y su archivo', function () use ($scouter, $db, $fotoDe, $eventoColonia) {
        $id = $fotoDe($eventoColonia);
        $archivo = (string) $db->query('SELECT archivo FROM galeria_fotos WHERE id = ' . $id)->fetchColumn();
        $resultado = esperar($scouter->post('/api/galeria.php?accion=borrar', ['id' => (string) $id]), 200);
        $existe = is_file(__DIR__ . '/../../storage/galeria/' . $archivo);
        return $resultado === true && !$existe ?: ($resultado === true ? 'el archivo sigue en disco' : $resultado);
    });
} finally {
    limpiarDatosPrueba($db);
    unlink($imagen);
    unlink($falsa);
}

$superadas = count(array_filter($resultados, fn($resultado) => $resultado['superada']));
$total = count($resultados);
echo "\n{$superadas} de {$total} pruebas superadas.\n";

if ($formatoMarkdown) {
    $fecha = date('Y-m-d');
    echo "\n| ID | Prueba | Resultado | Fecha |\n|---|---|---|---|\n";
    foreach ($resultados as $resultado) {
        echo '| ' . $resultado['id'] . ' | ' . $resultado['descripcion'] . ' | '
            . ($resultado['superada'] ? 'Superada' : 'No superada') . ' | ' . $fecha . " |\n";
    }
}

exit($superadas === $total ? 0 : 1);
