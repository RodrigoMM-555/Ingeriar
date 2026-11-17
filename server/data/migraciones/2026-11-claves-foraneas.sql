-- Claves foráneas que faltan en bases creadas con el instalador antiguo (modeloDeDatos.txt
-- no creaba las claves: MySQL ignora REFERENCES escrito dentro de una columna).
-- Primero se borran o corrigen las filas huérfanas y después se crea cada clave solo si
-- no existe. En una base creada con schema.sql no cambia nada.

-- 1. Filas huérfanas (apuntan a registros que ya no existen).
DELETE FROM usuarios_educandos
WHERE id_usuario NOT IN (SELECT id FROM usuarios)
   OR id_educando NOT IN (SELECT id FROM educandos);
DELETE FROM educandos_asistencias
WHERE id_educando NOT IN (SELECT id FROM educandos)
   OR id_evento NOT IN (SELECT id FROM eventos);
DELETE FROM eventos_secciones WHERE id_evento NOT IN (SELECT id FROM eventos);
UPDATE eventos SET id_responsable = NULL
WHERE id_responsable IS NOT NULL AND id_responsable NOT IN (SELECT id FROM usuarios);

-- 2. Crear cada clave si no existe ninguna clave foránea sobre esa columna.
DELIMITER //

DROP PROCEDURE IF EXISTS crear_clave_si_falta//
CREATE PROCEDURE crear_clave_si_falta(
    IN tabla VARCHAR(64), IN columna VARCHAR(64), IN definicion TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tabla
          AND COLUMN_NAME = columna AND REFERENCED_TABLE_NAME IS NOT NULL
    ) THEN
        SET @sentencia = CONCAT('ALTER TABLE `', tabla, '` ADD ', definicion);
        PREPARE ejecutar FROM @sentencia;
        EXECUTE ejecutar;
        DEALLOCATE PREPARE ejecutar;
    END IF;
END//

DELIMITER ;

CALL crear_clave_si_falta('facturas', 'responsable_id',
    'CONSTRAINT fk_facturas_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE');
CALL crear_clave_si_falta('usuarios_educandos', 'id_usuario',
    'FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE');
CALL crear_clave_si_falta('usuarios_educandos', 'id_educando',
    'FOREIGN KEY (id_educando) REFERENCES educandos(id) ON DELETE CASCADE ON UPDATE CASCADE');
CALL crear_clave_si_falta('eventos', 'id_responsable',
    'FOREIGN KEY (id_responsable) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE');
CALL crear_clave_si_falta('eventos_secciones', 'id_evento',
    'FOREIGN KEY (id_evento) REFERENCES eventos(id) ON DELETE CASCADE ON UPDATE CASCADE');
CALL crear_clave_si_falta('educandos_asistencias', 'id_educando',
    'FOREIGN KEY (id_educando) REFERENCES educandos(id) ON DELETE CASCADE ON UPDATE CASCADE');
CALL crear_clave_si_falta('educandos_asistencias', 'id_evento',
    'FOREIGN KEY (id_evento) REFERENCES eventos(id) ON DELETE CASCADE ON UPDATE CASCADE');

DROP PROCEDURE crear_clave_si_falta;
