<?php

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../auth/contrasenas.php';

function responderLogin(array $datos, int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderLogin(['error' => 'Método no permitido'], 405);
}

$datos = json_decode(file_get_contents('php://input'), true);
$email = trim((string) ($datos['email'] ?? ''));
$contrasena = (string) ($datos['contrasena'] ?? '');

if ($email === '' || $contrasena === '') {
    responderLogin(['error' => 'Email y contraseña son obligatorios'], 400);
}

try {
    $db = obtenerConexion();
    $stmt = $db->prepare(
        'SELECT id, nombre, apellidos, email, `contraseña`
         FROM usuarios WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
} catch (Throwable $error) {
    responderLogin([
        'error' => 'No se pudo conectar con la base de datos. '
            . 'Comprueba la configuración de MySQL y Apache.'
    ], 500);
}

if (!$usuario) {
    // Se calcula un hash igualmente para que la respuesta tarde lo mismo
    // tanto si el email existe como si no.
    password_verify($contrasena, HASH_COMPARACION_VACIA);
    responderLogin(['error' => 'Email o contraseña incorrectos'], 401);
}

$guardada = (string) $usuario['contraseña'];
if (!verificarContrasena($contrasena, $guardada)) {
    responderLogin(['error' => 'Email o contraseña incorrectos'], 401);
}

// Migración transparente: si la contraseña estaba en sha256 (versiones anteriores)
// o con un algoritmo antiguo, se reescribe con password_hash al entrar.
if (contrasenaNecesitaActualizarse($guardada)) {
    try {
        $db->prepare('UPDATE usuarios SET `contraseña` = ? WHERE id = ?')
            ->execute([cifrarContrasena($contrasena), (int) $usuario['id']]);
    } catch (Throwable $error) {
        // Si no se puede actualizar, el usuario entra igualmente y se reintenta en el próximo acceso.
    }
}

session_regenerate_id(true);
$_SESSION['ultima_actividad'] = time();
// Cargos y permisos del usuario (E-13). Se recargan en cada petición posterior.
$_SESSION['usuario'] = cargarPermisosUsuario($db, (int) $usuario['id']);

responderLogin(['usuario' => $_SESSION['usuario']]);
