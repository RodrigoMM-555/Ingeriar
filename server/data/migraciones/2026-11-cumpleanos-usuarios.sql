-- Dashboard (2026-11-17): fecha de nacimiento opcional de los usuarios, para mostrar los
-- cumpleaños de los monitores de la semana.
ALTER TABLE usuarios ADD COLUMN fecha_nacimiento DATE NULL AFTER telefono;
