<?php

// Registro de accesos a documentación sensible (E-06): quién, qué y cuándo.
// No se guardan nombres de educandos en el detalle, solo identificadores, para que
// al borrar un educando vencido no quede rastro de sus datos en el registro.

function registrarAcceso(
    PDO $db,
    array $usuario,
    string $accion,
    string $recurso,
    ?int $educandoId = null,
    ?string $detalle = null
): void {
    try {
        $db->prepare(
            'INSERT INTO registro_accesos
                (id_usuario, usuario_email, accion, recurso, id_educando, detalle, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $usuario['id'],
            (string) ($usuario['email'] ?? ''),
            $accion,
            $recurso,
            $educandoId,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $error) {
        // Si falta la tabla (migración sin ejecutar) no se bloquea el acceso,
        // pero se deja constancia en el log de errores de PHP.
        error_log('Ingeriar: no se pudo registrar el acceso: ' . $error->getMessage());
    }
}
