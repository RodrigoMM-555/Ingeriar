<?php

// Modelo de datos del instalador (E-11).
//
// Un modelo es un JSON con las tablas, sus columnas, claves, índices y claves foráneas:
//
//   {"tablas": [{"nombre": "usuarios",
//                "columnas": [{"nombre": "id", "tipo": "INT", "sin_signo": true,
//                              "nulo": false, "auto": true}, …],
//                "clave_primaria": ["id"], "unicos": [["email"]], "indices": [],
//                "foraneas": [{"columna": "id_usuario", "tabla": "usuarios",
//                              "referencia": "id", "al_borrar": "CASCADE",
//                              "al_actualizar": "CASCADE", "nombre": null}]}]}
//
// Esta clase valida el modelo y genera el SQL. El cliente nunca envía SQL en el modo
// asistente: los nombres se validan y se escriben entre comillas invertidas y los
// valores se escapan con PDO::quote. Las tablas se crean primero y las claves foráneas
// se añaden después con ALTER TABLE, así el orden de las tablas no importa.

class ModeloDatos
{
    public const TIPOS = ['VARCHAR', 'CHAR', 'INT', 'SMALLINT', 'TINYINT', 'DECIMAL',
        'DATE', 'DATETIME', 'BOOLEAN', 'TEXT', 'ENUM'];
    public const ACCIONES_FK = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];
    private const ENTEROS = ['INT', 'SMALLINT', 'TINYINT'];

    public static function identificadorValido($nombre): bool
    {
        return is_string($nombre) && preg_match('/^[\p{L}_][\p{L}\p{N}_]{0,63}$/u', $nombre) === 1;
    }

    private static function error(string $mensaje): never
    {
        throw new InvalidArgumentException($mensaje);
    }

    // Comprueba el modelo y lo devuelve normalizado. Lanza InvalidArgumentException con
    // un mensaje claro si algo no es válido.
    public static function validar(array $modelo): array
    {
        $tablas = $modelo['tablas'] ?? null;
        if (!is_array($tablas) || !$tablas) {
            self::error('El modelo no tiene tablas');
        }
        $normalizado = [];
        $nombresTablas = [];
        foreach ($tablas as $tabla) {
            $nombre = $tabla['nombre'] ?? '';
            if (!self::identificadorValido($nombre)) {
                self::error("Nombre de tabla no válido: «{$nombre}»");
            }
            if (isset($nombresTablas[$nombre])) {
                self::error("La tabla «{$nombre}» está repetida");
            }
            $nombresTablas[$nombre] = true;
            $columnas = [];
            foreach ((array) ($tabla['columnas'] ?? []) as $columna) {
                $columna = self::validarColumna($nombre, $columna);
                if (isset($columnas[$columna['nombre']])) {
                    self::error("La columna «{$columna['nombre']}» está repetida en «{$nombre}»");
                }
                $columnas[$columna['nombre']] = $columna;
            }
            if (!$columnas) {
                self::error("La tabla «{$nombre}» no tiene columnas");
            }
            $existeColumna = function ($lista, string $que) use ($columnas, $nombre): array {
                $lista = array_values(array_filter((array) $lista, fn($c) => $c !== ''));
                foreach ($lista as $columna) {
                    if (!isset($columnas[$columna])) {
                        self::error("{$que} de «{$nombre}» usa una columna que no existe: «{$columna}»");
                    }
                }
                return $lista;
            };
            $primaria = $existeColumna($tabla['clave_primaria'] ?? [], 'La clave primaria');
            if (!$primaria) {
                self::error("La tabla «{$nombre}» necesita una clave primaria");
            }
            foreach ($columnas as $columna) {
                if ($columna['auto'] && !in_array($columna['nombre'], $primaria, true)) {
                    self::error("En «{$nombre}», AUTO_INCREMENT solo se admite en la clave primaria");
                }
            }
            $normalizado[] = [
                'nombre' => $nombre,
                'columnas' => array_values($columnas),
                'clave_primaria' => $primaria,
                'unicos' => array_map(fn($u) => $existeColumna($u, 'Un índice único'), (array) ($tabla['unicos'] ?? [])),
                'indices' => array_map(fn($i) => [
                    'nombre' => self::identificadorValido($i['nombre'] ?? '') ? $i['nombre'] : self::error("Índice sin nombre válido en «{$nombre}»"),
                    'columnas' => $existeColumna($i['columnas'] ?? [], 'Un índice'),
                ], (array) ($tabla['indices'] ?? [])),
                'foraneas' => $tabla['foraneas'] ?? [],
            ];
        }
        // Las claves foráneas se validan al final, cuando ya se conocen todas las tablas.
        $indice = array_column($normalizado, null, 'nombre');
        foreach ($normalizado as &$tabla) {
            $foraneas = [];
            foreach ((array) $tabla['foraneas'] as $foranea) {
                $columna = $foranea['columna'] ?? '';
                $destino = $foranea['tabla'] ?? '';
                $referencia = $foranea['referencia'] ?? '';
                if (!in_array($columna, array_column($tabla['columnas'], 'nombre'), true)) {
                    self::error("Clave foránea de «{$tabla['nombre']}» sobre una columna que no existe: «{$columna}»");
                }
                if (!isset($indice[$destino]) || !in_array($referencia, array_column($indice[$destino]['columnas'], 'nombre'), true)) {
                    self::error("La clave foránea «{$tabla['nombre']}.{$columna}» apunta a «{$destino}.{$referencia}», que no existe");
                }
                $alBorrar = strtoupper($foranea['al_borrar'] ?? 'RESTRICT');
                $alActualizar = strtoupper($foranea['al_actualizar'] ?? 'RESTRICT');
                if (!in_array($alBorrar, self::ACCIONES_FK, true) || !in_array($alActualizar, self::ACCIONES_FK, true)) {
                    self::error("Acción ON DELETE/ON UPDATE no válida en «{$tabla['nombre']}.{$columna}»");
                }
                $nombreFk = $foranea['nombre'] ?? null;
                if ($nombreFk !== null && $nombreFk !== '' && !self::identificadorValido($nombreFk)) {
                    self::error("Nombre de clave foránea no válido: «{$nombreFk}»");
                }
                $foraneas[] = ['columna' => $columna, 'tabla' => $destino, 'referencia' => $referencia,
                    'al_borrar' => $alBorrar, 'al_actualizar' => $alActualizar, 'nombre' => $nombreFk ?: null];
            }
            $tabla['foraneas'] = $foraneas;
        }
        unset($tabla);
        return ['tablas' => $normalizado];
    }

    private static function validarColumna(string $tabla, $columna): array
    {
        $nombre = $columna['nombre'] ?? '';
        if (!self::identificadorValido($nombre)) {
            self::error("Nombre de columna no válido en «{$tabla}»: «{$nombre}»");
        }
        $tipo = strtoupper((string) ($columna['tipo'] ?? ''));
        if (!in_array($tipo, self::TIPOS, true)) {
            self::error("Tipo no válido en «{$tabla}.{$nombre}»: «{$tipo}»");
        }
        $resultado = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'nulo' => (bool) ($columna['nulo'] ?? true),
            'auto' => (bool) ($columna['auto'] ?? false),
            'sin_signo' => in_array($tipo, self::ENTEROS, true) && (bool) ($columna['sin_signo'] ?? false),
            'defecto' => $columna['defecto'] ?? null,
        ];
        if (in_array($tipo, ['VARCHAR', 'CHAR'], true)) {
            $longitud = (int) ($columna['longitud'] ?? 0);
            if ($longitud < 1 || $longitud > ($tipo === 'CHAR' ? 255 : 16383)) {
                self::error("Longitud no válida en «{$tabla}.{$nombre}»");
            }
            $resultado['longitud'] = $longitud;
        }
        if ($tipo === 'DECIMAL') {
            $precision = (int) ($columna['precision'] ?? 10);
            $escala = (int) ($columna['escala'] ?? 2);
            if ($precision < 1 || $precision > 65 || $escala < 0 || $escala > 30 || $escala > $precision) {
                self::error("Precisión o escala no válidas en «{$tabla}.{$nombre}»");
            }
            $resultado['precision'] = $precision;
            $resultado['escala'] = $escala;
        }
        if ($tipo === 'ENUM') {
            $valores = array_values(array_filter(array_map('strval', (array) ($columna['valores'] ?? [])), fn($v) => $v !== ''));
            if (!$valores) {
                self::error("El ENUM «{$tabla}.{$nombre}» necesita valores");
            }
            $resultado['valores'] = $valores;
        }
        if ($resultado['auto'] && !in_array($tipo, self::ENTEROS, true)) {
            self::error("AUTO_INCREMENT solo en columnas enteras («{$tabla}.{$nombre}»)");
        }
        if ($resultado['defecto'] === 'CURRENT_TIMESTAMP' && $tipo !== 'DATETIME') {
            self::error("CURRENT_TIMESTAMP solo como valor por defecto de DATETIME («{$tabla}.{$nombre}»)");
        }
        return $resultado;
    }

    private static function nombre(string $identificador): string
    {
        return '`' . str_replace('`', '``', $identificador) . '`';
    }

    private static function lista(array $columnas): string
    {
        return implode(', ', array_map([self::class, 'nombre'], $columnas));
    }

    private static function definicionColumna(PDO $db, array $columna): string
    {
        $tipo = match ($columna['tipo']) {
            'VARCHAR', 'CHAR' => "{$columna['tipo']}({$columna['longitud']})",
            'DECIMAL' => "DECIMAL({$columna['precision']}, {$columna['escala']})",
            'ENUM' => 'ENUM(' . implode(', ', array_map([$db, 'quote'], $columna['valores'])) . ')',
            default => $columna['tipo'],
        };
        $sql = self::nombre($columna['nombre']) . ' ' . $tipo
            . ($columna['sin_signo'] ? ' UNSIGNED' : '')
            . ($columna['nulo'] ? ' NULL' : ' NOT NULL');
        $defecto = $columna['defecto'];
        if ($defecto !== null && $defecto !== '') {
            $sql .= ' DEFAULT ' . match (true) {
                $defecto === 'CURRENT_TIMESTAMP' => 'CURRENT_TIMESTAMP',
                $columna['tipo'] === 'BOOLEAN' => filter_var($defecto, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE',
                in_array($columna['tipo'], ['INT', 'SMALLINT', 'TINYINT', 'DECIMAL'], true) && is_numeric($defecto) => (string) $defecto,
                default => $db->quote((string) $defecto),
            };
        }
        return $sql . ($columna['auto'] ? ' AUTO_INCREMENT' : '');
    }

    // Sentencias SQL del modelo ya validado: primero las tablas y después las claves foráneas.
    public static function generarSql(PDO $db, array $modelo): array
    {
        $sentencias = [];
        foreach ($modelo['tablas'] as $tabla) {
            $partes = array_map(fn($columna) => '    ' . self::definicionColumna($db, $columna), $tabla['columnas']);
            $partes[] = '    PRIMARY KEY (' . self::lista($tabla['clave_primaria']) . ')';
            foreach ($tabla['unicos'] as $unico) {
                $partes[] = '    UNIQUE (' . self::lista($unico) . ')';
            }
            foreach ($tabla['indices'] as $indiceTabla) {
                $partes[] = '    INDEX ' . self::nombre($indiceTabla['nombre']) . ' (' . self::lista($indiceTabla['columnas']) . ')';
            }
            $sentencias[] = 'CREATE TABLE IF NOT EXISTS ' . self::nombre($tabla['nombre']) . " (\n"
                . implode(",\n", $partes) . "\n) ENGINE=InnoDB";
        }
        foreach ($modelo['tablas'] as $tabla) {
            foreach ($tabla['foraneas'] as $foranea) {
                $sentencias[] = 'ALTER TABLE ' . self::nombre($tabla['nombre']) . ' ADD '
                    . ($foranea['nombre'] ? 'CONSTRAINT ' . self::nombre($foranea['nombre']) . ' ' : '')
                    . 'FOREIGN KEY (' . self::nombre($foranea['columna']) . ') REFERENCES '
                    . self::nombre($foranea['tabla']) . ' (' . self::nombre($foranea['referencia']) . ')'
                    . " ON DELETE {$foranea['al_borrar']} ON UPDATE {$foranea['al_actualizar']}";
            }
        }
        return $sentencias;
    }

    // Construye el modelo a partir de una base existente (information_schema). Se usa para
    // generar modelo.json desde una base creada con schema.sql y en las pruebas.
    public static function desdeBase(PDO $db): array
    {
        $tablas = [];
        $consulta = fn(string $sql) => $db->query($sql)->fetchAll();
        foreach ($consulta("SELECT TABLE_NAME FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE() ORDER BY CREATE_TIME, TABLE_NAME") as $fila) {
            $tablas[$fila['TABLE_NAME']] = ['nombre' => $fila['TABLE_NAME'], 'columnas' => [],
                'clave_primaria' => [], 'unicos' => [], 'indices' => [], 'foraneas' => []];
        }
        foreach ($consulta("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                            ORDER BY TABLE_NAME, ORDINAL_POSITION") as $fila) {
            $tablas[$fila['TABLE_NAME']]['columnas'][] = self::columnaDesdeBase($fila);
        }
        $indices = [];
        foreach ($consulta("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX") as $fila) {
            $indices[$fila['TABLE_NAME']][$fila['INDEX_NAME']]['unico'] = !$fila['NON_UNIQUE'];
            $indices[$fila['TABLE_NAME']][$fila['INDEX_NAME']]['columnas'][] = $fila['COLUMN_NAME'];
        }
        $foraneas = $consulta("SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                                      k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
                               FROM information_schema.KEY_COLUMN_USAGE k
                               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                                 ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                               WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
                               ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME");
        $nombresForaneas = array_column($foraneas, 'CONSTRAINT_NAME');
        foreach ($foraneas as $fila) {
            $tablas[$fila['TABLE_NAME']]['foraneas'][] = [
                'columna' => $fila['COLUMN_NAME'], 'tabla' => $fila['REFERENCED_TABLE_NAME'],
                'referencia' => $fila['REFERENCED_COLUMN_NAME'], 'al_borrar' => $fila['DELETE_RULE'],
                'al_actualizar' => $fila['UPDATE_RULE'],
                // Los nombres automáticos de MySQL (tabla_ibfk_1) no se guardan.
                'nombre' => preg_match('/_ibfk_\d+$/', $fila['CONSTRAINT_NAME']) ? null : $fila['CONSTRAINT_NAME'],
            ];
        }
        foreach ($indices as $tabla => $porNombre) {
            $columnasFk = array_column($tablas[$tabla]['foraneas'], 'columna');
            foreach ($porNombre as $nombreIndice => $indice) {
                if ($nombreIndice === 'PRIMARY') {
                    $tablas[$tabla]['clave_primaria'] = $indice['columnas'];
                } elseif ($indice['unico']) {
                    $tablas[$tabla]['unicos'][] = $indice['columnas'];
                } elseif (!in_array($nombreIndice, $nombresForaneas, true)
                    && !($indice['columnas'] === [$nombreIndice] && in_array($nombreIndice, $columnasFk, true))) {
                    // Índices propios; los que MySQL crea solo para una clave foránea no se guardan.
                    $tablas[$tabla]['indices'][] = ['nombre' => $nombreIndice, 'columnas' => $indice['columnas']];
                }
            }
        }
        return ['tablas' => array_values($tablas)];
    }

    private static function columnaDesdeBase(array $fila): array
    {
        $tipoColumna = strtolower($fila['COLUMN_TYPE']);
        $columna = ['nombre' => $fila['COLUMN_NAME'], 'tipo' => strtoupper($fila['DATA_TYPE']),
            'nulo' => $fila['IS_NULLABLE'] === 'YES', 'auto' => str_contains($fila['EXTRA'], 'auto_increment'),
            'sin_signo' => str_contains($tipoColumna, 'unsigned'), 'defecto' => $fila['COLUMN_DEFAULT']];
        if ($tipoColumna === 'tinyint(1)') {
            $columna['tipo'] = 'BOOLEAN';
            $columna['defecto'] = $fila['COLUMN_DEFAULT'] === null ? null : (bool) $fila['COLUMN_DEFAULT'];
        } elseif (in_array($columna['tipo'], ['VARCHAR', 'CHAR'], true)) {
            $columna['longitud'] = (int) $fila['CHARACTER_MAXIMUM_LENGTH'];
        } elseif ($columna['tipo'] === 'DECIMAL') {
            $columna['precision'] = (int) $fila['NUMERIC_PRECISION'];
            $columna['escala'] = (int) $fila['NUMERIC_SCALE'];
        } elseif ($columna['tipo'] === 'ENUM') {
            preg_match_all("/'((?:''|[^'])*)'/", $fila['COLUMN_TYPE'], $valores);
            $columna['valores'] = array_map(fn($v) => str_replace("''", "'", $v), $valores[1]);
        }
        if ($columna['defecto'] !== null && str_starts_with(strtoupper((string) $columna['defecto']), 'CURRENT_TIMESTAMP')) {
            $columna['defecto'] = 'CURRENT_TIMESTAMP';
        }
        return $columna;
    }

    // ---------- Validación de un schema.sql subido ----------

    // Divide un fichero SQL en sentencias, respetando cadenas y comentarios.
    public static function dividirSentencias(string $sql): array
    {
        $sentencias = [];
        $actual = '';
        $longitud = strlen($sql);
        $comilla = null;
        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $sql[$i];
            if ($comilla !== null) {
                $actual .= $caracter;
                if ($caracter === '\\' && $comilla !== '`') {
                    $actual .= $sql[++$i] ?? '';
                } elseif ($caracter === $comilla) {
                    $comilla = null;
                }
                continue;
            }
            if ($caracter === '-' && substr($sql, $i, 2) === '--' || $caracter === '#') {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $longitud : $fin;
                $actual .= "\n";
                continue;
            }
            if ($caracter === '/' && substr($sql, $i, 2) === '/*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i = $fin === false ? $longitud : $fin + 1;
                $actual .= ' ';
                continue;
            }
            if (in_array($caracter, ["'", '"', '`'], true)) {
                $comilla = $caracter;
            }
            if ($caracter === ';') {
                if (trim($actual) !== '') {
                    $sentencias[] = trim($actual);
                }
                $actual = '';
                continue;
            }
            $actual .= $caracter;
        }
        if (trim($actual) !== '') {
            $sentencias[] = trim($actual);
        }
        return $sentencias;
    }

    // Clasifica una sentencia: 'ejecutar', 'omitir' (CREATE DATABASE y USE: se instala
    // siempre en la base configurada) o lanza InvalidArgumentException si no se admite.
    public static function clasificarSentencia(string $sentencia): string
    {
        // Para buscar palabras peligrosas se quitan antes las cadenas entre comillas.
        $sinCadenas = preg_replace("/'(?:\\\\.|''|[^'\\\\])*'|\"(?:\\\\.|[^\"\\\\])*\"/s", "''", $sentencia);
        if (preg_match('/\b(DROP|GRANT|REVOKE|LOAD|OUTFILE|DUMPFILE|DELETE|UPDATE|TRUNCATE|RENAME|CALL|PROCEDURE|FUNCTION|TRIGGER|EVENT|USER|SHUTDOWN|HANDLER|PREPARE|EXECUTE|DELIMITER)\b/i', $sinCadenas, $peligrosa)) {
            // ON UPDATE / ON DELETE de las claves foráneas sí se admiten.
            $limpia = preg_replace('/\bON\s+(UPDATE|DELETE)\b/i', '', $sinCadenas);
            if (preg_match('/\b(DROP|GRANT|REVOKE|LOAD|OUTFILE|DUMPFILE|DELETE|UPDATE|TRUNCATE|RENAME|CALL|PROCEDURE|FUNCTION|TRIGGER|EVENT|USER|SHUTDOWN|HANDLER|PREPARE|EXECUTE|DELIMITER)\b/i', $limpia, $peligrosa)) {
                self::error('Sentencia no permitida (contiene ' . strtoupper($peligrosa[1]) . '): ' . self::resumen($sentencia));
            }
        }
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $sentencia)) {
            return 'omitir';
        }
        if (preg_match('/^(CREATE\s+TABLE|INSERT\s+(IGNORE\s+)?INTO|ALTER\s+TABLE\s+\S+\s+ADD)\b/i', $sentencia)) {
            return 'ejecutar';
        }
        self::error('Solo se admiten CREATE TABLE, INSERT y ALTER TABLE … ADD: ' . self::resumen($sentencia));
    }

    private static function resumen(string $sentencia): string
    {
        $linea = preg_replace('/\s+/', ' ', $sentencia);
        return strlen($linea) > 80 ? substr($linea, 0, 77) . '…' : $linea;
    }
}
