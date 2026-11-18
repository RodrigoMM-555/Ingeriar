<?php

// Funciones comunes para guardar y comprobar contraseñas de usuarios.
// Se usan desde el inicio de sesión, el alta y edición de usuarios y el instalador.

// Longitud mínima por defecto; el administrador la cambia en Configuración (E-18).
const CONTRASENA_LONGITUD_MINIMA = 8;

function longitudMinimaContrasena(): int
{
    $configuracion = json_decode((string) @file_get_contents(__DIR__ . '/../data/config.php'), true);
    $valor = (int) ($configuracion['contrasena_longitud_minima'] ?? CONTRASENA_LONGITUD_MINIMA);
    return max(6, min(64, $valor));
}

// Hash de una cadena aleatoria descartada. Se usa en el login cuando el email no existe
// para que la respuesta tarde lo mismo y no revele qué emails están registrados.
const HASH_COMPARACION_VACIA = '$2y$12$AHvzuPxuvVPQgtdgA8NVB.lt7/8LfL78/HY4i6Jk63zPQrooXliJi';

// Devuelve un mensaje de error si la contraseña no cumple las reglas, o null si es válida.
// Reglas: al menos 8 caracteres y al menos un número o un carácter especial
// (cualquier carácter que no sea una letra).
function validarContrasena(string $contrasena): ?string
{
    // Se cuentan caracteres (no bytes) con una expresión regular UTF-8,
    // para no depender de la extensión mbstring.
    $longitud = (int) preg_match_all('/./su', $contrasena);
    $minima = longitudMinimaContrasena();
    if ($longitud < $minima) {
        return 'La contraseña debe tener al menos ' . $minima . ' caracteres';
    }
    if (preg_match('/[^\p{L}]/u', $contrasena) !== 1) {
        return 'La contraseña debe incluir al menos un número o un carácter especial';
    }
    return null;
}

// Genera el hash que se guarda en la base de datos (algoritmo por defecto de PHP, con sal).
function cifrarContrasena(string $contrasena): string
{
    return password_hash($contrasena, PASSWORD_DEFAULT);
}

// Las versiones anteriores guardaban sha256 sin sal: 64 caracteres hexadecimales.
function esHashAntiguo(string $guardada): bool
{
    return preg_match('/^[0-9a-f]{64}$/i', $guardada) === 1;
}

// Comprueba la contraseña contra el valor guardado, admitiendo el formato antiguo.
function verificarContrasena(string $contrasena, string $guardada): bool
{
    if (esHashAntiguo($guardada)) {
        return hash_equals(strtolower($guardada), hash('sha256', $contrasena));
    }
    return password_verify($contrasena, $guardada);
}

// Indica si el valor guardado debe reescribirse con el algoritmo actual.
function contrasenaNecesitaActualizarse(string $guardada): bool
{
    return esHashAntiguo($guardada) || password_needs_rehash($guardada, PASSWORD_DEFAULT);
}
