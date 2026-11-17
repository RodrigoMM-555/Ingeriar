<?php

// Clase base de las clases de dominio (E-02): mapeo objeto-relacional hecho a mano.
//
// Cada subclase indica su tabla y qué columna de la base de datos corresponde a cada
// propiedad del objeto. Con eso, esta clase sabe:
//   - crear un objeto a partir de una fila (desdeFila) y buscarlo por id (buscar);
//   - cargar varios con una condición (donde);
//   - guardarlo (INSERT si es nuevo, UPDATE si ya tiene id) y borrarlo;
//   - convertirlo a JSON (jsonSerialize), sin las propiedades ocultas.
//
// Es lo que hace un ORM como Doctrine (PHP) o Hibernate (Java), pero sin librerías y
// visible en el código: todas las consultas son preparadas y los nombres de tabla y
// columna salen siempre de las constantes de la clase, nunca de datos del usuario.

abstract class Modelo implements JsonSerializable
{
    // Nombre de la tabla en la base de datos.
    protected const TABLA = '';

    // Columna de la base de datos => propiedad del objeto (sin la clave primaria «id»).
    protected const COLUMNAS = [];

    // Columnas que nunca se envían al cliente (por ejemplo, la contraseña).
    protected const OCULTAS = [];

    public ?int $id = null;

    // Crea un objeto a partir de una fila de la base de datos (array asociativo).
    public static function desdeFila(array $fila): static
    {
        $objeto = new static();
        $objeto->id = isset($fila['id']) ? (int) $fila['id'] : null;
        foreach (static::COLUMNAS as $columna => $propiedad) {
            if (array_key_exists($columna, $fila)) {
                $objeto->$propiedad = $fila[$columna];
            }
        }
        return $objeto;
    }

    // Copia en el objeto los valores de las columnas que vengan en $fila (por ejemplo,
    // los datos ya validados de un formulario). Las columnas desconocidas se ignoran.
    public function rellenar(array $fila): static
    {
        foreach (static::COLUMNAS as $columna => $propiedad) {
            if (array_key_exists($columna, $fila)) {
                $this->$propiedad = $fila[$columna];
            }
        }
        return $this;
    }

    // Devuelve el objeto con ese id, o null si no existe.
    public static function buscar(PDO $db, int $id): ?static
    {
        $resultado = static::donde($db, '`id` = ?', [$id]);
        return $resultado[0] ?? null;
    }

    // Carga los objetos que cumplen una condición. La condición la escribe siempre el
    // programador (con «?» para los valores); los valores van en $parametros.
    public static function donde(PDO $db, string $condicion = '1', array $parametros = [], string $orden = '`id`'): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM `' . static::TABLA . '` WHERE ' . $condicion . ' ORDER BY ' . $orden
        );
        $stmt->execute($parametros);
        return array_map(fn(array $fila) => static::desdeFila($fila), $stmt->fetchAll());
    }

    // Columna => valor, listo para guardar.
    public function aFila(): array
    {
        $fila = [];
        foreach (static::COLUMNAS as $columna => $propiedad) {
            $valor = $this->$propiedad;
            $fila[$columna] = is_bool($valor) ? (int) $valor : $valor;
        }
        return $fila;
    }

    // INSERT si el objeto es nuevo; UPDATE si ya tiene id.
    public function guardar(PDO $db): void
    {
        $fila = $this->aFila();
        $columnas = array_map(fn(string $columna) => '`' . $columna . '`', array_keys($fila));
        if ($this->id === null) {
            $db->prepare(
                'INSERT INTO `' . static::TABLA . '` (' . implode(', ', $columnas) . ') VALUES ('
                . implode(', ', array_fill(0, count($fila), '?')) . ')'
            )->execute(array_values($fila));
            $this->id = (int) $db->lastInsertId();
            return;
        }
        $cambios = array_map(fn(string $columna) => $columna . ' = ?', $columnas);
        $db->prepare(
            'UPDATE `' . static::TABLA . '` SET ' . implode(', ', $cambios) . ' WHERE `id` = ?'
        )->execute([...array_values($fila), $this->id]);
    }

    public function borrar(PDO $db): void
    {
        $db->prepare('DELETE FROM `' . static::TABLA . '` WHERE `id` = ?')->execute([$this->id]);
    }

    // JSON con los mismos nombres de columna que la base de datos (los que ya usa el cliente).
    public function jsonSerialize(): array
    {
        $datos = ['id' => $this->id] + $this->aFila();
        foreach (static::OCULTAS as $columna) {
            unset($datos[$columna]);
        }
        return $datos;
    }
}
