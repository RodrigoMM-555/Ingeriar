<?php

require_once __DIR__ . '/Modelo.php';

// Usuario de la aplicación (tabla usuarios) y sus cargos (usuarios_roles, E-13).
// La contraseña nunca sale del servidor: está en OCULTAS.
class Usuario extends Modelo
{
    protected const TABLA = 'usuarios';
    protected const COLUMNAS = [
        'nombre' => 'nombre',
        'apellidos' => 'apellidos',
        'email' => 'email',
        'telefono' => 'telefono',
        'contraseña' => 'contrasena',
    ];
    protected const OCULTAS = ['contraseña'];

    public string $nombre = '';
    public string $apellidos = '';
    public string $email = '';
    public string $telefono = '';
    public string $contrasena = '';

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellidos);
    }

    // Cargos de todos los usuarios: [id => ['codigos' => […], 'nombres' => […], 'basico' => código]].
    public static function cargosDeTodos(PDO $db): array
    {
        $cargos = [];
        foreach ($db->query(
            'SELECT ur.id_usuario, r.codigo, r.nombre, r.basico
             FROM usuarios_roles ur JOIN roles r ON r.codigo = ur.codigo_rol
             ORDER BY r.basico DESC, r.orden'
        ) as $fila) {
            $id = (int) $fila['id_usuario'];
            $cargos[$id]['codigos'][] = $fila['codigo'];
            $cargos[$id]['nombres'][] = $fila['nombre'];
            if ($fila['basico']) {
                $cargos[$id]['basico'] = $fila['codigo'];
            }
        }
        return $cargos;
    }

    // Sustituye sus cargos. La validación (un cargo básico, cargos activos) la hace
    // validarRolesUsuario() de permisos.php antes de llamar a este método.
    public function asignarCargos(PDO $db, array $codigos): void
    {
        $db->prepare('DELETE FROM usuarios_roles WHERE id_usuario = ?')->execute([$this->id]);
        $insertar = $db->prepare('INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES (?, ?)');
        foreach ($codigos as $codigo) {
            $insertar->execute([$this->id, $codigo]);
        }
    }

    // Sustituye los educandos de los que es tutor.
    public function asignarEducandos(PDO $db, array $idsEducandos): void
    {
        $db->prepare('DELETE FROM usuarios_educandos WHERE id_usuario = ?')->execute([$this->id]);
        $insertar = $db->prepare('INSERT INTO usuarios_educandos (id_usuario, id_educando) VALUES (?, ?)');
        foreach (array_unique($idsEducandos) as $educandoId) {
            $insertar->execute([$this->id, $educandoId]);
        }
    }
}
