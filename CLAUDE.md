# Ingeriar

Proyecto intermodular de DAM (2.º curso). Aplicación web de gestión interna del Grupo Scout Seeonee.
Existe un segundo chat de Claude (el "chat del documento") que redacta la memoria del proyecto a
partir de este repositorio. Este archivo y los de `docs/` son el puente entre ambos.

## Stack y límites
- Cliente: HTML, CSS y JavaScript sin frameworks. Servidor: PHP con PDO. Base de datos: MySQL. Servidor web: Apache.
- Solo se usan conocimientos del temario de DAM2 o de lo visto en https://github.com/jocarsa/ceac2627dam2.
  No se añaden frameworks ni librerías nuevas sin consultarlo antes.
- El inventario usa ficheros JSON (base documental). El resto de datos va en MySQL.

## Estructura
- `public/`: cliente y puntos de entrada web (único directorio accesible desde Apache).
- `server/`: API, autenticación y datos. `storage/`: archivos privados, nunca servidos directamente.
- `noteas.md`: documentación técnica y diario de cambios.

## Reglas de seguridad (obligatorias)
- Los permisos se comprueban siempre en el servidor, nunca solo en el cliente.
- Consultas preparadas con PDO. Nunca concatenar valores en SQL.
- No usar `root` desde PHP. No subir al repositorio credenciales, `config` con claves ni datos reales de personas.
  El repositorio es público: solo datos ficticios.
- Los archivos subidos se validan por tipo real (finfo), tamaño y nombre aleatorio, y se guardan en `storage/`.

## Forma de trabajar
1. Las tareas están en `docs/ENCARGOS.md`. Se trabaja en un solo encargo cada vez, en una rama `feat/E-XX-nombre`.
2. Antes de modificar código, explicar el plan y esperar confirmación.
3. Al terminar, añadir una entrada a `docs/REGISTRO-CAMBIOS.md` (formato dentro del archivo), añadir una línea
   al diario de `noteas.md` y cambiar el estado del encargo en `ENCARGOS.md`.
4. No hacer push a `main`. El usuario revisa y fusiona. El chat del documento solo ve `main`.
5. No editar la memoria del proyecto (no está en este repositorio).
6. Si un cambio contradice lo que dice un requisito (RF/RNF) o un encargo, avisar en lugar de decidirlo solo.
7. Código y comentarios en español. Mantener el estilo existente (nombres en español, `estilo.css` por secciones).
