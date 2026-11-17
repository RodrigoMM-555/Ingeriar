-- Documentos base iniciales (E-14). Se puede ejecutar varias veces (INSERT IGNORE).
-- Fichas de la FEV que usa el grupo. Las plantillas en blanco las sube el administrador
-- desde Configuración (cada grupo usa las suyas). Las versiones «mayor de edad» no son
-- obligatorias porque un educando menor no puede entregar las dos.
INSERT IGNORE INTO documentos_base
    (codigo, nombre, tipo, archivo_plantilla, obligatorio, activo, orden)
VALUES
    ('participacion', 'Autorización de participación en actividades', 'participacion', NULL, FALSE, TRUE, 0),
    ('ficha_inscripcion', 'Ficha de inscripción en el grupo (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 10),
    ('ficha_sanitaria_menor', 'Ficha sanitaria (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 20),
    ('ficha_sanitaria_mayor', 'Ficha sanitaria (mayor de edad)', 'inscripcion', NULL, FALSE, TRUE, 30),
    ('exclusion_responsabilidad', 'Exclusión de responsabilidad', 'inscripcion', NULL, TRUE, TRUE, 40),
    ('autorizacion_ausencia', 'Autorización para ausentarse de actividades', 'inscripcion', NULL, TRUE, TRUE, 50),
    ('autorizacion_imagen_menor', 'Autorización de uso de imagen (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 60),
    ('autorizacion_imagen_mayor', 'Autorización de uso de imagen (mayor de edad)', 'inscripcion', NULL, FALSE, TRUE, 70),
    ('autorizacion_whatsapp', 'Autorización para grupos de WhatsApp', 'inscripcion', NULL, TRUE, TRUE, 80),
    ('autorizacion_vehiculo', 'Autorización de viaje en vehículo privado', 'inscripcion', NULL, TRUE, TRUE, 90),
    ('autorizacion_volver_solo', 'Autorización para volver solo a casa', 'inscripcion', NULL, TRUE, TRUE, 100);
