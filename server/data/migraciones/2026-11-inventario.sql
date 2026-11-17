-- E-04: inventario de material. Los datos van en ficheros JSON (storage/inventario/),
-- no en MySQL; aquí solo se añade el permiso para administradores y monitores.
INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
    ('inventario.gestionar', 'Gestionar el inventario de material y las listas de material');

INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso) VALUES
    ('administrador', 'inventario.gestionar'),
    ('scouter', 'inventario.gestionar');
