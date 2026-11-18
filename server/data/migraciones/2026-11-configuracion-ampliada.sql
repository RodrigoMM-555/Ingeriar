-- E-18: configuración ampliada. Ejecutar una vez en bases creadas antes de E-18.
-- Colores de tipos de evento y secciones, horario por defecto y plazo de respuesta de las
-- familias por tipo de evento, y plazo propio de cada evento (NULL = el de su tipo).

ALTER TABLE tipos_evento
    ADD COLUMN color CHAR(7) NULL AFTER nombre,
    ADD COLUMN hora_inicio TIME NULL AFTER color,
    ADD COLUMN hora_fin TIME NULL AFTER hora_inicio,
    ADD COLUMN apertura_dias SMALLINT UNSIGNED NULL AFTER hora_fin,
    ADD COLUMN cierre_horas SMALLINT UNSIGNED NULL AFTER apertura_dias;

ALTER TABLE secciones
    ADD COLUMN color CHAR(7) NULL AFTER nombre;

ALTER TABLE eventos
    ADD COLUMN apertura_dias SMALLINT UNSIGNED NULL AFTER circular,
    ADD COLUMN cierre_horas SMALLINT UNSIGNED NULL AFTER apertura_dias;

-- Valores iniciales: los colores que ya usaba la aplicación.
UPDATE tipos_evento SET color = '#2563eb', hora_inicio = '18:00', hora_fin = '19:30', apertura_dias = 30, cierre_horas = 0
    WHERE codigo = 'reunión de familias';
UPDATE tipos_evento SET color = '#7c3aed', hora_inicio = '10:30', hora_fin = '13:30', apertura_dias = 14, cierre_horas = 12
    WHERE codigo = 'sabado';
UPDATE tipos_evento SET color = '#db2777', hora_inicio = '17:00', hora_fin = '20:00', apertura_dias = 30, cierre_horas = 24
    WHERE codigo = 'festival';
UPDATE tipos_evento SET color = '#059669', hora_inicio = '09:00', hora_fin = '18:00', apertura_dias = 21, cierre_horas = 48
    WHERE codigo = 'excursion';
UPDATE tipos_evento SET color = '#d97706', hora_inicio = '18:00', hora_fin = NULL, apertura_dias = 30, cierre_horas = 72
    WHERE codigo IN ('acampada', 'campamento');
UPDATE tipos_evento SET color = '#64748b', apertura_dias = 30, cierre_horas = 24
    WHERE color IS NULL;

UPDATE secciones SET color = '#ef6c00' WHERE codigo = 'colonia';
UPDATE secciones SET color = '#f2b705' WHERE codigo = 'manada';
UPDATE secciones SET color = '#1e63c6' WHERE codigo = 'tropa';
UPDATE secciones SET color = '#d32f2f' WHERE codigo = 'posta';
UPDATE secciones SET color = '#2e7d32' WHERE codigo = 'rutas';
