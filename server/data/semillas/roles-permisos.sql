-- Roles y permisos iniciales (E-13). Se puede ejecutar varias veces (INSERT IGNORE).
-- Lo usan el instalador y la migración 2026-11-roles.sql; schema.sql incluye lo mismo.
-- Cargos básicos (basico = TRUE): todo usuario tiene exactamente uno.
-- Cargos adicionales (basico = FALSE): se suman al básico y solo añaden permisos nuevos.

INSERT IGNORE INTO roles (codigo, nombre, basico, activo, orden) VALUES
    ('administrador', 'Administrador', TRUE, TRUE, 10),
    ('scouter', 'Monitor', TRUE, TRUE, 20),
    ('tutor', 'Tutor', TRUE, TRUE, 30),
    ('tesoreria', 'Tesorería', FALSE, TRUE, 40);

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
    ('usuarios.ver', 'Ver la lista de usuarios (para elegir responsables)'),
    ('usuarios.gestionar', 'Crear, editar y borrar usuarios y asignar cargos'),
    ('educandos.ver_propios', 'Ver solo los educandos vinculados'),
    ('educandos.ver_todos', 'Ver todos los educandos'),
    ('educandos.gestionar', 'Crear y editar educandos, incluida la baja'),
    ('educandos.borrar', 'Borrar educandos definitivamente'),
    ('eventos.ver', 'Ver el calendario de eventos'),
    ('eventos.gestionar', 'Crear, editar y borrar eventos'),
    ('asistencia.responder_propios', 'Responder asistencias y subir entregas de sus educandos'),
    ('asistencia.ver_todas', 'Ver las asistencias de todos los educandos'),
    ('asistencia.gestionar', 'Editar directamente la tabla de asistencias'),
    ('documentos.ver_todos', 'Ver la documentación entregada de cualquier educando'),
    ('galeria.ver', 'Ver la galería de fotos'),
    ('galeria.subir', 'Subir y borrar fotos de la galería'),
    ('facturas.subir_propias', 'Subir y gestionar sus propias facturas'),
    ('facturas.ver_todas', 'Ver las facturas de todos'),
    ('facturas.cambiar_estado', 'Cambiar el estado de cualquier factura'),
    ('facturas.gestionar_todas', 'Editar y borrar facturas de otros'),
    ('configuracion.gestionar', 'Cambiar la configuración del grupo y los catálogos'),
    ('roles.gestionar', 'Crear cargos y editar sus permisos'),
    ('copia.descargar', 'Descargar copias de seguridad'),
    ('conservacion.gestionar', 'Eliminar los datos vencidos de educandos dados de baja'),
    ('registro.ver', 'Consultar el registro de accesos'),
    ('documentos.subir_propios', 'Entregar la documentación de inscripción de sus educandos'),
    ('documentos.revisar', 'Revisar la documentación de inscripción y entregarla en nombre de las familias');

-- El administrador tiene todos los permisos salvo los de tutor («sus» educandos), que
-- describen una relación y no una capacidad: con ellos vería el panel de tutor.
INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso)
SELECT 'administrador', codigo FROM permisos
WHERE codigo NOT IN ('educandos.ver_propios', 'asistencia.responder_propios', 'documentos.subir_propios');

INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso) VALUES
    ('scouter', 'usuarios.ver'),
    ('scouter', 'educandos.ver_todos'),
    ('scouter', 'educandos.gestionar'),
    ('scouter', 'eventos.ver'),
    ('scouter', 'eventos.gestionar'),
    ('scouter', 'asistencia.ver_todas'),
    ('scouter', 'documentos.ver_todos'),
    ('scouter', 'galeria.ver'),
    ('scouter', 'galeria.subir'),
    ('scouter', 'facturas.subir_propias'),
    ('scouter', 'documentos.revisar'),
    ('tutor', 'educandos.ver_propios'),
    ('tutor', 'eventos.ver'),
    ('tutor', 'asistencia.responder_propios'),
    ('tutor', 'galeria.ver'),
    ('tutor', 'documentos.subir_propios'),
    ('tesoreria', 'facturas.ver_todas'),
    ('tesoreria', 'facturas.cambiar_estado');
