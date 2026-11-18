<?php

// Roles y permisos (E-13).
//
// Cada usuario tiene un cargo básico (administrador, monitor o tutor) y puede tener
// cargos adicionales (tesorería…). Sus permisos son la unión de los permisos de todos
// sus cargos activos. Las rutas preguntan por permisos, nunca por el nombre del cargo.
//
// En cada petición se vuelven a leer de la base de datos (una sola consulta) y se
// guardan en la sesión: así, quitar un permiso o borrar un usuario tiene efecto
// inmediato, sin esperar a que cierre la sesión.

require_once __DIR__ . '/sesion.php';

// Lee los cargos activos y los permisos de un usuario. Devuelve null si el usuario
// ya no existe.
function cargarPermisosUsuario(PDO $db, int $usuarioId): ?array
{
    $stmt = $db->prepare(
        'SELECT u.id, u.nombre, u.apellidos, u.email,
                r.codigo AS rol, r.nombre AS nombre_rol, r.basico, r.orden,
                rp.codigo_permiso
         FROM usuarios u
         LEFT JOIN usuarios_roles ur ON ur.id_usuario = u.id
         LEFT JOIN roles r ON r.codigo = ur.codigo_rol AND r.activo = 1
         LEFT JOIN roles_permisos rp ON rp.codigo_rol = r.codigo
         WHERE u.id = ?'
    );
    $stmt->execute([$usuarioId]);
    $filas = $stmt->fetchAll();
    if (!$filas) {
        return null;
    }

    $roles = [];
    $permisos = [];
    foreach ($filas as $fila) {
        if ($fila['rol'] !== null) {
            $roles[$fila['rol']] = [
                'codigo' => $fila['rol'],
                'nombre' => $fila['nombre_rol'],
                'basico' => (bool) $fila['basico'],
                'orden' => (int) $fila['orden'],
            ];
        }
        if ($fila['codigo_permiso'] !== null) {
            $permisos[$fila['codigo_permiso']] = true;
        }
    }
    // Primero el cargo básico y después los adicionales, por orden.
    uasort($roles, fn($a, $b) => [!$a['basico'], $a['orden']] <=> [!$b['basico'], $b['orden']]);
    $roles = array_values(array_map(
        fn($rol) => ['codigo' => $rol['codigo'], 'nombre' => $rol['nombre'], 'basico' => $rol['basico']],
        $roles
    ));
    $basico = array_values(array_filter($roles, fn($rol) => $rol['basico']))[0]['codigo'] ?? null;

    $primera = $filas[0];
    return [
        'id' => (int) $primera['id'],
        'nombre' => $primera['nombre'],
        'apellidos' => $primera['apellidos'],
        'email' => $primera['email'],
        'rol_basico' => $basico,
        'roles' => $roles,
        'permisos' => array_keys($permisos),
    ];
}

// Comprueba la sesión y recarga los permisos. Si el usuario ya no existe, cierra la sesión.
function exigirUsuario(PDO $db): array
{
    $sesion = exigirAutenticacion();
    // E-18: cierre de sesión tras X minutos sin actividad (0 = sin límite).
    $configuracion = json_decode((string) @file_get_contents(__DIR__ . '/../data/config.php'), true);
    $minutos = max(0, (int) ($configuracion['sesion_inactividad_minutos'] ?? 0));
    $ultima = (int) ($_SESSION['ultima_actividad'] ?? time());
    if ($minutos > 0 && time() - $ultima > $minutos * 60) {
        $_SESSION = [];
        session_destroy();
        responder(['error' => 'La sesión se ha cerrado por inactividad', 'sesion_caducada' => true], 401);
    }
    $_SESSION['ultima_actividad'] = time();
    $usuario = cargarPermisosUsuario($db, (int) $sesion['id']);
    if ($usuario === null) {
        $_SESSION = [];
        session_destroy();
        responder(['error' => 'No autenticado'], 401);
    }
    $_SESSION['usuario'] = $usuario;
    return $usuario;
}

// ¿Tiene el usuario de la sesión este permiso?
function tienePermiso(string $codigo): bool
{
    return in_array($codigo, $_SESSION['usuario']['permisos'] ?? [], true);
}

// ¿Tiene al menos uno de estos permisos?
function tieneAlgunPermiso(array $codigos): bool
{
    foreach ($codigos as $codigo) {
        if (tienePermiso($codigo)) {
            return true;
        }
    }
    return false;
}

// Corta la petición con 403 si no tiene ninguno de los permisos indicados.
function exigirPermiso(string ...$codigos): void
{
    if (!tieneAlgunPermiso($codigos)) {
        responder(['error' => 'No tienes permisos para esta operación'], 403);
    }
}

// ¿Tiene otro usuario (no el de la sesión) un permiso? Por ejemplo, para comprobar que
// el responsable de una factura puede tener facturas.
function usuarioTienePermiso(PDO $db, int $usuarioId, string $codigo): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM usuarios_roles ur
         JOIN roles r ON r.codigo = ur.codigo_rol AND r.activo = 1
         JOIN roles_permisos rp ON rp.codigo_rol = r.codigo
         WHERE ur.id_usuario = ? AND rp.codigo_permiso = ?
         LIMIT 1'
    );
    $stmt->execute([$usuarioId, $codigo]);
    return (bool) $stmt->fetchColumn();
}

// Número de usuarios que pueden gestionar roles. Nunca debe quedar en cero:
// sería imposible volver a dar permisos a nadie.
function usuariosQueGestionanRoles(PDO $db): int
{
    return (int) $db->query(
        "SELECT COUNT(DISTINCT ur.id_usuario)
         FROM usuarios_roles ur
         JOIN roles r ON r.codigo = ur.codigo_rol AND r.activo = 1
         JOIN roles_permisos rp ON rp.codigo_rol = r.codigo
         WHERE rp.codigo_permiso = 'roles.gestionar'"
    )->fetchColumn();
}

// Comprueba la lista de cargos de un usuario: deben existir y estar activos, y debe
// haber exactamente un cargo básico. Devuelve el mensaje de error o null.
function validarRolesUsuario(PDO $db, array $codigos): ?string
{
    $codigos = array_values(array_unique(array_map('strval', $codigos)));
    if (!$codigos) {
        return 'El usuario debe tener un cargo básico';
    }
    $marcas = implode(',', array_fill(0, count($codigos), '?'));
    $stmt = $db->prepare("SELECT codigo, basico FROM roles WHERE activo = 1 AND codigo IN ({$marcas})");
    $stmt->execute($codigos);
    $encontrados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($encontrados) !== count($codigos)) {
        return 'Algún cargo no existe o está desactivado';
    }
    $basicos = count(array_filter($encontrados, fn($basico) => (bool) $basico));
    if ($basicos !== 1) {
        return 'El usuario debe tener exactamente un cargo básico (administrador, monitor o tutor)';
    }
    return null;
}

// ¿Existe todavía la columna antigua usuarios.rol? (hasta ejecutar la segunda migración)
function existeColumnaRolAntigua(PDO $db): bool
{
    static $existe = null;
    if ($existe === null) {
        $stmt = $db->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol'"
        );
        $existe = (bool) $stmt->fetchColumn();
    }
    return $existe;
}
