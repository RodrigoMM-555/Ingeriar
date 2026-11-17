<?php

// Pruebas del instalador interactivo (E-11), incluida la de aceptación: instalar con el
// asistente deja el mismo esquema y los mismos datos iniciales que subir schema.sql.
//
// Hace falta un servidor web de la aplicación configurado con una base de datos VACÍA
// (INGERIAR_DB_NAME) y las mismas variables de entorno en esta terminal:
//   INGERIAR_DB_NAME=<base vacía> INGERIAR_DB_PASSWORD=... \
//     php server/pruebas/pruebas-instalador.php http://localhost:8098 --confirmar
// Al terminar, la base se deja vacía otra vez.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../instalacion/ModeloDatos.php';

$base = rtrim((string) ($argv[1] ?? ''), '/');
if ($base === '' || !in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, "Uso: INGERIAR_DB_NAME=<base vacía> php server/pruebas/pruebas-instalador.php <url> --confirmar\n");
    exit(2);
}

$resultados = [];
function prueba(string $id, string $descripcion, callable $comprobacion): void
{
    global $resultados;
    try {
        $detalle = $comprobacion();
        $superada = $detalle === true;
        $mensaje = is_string($detalle) ? $detalle : '';
    } catch (Throwable $error) {
        $superada = false;
        $mensaje = $error->getMessage();
    }
    $resultados[] = $superada;
    echo ($superada ? '  OK     ' : '  FALLO  ') . "{$id}  {$descripcion}" . ($superada ? '' : "  → {$mensaje}") . "\n";
}

function http(string $url, ?array $json = null, ?array $multipart = null, ?string $cookies = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    if ($cookies !== null) {
        curl_setopt_array($curl, [CURLOPT_COOKIEFILE => $cookies, CURLOPT_COOKIEJAR => $cookies]);
    }
    if ($json !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($json),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    } elseif ($multipart !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $multipart]);
    }
    $texto = (string) curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return ['codigo' => $codigo, 'json' => json_decode($texto, true), 'texto' => $texto];
}

