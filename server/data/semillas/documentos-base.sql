-- Documentos base iniciales (E-14). Se puede ejecutar varias veces (INSERT IGNORE).
-- Las plantillas en blanco las sube el administrador desde Configuración; hasta entonces
-- solo la circular de participación tiene archivo.
INSERT IGNORE INTO documentos_base
    (codigo, nombre, tipo, archivo_plantilla, obligatorio, activo, orden)
VALUES
    ('participacion', 'Circular de participación en actividades', 'participacion', 'circular1.pdf', FALSE, TRUE, 0),
    ('ficha_sanitaria', 'Ficha sanitaria', 'inscripcion', NULL, TRUE, TRUE, 10),
    ('hoja_inscripcion', 'Hoja de inscripción', 'inscripcion', NULL, TRUE, TRUE, 20),
    ('exclusion_responsabilidad', 'Exclusión de responsabilidad', 'inscripcion', NULL, TRUE, TRUE, 30),
    ('autorizacion_ausencia', 'Autorización para ausentarse de actividades', 'inscripcion', NULL, TRUE, TRUE, 40);