function vaciarBase(PDO $db): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
        $db->exec('DROP TABLE `' . $tabla . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Foto de la base: estructura (modelo) y filas de las tablas de datos iniciales.
function fotoBase(PDO $db): array
{
    $modelo = ModeloDatos::validar(ModeloDatos::desdeBase($db));
    $tablas = array_column($modelo['tablas'], null, 'nombre');
    ksort($tablas);
    foreach ($tablas as &$tabla) {
        usort($tabla['foraneas'], fn($a, $b) => strcmp($a['columna'], $b['columna']));
        sort($tabla['unicos']);
    }
    unset($tabla);
    $datos = [];
    foreach (['tipos_evento', 'secciones', 'categorias_factura', 'roles', 'permisos', 'roles_permisos', 'documentos_base'] as $tabla) {
        $filas = $db->query('SELECT * FROM `' . $tabla . '`')->fetchAll(PDO::FETCH_ASSOC);
        sort($filas);
        $datos[$tabla] = $filas;
    }
    return ['estructura' => $tablas, 'datos' => $datos];
}

$db = obtenerConexion();
$schema = tempnam(sys_get_temp_dir(), 'schema-');
$peligroso = tempnam(sys_get_temp_dir(), 'peligroso-');
echo "Pruebas del instalador contra {$base} (base «" . $db->query('SELECT DATABASE()')->fetchColumn() . "»)\n\n";
try {
    if ($db->query('SHOW TABLES')->fetchColumn()) {
        throw new RuntimeException('La base de datos no está vacía');
    }
    $foto = null;

    prueba('INI-01', 'En una base vacía el instalador está disponible sin sesión', function () use ($base) {
        $estado = http($base . '/api/instalar.php?accion=estado')['json'] ?? [];
        return ($estado['puede_instalar'] ?? false) && !($estado['en_uso'] ?? true) ?: json_encode($estado);
    });
    prueba('INI-02', 'Un schema.sql con DROP, GRANT o INTO OUTFILE se rechaza sin ejecutar nada', function () use ($base, $peligroso, $db) {
        $codigos = [];
        foreach (["CREATE TABLE a (id INT PRIMARY KEY);\nDROP TABLE usuarios;",
                     "GRANT ALL ON *.* TO 'x'@'%';",
                     "CREATE TABLE b (id INT PRIMARY KEY);\nSELECT * FROM usuarios INTO OUTFILE '/tmp/x';"] as $sql) {
            file_put_contents($peligroso, $sql);
            $codigos[] = http($base . '/api/instalar.php?accion=instalar_sql', null,
                ['archivo' => new CURLFile($peligroso, 'text/plain', 'schema.sql')])['codigo'];
        }
        $tablas = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        return $codigos === [400, 400, 400] && !$tablas ?: 'códigos ' . implode(',', $codigos) . ', tablas ' . implode(',', $tablas);
    });
    prueba('INI-03', 'Un modelo con errores se rechaza con un mensaje claro', function () use ($base) {
        $respuesta = http($base . '/api/instalar.php?accion=vista_previa', ['modelo' => ['tablas' => [[
            'nombre' => 'prueba', 'clave_primaria' => ['id'], 'columnas' => [['nombre' => 'id', 'tipo' => 'INT']],
            'foraneas' => [['columna' => 'id', 'tabla' => 'no_existe', 'referencia' => 'id']],
        ]]]]);
        return $respuesta['codigo'] === 400 && str_contains($respuesta['json']['error'] ?? '', 'no_existe') ?: $respuesta['texto'];
    });
    prueba('INI-04', 'Instalar subiendo schema.sql crea el esquema completo', function () use ($base, $schema, $db, &$foto) {
        copy(__DIR__ . '/../data/schema.sql', $schema);
        $respuesta = http($base . '/api/instalar.php?accion=instalar_sql', null,
            ['archivo' => new CURLFile($schema, 'text/plain', 'schema.sql')]);
        if ($respuesta['codigo'] !== 200) {
            return $respuesta['texto'];
        }
        $foto = fotoBase($db);
        return count($foto['estructura']) >= 18 && count($foto['datos']['roles']) >= 4 ?: 'tablas ' . count($foto['estructura']);
    });
    prueba('INI-05', 'Instalar con el asistente deja el mismo esquema y datos que schema.sql (aceptación)', function () use ($base, $db, &$foto) {
        vaciarBase($db);
        $modelo = http($base . '/api/instalar.php?accion=modelo')['json'];
        $respuesta = http($base . '/api/instalar.php?accion=instalar', [
            'modelo' => $modelo, 'semillas' => ['catalogos', 'roles', 'documentos'],
            'administrador' => ['nombre' => 'Ana', 'apellidos' => 'Instaladora', 'email' => 'admin@instalador.test',
                'telefono' => '600000000', 'contrasena' => 'instalar-2026'],
        ]);
        if ($respuesta['codigo'] !== 200) {
            return $respuesta['texto'];
        }
        $asistente = fotoBase($db);
        if ($asistente['estructura'] !== $foto['estructura']) {
            foreach ($foto['estructura'] as $nombre => $tabla) {
                if ($tabla !== ($asistente['estructura'][$nombre] ?? null)) {
                    return "la tabla «{$nombre}» es distinta";
                }
            }
            return 'distinto número de tablas';
        }
        foreach ($foto['datos'] as $tabla => $filas) {
            if ($filas !== $asistente['datos'][$tabla]) {
                return "los datos iniciales de «{$tabla}» son distintos";
            }
        }
        return true;
    });
    prueba('INI-06', 'El primer administrador entra con su cargo y sus permisos', function () use ($base) {
        $cookies = tempnam(sys_get_temp_dir(), 'cookies-');
        $login = http($base . '/api/login.php', ['email' => 'admin@instalador.test', 'contrasena' => 'instalar-2026'], null, $cookies);
        $usuario = http($base . '/api/superapi.php?ruta=usuario_actual', null, null, $cookies)['json']['usuario'] ?? [];
        unlink($cookies);
        return $login['codigo'] === 200 && in_array('roles.gestionar', $usuario['permisos'] ?? [], true)
            && ($usuario['roles'][0]['codigo'] ?? '') === 'administrador' ?: $login['texto'];
    });
    prueba('INI-07', 'Con la aplicación en uso, el instalador responde 403 sin sesión', function () use ($base) {
        $estado = http($base . '/api/instalar.php?accion=estado')['json'] ?? [];
        $instalar = http($base . '/api/instalar.php?accion=instalar', ['modelo' => ['tablas' => []]])['codigo'];
        return ($estado['en_uso'] ?? false) && !($estado['puede_instalar'] ?? true) && $instalar === 403
            ?: json_encode($estado) . " instalar {$instalar}";
    });
} finally {
    vaciarBase($db);
    unlink($schema);
    unlink($peligroso);
}

$superadas = count(array_filter($resultados));
echo "\n{$superadas} de " . count($resultados) . " pruebas superadas.\n";
exit($superadas === count($resultados) ? 0 : 1);
