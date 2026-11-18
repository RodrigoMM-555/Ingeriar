class IngeriarConfiguracion {
    // Orden por defecto del menú lateral (claves de codigo.js: vistas y tablas). Se cambia
    // desde Configuración → «Menú de navegación»; lo que no esté aquí sale al final.
    static ORDEN_MENU_PREDETERMINADO = [
        "dashboard", "calendario", "galeria", "educandos", "usuarios",
        "inventario", "facturas", "registro_accesos"
    ];

    constructor({utilidades, nombre = "#nombre", cabecera = "header"}) {
        this.utilidades = utilidades;
        this.nombre = nombre;
        this.cabecera = cabecera;
    }

    cargar() {
        return this.utilidades.obtenerJson(
            "api/superapi.php?ruta=configuracion"
        ).then((datos) => {
            document.querySelector(this.nombre).textContent =
                datos.nombre_grupo || "Nombre del grupo";
            document.title = "Ingeriar | " +
                (datos.nombre_grupo || "Ingeriar");
            this.aplicarConfiguracion(datos);
            return datos;
        });
    }

    aplicarConfiguracion(configuracion) {
        this.configuracionActual = configuracion;
        document.documentElement.style.setProperty(
            "--color_corporativo",
            configuracion.color || "#8b0000"
        );
        document.documentElement.style.setProperty(
            "--color_secundario",
            configuracion.color_secundario || "#000000"
        );
        document.documentElement.dataset.modoOscuro =
            configuracion.modo_oscuro ? "true" : "false";
        document.documentElement.dataset.nombresTiposEvento = JSON.stringify(
            Object.fromEntries((configuracion.catalogos?.tipos_evento || [])
                .map((tipo) => [tipo.codigo, tipo.nombre]))
        );
        // E-18: el catálogo completo de tipos (horario y plazos para el formulario de eventos).
        document.documentElement.dataset.tiposEvento = JSON.stringify(
            configuracion.catalogos?.tipos_evento || []
        );
        document.documentElement.dataset.secciones = JSON.stringify(
            configuracion.catalogos?.secciones || []
        );
        document.documentElement.dataset.categoriasFactura = JSON.stringify(
            configuracion.catalogos?.categorias_factura || []
        );
        document.documentElement.dataset.contrasenaMinima =
            String(configuracion.contrasena_longitud_minima || 8);
        this.aplicarColoresCatalogos(configuracion.catalogos || {});
        const logo = document.querySelector("#logo-grupo");
        if (logo) {
            logo.hidden = !configuracion.logo_archivo;
            logo.src = configuracion.logo_archivo
                ? "api/superapi.php?ruta=configuracion_logo"
                : "";
        }
    }

    // Colores de secciones (fondo claro de filas y fichas) y de tipos de evento (calendario).
    // Los colores llegan validados del servidor (#rrggbb); los códigos se escapan.
    aplicarColoresCatalogos(catalogos) {
        let estilo = document.querySelector("#colores-catalogos");
        if (!estilo) {
            estilo = document.createElement("style");
            estilo.id = "colores-catalogos";
            document.head.appendChild(estilo);
        }
        const color = (valor) => /^#[0-9a-fA-F]{6}$/.test(valor || "") ? valor : null;
        const reglas = [];
        (catalogos.secciones || []).forEach((seccion) => {
            if (color(seccion.color)) {
                reglas.push(".fila-seccion-" + CSS.escape(seccion.codigo) +
                    "{background:color-mix(in srgb," + seccion.color + " 26%,white);}");
            }
        });
        (catalogos.tipos_evento || []).forEach((tipo) => {
            if (color(tipo.color)) {
                const clase = "." + this.utilidades.claseTipoEvento(tipo.codigo);
                reglas.push(clase + "," + ".detalle-evento" + clase + "{--color-evento:" + tipo.color + ";}");
            }
        });
        estilo.textContent = reglas.join("\n");
    }

    // ¿Algún apartado tiene cambios sin guardar? (para avisar al salir de Configuración)
    hayCambiosSinGuardar() {
        return Boolean(document.querySelector(".vista-configuracion [data-cambios]"));
    }

    crearBoton() {
        let boton = document.createElement("button");
        boton.textContent = "⚙";
        boton.className = "botonConfiguracion";
        boton.type = "button";
        boton.onclick = () => this.mostrar().catch((error) => {
            const seccion = document.querySelector("main > section");
            if (seccion) {
                seccion.className = "vista vista-configuracion";
                seccion.textContent =
                    "No se pudo cargar la configuración: " + error.message;
            }
        });
        document.querySelector(this.cabecera).appendChild(boton);
    }

    // Cada apartado es una caja con su propio botón Guardar (E-18). Las acciones (copias,
    // datos vencidos…) tienen sus botones dentro de su caja.
    mostrar() {
        return this.utilidades.obtenerJson(
            "api/superapi.php?ruta=configuracion"
        ).then((configuracion) => {
            this.configuracionActual = configuracion;
            let seccion = document.querySelector("main > section");
            if (!seccion) {
                throw new Error("No se encontró el contenedor principal");
            }
            seccion.className = "vista vista-configuracion";
            seccion.innerHTML = "";

            let titulo = document.createElement("h2");
            titulo.textContent = "Configuración";
            seccion.appendChild(titulo);

            const apartados = [
                ["Identidad del grupo", [
                    ["nombre_grupo", "Nombre del grupo", "text"],
                    ["logo_archivo", "Logo del grupo", "file"]
                ]],
                ["Apariencia", [
                    ["color", "Color principal", "color"],
                    ["color_secundario", "Color secundario", "color"],
                    ["modo_oscuro", "Modo oscuro", "checkbox"]
                ]],
                ["Información del grupo", [
                    ["cif", "CIF", "text"], ["direccion", "Dirección", "text"],
                    ["municipio", "Municipio", "text"],
                    ["provincia", "Provincia", "text"],
                    ["telefono", "Teléfono", "tel"],
                    ["correo", "Correo", "email"],
                    ["coordinador", "Coordinador", "text"]
                ]],
                ["Usuarios y educandos", [
                    ["formato_nombre", "Formato del nombre", "select"],
                    ["mostrar_educandos_sin_seccion", "Mostrar educandos sin sección", "checkbox"]
                ]],
                ["Archivos", [
                    ["tamano_maximo_archivos_mb", "Tamaño máximo de archivos (MB)", "number"],
                    ["formatos_entrega", "Formatos de entrega", "text"]
                ]],
                ["Seguridad de las cuentas", [
                    ["contrasena_longitud_minima", "Longitud mínima de las contraseñas", "number"],
                    ["sesion_inactividad_minutos", "Cerrar la sesión tras estos minutos sin actividad (0 = nunca)", "number"]
                ]],
                ["Conservación de datos", [
                    ["plazo_conservacion_anos", "Años que se conservan los datos de un educando dado de baja y las circulares de una ronda", "number"]
                ]]
            ];
            apartados.forEach(([nombre, campos]) => {
                seccion.appendChild(this.crearApartadoGeneral(nombre, campos, configuracion));
            });
            seccion.appendChild(this.crearApartadoMenu(configuracion.orden_menu));
            seccion.appendChild(this.crearApartadoListas(configuracion.catalogos || {
                tipos_evento: [], secciones: [], categorias_factura: []
            }));
            // Cada bloque solo aparece con su permiso; el servidor lo vuelve a comprobar.
            if (this.utilidades.tienePermiso("roles.gestionar")) {
                seccion.appendChild(this.crearBloqueRoles());
            }
            // Documentos base y plantillas (E-14); el formulario ya exige configuracion.gestionar.
            seccion.appendChild(
                new IngeriarDocumentos({utilidades: this.utilidades}).crearBloquePlantillas()
            );
            if (this.utilidades.tienePermiso("copia.descargar")) {
                seccion.appendChild(this.crearBloqueCopiasSeguridad());
                seccion.appendChild(this.crearBloqueCopia());
            }
            if (this.utilidades.tienePermiso("conservacion.gestionar")) {
                seccion.appendChild(this.crearBloqueVencidos());
                seccion.appendChild(this.crearBloqueRondasVencidas());
            }
            seccion.appendChild(this.crearBloqueInstalacion());
        });
    }

    crearCaja(texto) {
        const caja = document.createElement("div");
        caja.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = texto;
        caja.appendChild(titulo);
        return caja;
    }

    // Apartado de la configuración general: solo envía sus campos (el servidor conserva el resto).
    crearApartadoGeneral(nombre, campos, configuracion) {
        const caja = this.crearCaja(nombre);
        const controles = {};
        campos.forEach(([clave, texto, tipo]) => {
            const grupo = document.createElement("div");
            grupo.className = "campo";
            const etiqueta = document.createElement("label");
            etiqueta.textContent = texto;
            let entrada;
            if (tipo === "select") {
                entrada = document.createElement("select");
                const opciones = clave === "formato_nombre"
                    ? [["nombre_apellidos", "Nombre Apellidos"], ["apellidos_nombre", "Apellidos, Nombre"]]
                    : [];
                opciones.forEach(([valor, textoOpcion]) => {
                    const opcion = document.createElement("option");
                    opcion.value = valor;
                    opcion.textContent = textoOpcion;
                    entrada.appendChild(opcion);
                });
            } else {
                entrada = document.createElement("input");
                entrada.type = tipo;
            }
            entrada.name = clave;
            const id = "config-" + clave;
            entrada.id = id;
            etiqueta.htmlFor = id;
            if (tipo === "checkbox") {
                entrada.checked = Boolean(configuracion[clave]);
            } else if (tipo === "file") {
                entrada.accept = "image/jpeg,image/png,image/webp";
            } else {
                entrada.value = configuracion[clave] ?? "";
            }
            if (tipo === "number") {
                entrada.min = "0";
            }
            controles[clave] = entrada;
            grupo.append(etiqueta, entrada);
            caja.appendChild(grupo);
        });
        caja.appendChild(this.utilidades.crearAccionesGuardar(caja, () => {
            const datos = {};
            let logo = null;
            Object.entries(controles).forEach(([clave, control]) => {
                if (control.type === "file") {
                    logo = control.files[0] || null;
                } else if (control.type === "checkbox") {
                    datos[clave] = control.checked;
                } else {
                    datos[clave] = control.value;
                }
            });
            const formulario = new FormData();
            formulario.append("configuracion", JSON.stringify(datos));
            if (logo) {
                formulario.append("logo", logo);
            }
            return this.utilidades.enviarFormulario("api/superapi.php?ruta=configuracion", formulario)
                .then(() => this.cargar())
                .then(() => window.dispatchEvent(new CustomEvent("configuracion-actualizada")));
        }));
        return caja;
    }

    // Orden del menú lateral: las entradas actuales del menú, con flechas para moverlas.
    crearApartadoMenu(ordenGuardado) {
        const caja = this.crearCaja("Menú de navegación");
        const ayuda = document.createElement("p");
        ayuda.textContent = "Orden de las entradas del menú lateral para todos los usuarios " +
            "(cada uno solo ve las que permite su cargo).";
        const lista = document.createElement("ol");
        lista.className = "lista-orden-menu";
        const pintar = (claves) => {
            const enlaces = new Map(Array.from(document.querySelectorAll("#entidades > a[data-clave]"))
                .map((enlace) => [enlace.dataset.clave, enlace.textContent]));
            const orden = [...claves.filter((clave) => enlaces.has(clave)),
                ...Array.from(enlaces.keys()).filter((clave) => !claves.includes(clave))];
            lista.replaceChildren();
            orden.forEach((clave, indice) => {
                const fila = document.createElement("li");
                fila.className = "fila-orden-menu";
                fila.dataset.clave = clave;
                const nombre = document.createElement("span");
                nombre.textContent = enlaces.get(clave);
                const mover = (desplazamiento) => {
                    const claves = Array.from(lista.children).map((elemento) => elemento.dataset.clave);
                    const destino = indice + desplazamiento;
                    [claves[indice], claves[destino]] = [claves[destino], claves[indice]];
                    pintar(claves);
                    lista.dispatchEvent(new Event("change", {bubbles: true}));
                };
                const subir = document.createElement("button");
                subir.type = "button";
                subir.className = "boton boton-mini";
                subir.textContent = "↑";
                subir.title = "Subir " + nombre.textContent;
                subir.disabled = indice === 0;
                subir.onclick = () => mover(-1);
                const bajar = document.createElement("button");
                bajar.type = "button";
                bajar.className = "boton boton-mini";
                bajar.textContent = "↓";
                bajar.title = "Bajar " + nombre.textContent;
                bajar.disabled = indice === orden.length - 1;
                bajar.onclick = () => mover(1);
                fila.append(nombre, subir, bajar);
                lista.appendChild(fila);
            });
        };
        pintar(Array.isArray(ordenGuardado) && ordenGuardado.length
            ? ordenGuardado
            : IngeriarConfiguracion.ORDEN_MENU_PREDETERMINADO);
        const restablecer = document.createElement("button");
        restablecer.type = "button";
        restablecer.className = "boton boton-cancelar";
        restablecer.textContent = "Orden por defecto";
        restablecer.onclick = () => {
            pintar(IngeriarConfiguracion.ORDEN_MENU_PREDETERMINADO);
            lista.dispatchEvent(new Event("change", {bubbles: true}));
        };
        caja.append(ayuda, lista, restablecer);
        caja.appendChild(this.utilidades.crearAccionesGuardar(caja, () => {
            const datos = new FormData();
            datos.append("configuracion", JSON.stringify({
                orden_menu: Array.from(lista.children).map((fila) => fila.dataset.clave)
            }));
            return this.utilidades.enviarFormulario("api/superapi.php?ruta=configuracion", datos)
                .then(() => this.cargar())
                .then(() => window.dispatchEvent(new CustomEvent("configuracion-actualizada")));
        }));
        return caja;
    }

    // Listas de la aplicación: tipos de evento, secciones y categorías se guardan juntas.
    crearApartadoListas(catalogos) {
        const caja = this.crearCaja("Listas de la aplicación");
        const tipos = this.crearEditorCatalogo("Tipos de evento", catalogos.tipos_evento || [], "tipos");
        const secciones = this.crearEditorCatalogo("Secciones", catalogos.secciones || [], "secciones");
        const categorias = this.crearEditorCatalogo("Categorías de facturas", catalogos.categorias_factura || [], "categorias");
        caja.append(tipos, secciones, categorias);
        caja.appendChild(this.utilidades.crearAccionesGuardar(caja, () =>
            this.utilidades.enviarJson("api/superapi.php?ruta=catalogos", {
                tipos_evento: this.leerEditorCatalogo(tipos),
                secciones: this.leerEditorCatalogo(secciones),
                categorias_factura: this.leerEditorCatalogo(categorias)
            }).then(() => this.cargar())
                .then(() => window.dispatchEvent(new CustomEvent("configuracion-actualizada")))
        ));
        return caja;
    }

    // Acceso al instalador (E-18). Con datos ya instalados, el instalador exige la sesión de
    // un administrador y no borra nada: sirve para revisar o crear tablas que falten.
    crearBloqueInstalacion() {
        const caja = this.crearCaja("Instalación");
        const ayuda = document.createElement("p");
        ayuda.textContent = "El instalador crea las tablas y los datos iniciales en la base de datos " +
            "configurada en el servidor. Con la aplicación ya en uso solo puede abrirlo un administrador. " +
            "Antes de usarlo, descarga una copia de seguridad.";
        const enlace = document.createElement("a");
        enlace.className = "boton boton-editar";
        enlace.href = "instalador.html";
        enlace.target = "_blank";
        enlace.rel = "noopener";
        enlace.textContent = "Abrir el instalador";
        caja.append(ayuda, enlace);
        return caja;
    }

    // Cargos y permisos (E-13): una columna por cargo y una fila por permiso.
    // Un solo botón Guardar para todos (E-18). El administrador no se puede modificar.
    crearBloqueRoles() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Cargos y permisos";
        const ayuda = document.createElement("p");
        ayuda.textContent = "Cada usuario tiene un cargo básico (administrador, monitor o " +
            "tutor) y puede tener cargos adicionales. Sus permisos son la suma de los de " +
            "todos sus cargos. Los cambios se aplican en la siguiente acción de cada usuario.";
        const contenido = document.createElement("div");
        contenido.className = "contenedor-tabla-roles";
        contenido.textContent = "Cargando…";
        bloque.append(titulo, ayuda, contenido);

        const alta = document.createElement("div");
        const cargar = () => this.utilidades.obtenerJson("api/superapi.php?ruta=roles")
            .then((datos) => {
                contenido.replaceChildren(this.crearTablaRoles(datos));
                alta.replaceChildren(this.crearFormularioCargo(cargar));
            })
            .catch((error) => {
                contenido.textContent = error.message;
            });
        cargar();
        // Un solo Guardar para todos los cargos (el administrador no se modifica).
        bloque.append(alta, this.utilidades.crearAccionesGuardar(bloque, () => {
            const tabla = contenido.querySelector(".tabla-roles");
            const columnas = (tabla?.columnasCargos || [])
                .filter((columna) => columna.rol.codigo !== "administrador");
            return columnas.reduce((cadena, columna) => cadena.then(() => this.utilidades.enviarJson(
                "api/superapi.php?ruta=roles",
                {
                    codigo: columna.rol.codigo,
                    nombre: columna.nombre.value,
                    activo: columna.casillaActivo.checked,
                    orden: columna.rol.orden,
                    permisos: columna.casillas
                        .filter((casilla) => casilla.checked)
                        .map((casilla) => casilla.value)
                }
            )), Promise.resolve()).then(cargar);
        }));
        return bloque;
    }

    crearTablaRoles(datos) {
        const tabla = document.createElement("table");
        tabla.className = "tabla-roles";
        const cabecera = document.createElement("tr");
        const vacia = document.createElement("th");
        vacia.textContent = "Permiso";
        vacia.scope = "col";
        cabecera.appendChild(vacia);
        const columnas = datos.roles.map((rol) => {
            const celda = document.createElement("th");
            celda.scope = "col";
            const nombre = document.createElement("input");
            nombre.type = "text";
            nombre.value = rol.nombre;
            nombre.disabled = rol.codigo === "administrador";
            nombre.setAttribute("aria-label", "Nombre del cargo " + rol.codigo);
            const tipo = document.createElement("small");
            tipo.textContent = rol.basico ? "Básico" : "Adicional";
            const activo = document.createElement("label");
            const casillaActivo = document.createElement("input");
            casillaActivo.type = "checkbox";
            casillaActivo.checked = rol.activo;
            casillaActivo.disabled = rol.basico;
            activo.append(casillaActivo, " Activo");
            celda.append(nombre, tipo, activo);
            cabecera.appendChild(celda);
            return {rol, nombre, casillaActivo, casillas: []};
        });
        tabla.appendChild(cabecera);

        datos.permisos.forEach((permiso) => {
            const fila = document.createElement("tr");
            const descripcion = document.createElement("th");
            descripcion.scope = "row";
            descripcion.textContent = permiso.descripcion;
            const codigo = document.createElement("small");
            codigo.textContent = permiso.codigo;
            descripcion.append(document.createElement("br"), codigo);
            fila.appendChild(descripcion);
            columnas.forEach((columna) => {
                const celda = document.createElement("td");
                const casilla = document.createElement("input");
                casilla.type = "checkbox";
                casilla.value = permiso.codigo;
                casilla.checked = columna.rol.permisos.includes(permiso.codigo);
                casilla.disabled = columna.rol.codigo === "administrador";
                casilla.setAttribute(
                    "aria-label",
                    permiso.descripcion + " para " + columna.rol.nombre
                );
                columna.casillas.push(casilla);
                celda.appendChild(casilla);
                fila.appendChild(celda);
            });
            tabla.appendChild(fila);
        });

        tabla.columnasCargos = columnas;
        return tabla;
    }

    // Alta de un cargo adicional nuevo (los básicos son fijos).
    crearFormularioCargo(recargar) {
        const formulario = document.createElement("form");
        formulario.className = "formulario-cargo";
        // Crear un cargo es una acción propia: no cuenta como cambio sin guardar.
        formulario.dataset.sinCambios = "1";
        const codigo = document.createElement("input");
        codigo.placeholder = "código (p. ej. secretaria)";
        codigo.pattern = "[a-z][a-z0-9_]{1,49}";
        codigo.required = true;
        codigo.setAttribute("aria-label", "Código del cargo nuevo");
        const nombre = document.createElement("input");
        nombre.placeholder = "Nombre visible (p. ej. Secretaría)";
        nombre.required = true;
        nombre.setAttribute("aria-label", "Nombre del cargo nuevo");
        const crear = document.createElement("button");
        crear.type = "submit";
        crear.className = "boton";
        crear.textContent = "+ Nuevo cargo adicional";
        formulario.append(codigo, nombre, crear);
        formulario.onsubmit = (envio) => {
            envio.preventDefault();
            this.utilidades.enviarJson("api/superapi.php?ruta=roles", {
                codigo: codigo.value.trim(),
                nombre: nombre.value.trim(),
                activo: true,
                permisos: []
            }).then(recargar).catch((error) => alert(error.message));
        };
        return formulario;
    }

    // Datos vencidos (E-06): educandos de baja cuyo plazo de conservación ha pasado.
    // El servidor decide qué está vencido y solo borra eso.
    crearBloqueVencidos() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Datos vencidos";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.append(titulo, contenido);

        const crearCasilla = (nombre, valor, texto) => {
            const etiqueta = document.createElement("label");
            etiqueta.className = "casilla-vencido";
            const casilla = document.createElement("input");
            casilla.type = "checkbox";
            casilla.name = nombre;
            casilla.value = valor;
            casilla.checked = true;
            etiqueta.append(casilla, " " + texto);
            return etiqueta;
        };

        const cargar = () => this.utilidades.obtenerJson(
            "api/superapi.php?ruta=conservacion"
        ).then((datos) => {
            contenido.replaceChildren();
            const ayuda = document.createElement("p");
            ayuda.textContent = "Se borran definitivamente los educandos dados de baja " +
                "hace " + datos.plazo_anos + " años o más (baja anterior o igual al " +
                datos.fecha_limite + "), con sus fichas permanentes, entregas y asistencias.";
            contenido.appendChild(ayuda);
            if (datos.educandos.length === 0) {
                const vacio = document.createElement("p");
                vacio.className = "sin-datos";
                vacio.textContent = "No hay datos vencidos.";
                contenido.appendChild(vacio);
                return;
            }
            const lista = document.createElement("div");
            lista.className = "lista-vencidos";
            datos.educandos.forEach((educando) => {
                lista.appendChild(crearCasilla(
                    "educandos",
                    educando.id,
                    educando.nombre + " " + educando.apellidos +
                    " · baja " + educando.fecha_baja + " · " +
                    educando.archivos + " archivos"
                ));
            });
            contenido.appendChild(lista);
            if (datos.tutores_sin_educandos.length > 0) {
                const textoTutores = document.createElement("p");
                textoTutores.textContent = "Estos tutores se quedarían sin educandos. " +
                    "Marca los que quieras borrar también:";
                const listaTutores = document.createElement("div");
                listaTutores.className = "lista-vencidos";
                datos.tutores_sin_educandos.forEach((tutor) => {
                    const casilla = crearCasilla(
                        "tutores",
                        tutor.id,
                        tutor.nombre + " " + tutor.apellidos + " (" + tutor.email + ")"
                    );
                    casilla.querySelector("input").checked = false;
                    listaTutores.appendChild(casilla);
                });
                contenido.append(textoTutores, listaTutores);
            }
            const borrar = document.createElement("button");
            borrar.type = "button";
            borrar.className = "boton boton-borrar";
            borrar.textContent = "Eliminar definitivamente";
            borrar.onclick = () => {
                const marcados = (nombre) => Array.from(
                    contenido.querySelectorAll('input[name="' + nombre + '"]:checked')
                ).map((casilla) => Number(casilla.value));
                const seleccion = {
                    educandos: marcados("educandos"),
                    tutores: marcados("tutores")
                };
                if (seleccion.educandos.length + seleccion.tutores.length === 0) {
                    alert("No hay nada seleccionado.");
                    return;
                }
                if (!confirm("Se borrarán " + seleccion.educandos.length +
                    " educandos y " + seleccion.tutores.length +
                    " tutores con sus archivos. No se puede deshacer. ¿Continuar?")) {
                    return;
                }
                this.utilidades.enviarJson(
                    "api/superapi.php?ruta=conservacion",
                    seleccion
                ).then((resultado) => {
                    alert("Borrados: " + resultado.educandos + " educandos, " +
                        resultado.tutores + " tutores y " +
                        resultado.archivos + " archivos.");
                    return cargar();
                }).catch((error) => alert(error.message));
            };
            contenido.appendChild(borrar);
        }).catch((error) => {
            contenido.textContent = error.message;
        });

        cargar();
        return bloque;
    }

    // Rondas vencidas (E-16): se borran la carpeta de la ronda (circulares de todos los
    // educandos) y las respuestas de asistencia de sus eventos. Las fichas permanentes no.
    crearBloqueRondasVencidas() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Rondas vencidas";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.append(titulo, contenido);

        const cargar = () => this.utilidades.obtenerJson(
            "api/superapi.php?ruta=conservacion_rondas"
        ).then((datos) => {
            contenido.replaceChildren();
            const ayuda = document.createElement("p");
            ayuda.textContent = "Pasados " + datos.plazo_anos + " años desde el final de una ronda " +
                "(31 de agosto) se borran sus circulares y las respuestas de asistencia de sus " +
                "eventos, de todos los educandos. Las fichas permanentes no se borran aquí.";
            contenido.appendChild(ayuda);
            if (datos.rondas.length === 0) {
                const vacio = document.createElement("p");
                vacio.className = "sin-datos";
                vacio.textContent = "No hay rondas vencidas.";
                contenido.appendChild(vacio);
                return;
            }
            const lista = document.createElement("div");
            lista.className = "lista-vencidos";
            datos.rondas.forEach((ronda) => {
                const etiqueta = document.createElement("label");
                etiqueta.className = "casilla-vencido";
                const casilla = document.createElement("input");
                casilla.type = "checkbox";
                casilla.name = "rondas";
                casilla.value = ronda.ronda;
                casilla.checked = true;
                etiqueta.append(casilla, " Ronda " + ronda.ronda + " (" + ronda.desde + " a " +
                    ronda.hasta + ") · " + ronda.archivos + " archivos · " +
                    ronda.asistencias + " respuestas de asistencia");
                lista.appendChild(etiqueta);
            });
            const borrar = document.createElement("button");
            borrar.type = "button";
            borrar.className = "boton boton-borrar";
            borrar.textContent = "Eliminar rondas";
            borrar.onclick = () => {
                const rondas = Array.from(
                    contenido.querySelectorAll('input[name="rondas"]:checked')
                ).map((casilla) => casilla.value);
                if (rondas.length === 0) {
                    alert("No hay ninguna ronda seleccionada.");
                    return;
                }
                if (!confirm("Se borrarán las circulares y las asistencias de las rondas " +
                    rondas.join(", ") + ". No se puede deshacer. ¿Continuar?")) {
                    return;
                }
                this.utilidades.enviarJson(
                    "api/superapi.php?ruta=conservacion_rondas",
                    {rondas}
                ).then((resultado) => {
                    alert("Borradas las rondas " + resultado.rondas.join(", ") + ": " +
                        resultado.archivos + " archivos y " +
                        resultado.asistencias + " respuestas de asistencia.");
                    return cargar();
                }).catch((error) => alert(error.message));
            };
            contenido.append(lista, borrar);
        }).catch((error) => {
            contenido.textContent = error.message;
        });

        cargar();
        return bloque;
    }

    // Enlace de descarga de la copia de seguridad en JSON.
    // El servidor comprueba que el usuario es administrador.
    crearBloqueCopia() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Exportación de datos en JSON";
        const ayuda = document.createElement("p");
        ayuda.textContent =
            "Descarga todas las tablas en un archivo JSON (sin contraseñas ni archivos). " +
            "No sustituye a las copias de seguridad. Contiene datos personales.";
        const enlace = document.createElement("a");
        enlace.href = "servicios/salida.php";
        enlace.className = "boton boton-descargar";
        enlace.textContent = "Descargar JSON";
        bloque.append(titulo, ayuda, enlace);
        return bloque;
    }

    // Copias de seguridad completas (E-12): las genera server/copias/copia.php con cron.
    crearBloqueCopiasSeguridad() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Copias de seguridad";
        const ayuda = document.createElement("p");
        ayuda.textContent = "Base de datos y archivos (documentos, facturas, galería, " +
            "inventario) en un único archivo. Se generan automáticamente en el servidor; " +
            "aquí se descargan. Cada descarga queda registrada.";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.append(titulo, ayuda, contenido);

        this.utilidades.obtenerJson("api/copias.php?accion=listar").then((datos) => {
            contenido.replaceChildren();
            const aviso = (texto) => {
                const parrafo = document.createElement("p");
                parrafo.className = "aviso-copias";
                parrafo.textContent = texto;
                contenido.appendChild(parrafo);
            };
            if (!datos.carpeta_legible) {
                aviso("El servidor web no puede leer la carpeta de copias. Programa la copia con el mismo usuario que Apache.");
            }
            const ultima = datos.copias[0];
            if (!ultima) {
                aviso("Todavía no hay ninguna copia. Programa server/copias/copia.php con cron (ver noteas.md).");
            } else {
                const dias = (Date.now() - new Date(ultima.fecha.replace(" ", "T"))) / 86400000;
                if (dias > 7) {
                    aviso("La última copia tiene más de una semana: revisa que la tarea programada funcione.");
                }
                if (!ultima.cifrada) {
                    aviso("La última copia no está cifrada y contiene datos de menores. Define INGERIAR_COPIAS_CLAVE en el servidor.");
                }
            }
            const lista = document.createElement("ul");
            lista.className = "lista-copias";
            datos.copias.forEach((copia, indice) => {
                const elemento = document.createElement("li");
                const texto = document.createElement("span");
                texto.textContent = copia.fecha + " · " + (copia.tamano / 1048576).toFixed(1) + " MB · " +
                    (copia.cifrada ? "cifrada" : "sin cifrar");
                const enlace = document.createElement("a");
                enlace.className = "boton " + (indice === 0 ? "boton-descargar" : "boton-cancelar");
                enlace.href = "api/copias.php?accion=descargar&nombre=" + encodeURIComponent(copia.nombre);
                enlace.textContent = indice === 0 ? "Descargar la última" : "Descargar";
                elemento.append(texto, enlace);
                lista.appendChild(elemento);
            });
            contenido.appendChild(lista);
            if (datos.registro.length > 0) {
                const detalles = document.createElement("details");
                const resumen = document.createElement("summary");
                resumen.textContent = "Registro de las últimas ejecuciones";
                const registro = document.createElement("pre");
                registro.className = "registro-copias";
                registro.textContent = datos.registro.join("\n");
                detalles.append(resumen, registro);
                contenido.appendChild(detalles);
            }
        }).catch((error) => {
            contenido.textContent = error.message;
        });
        return bloque;
    }

    // Editor de un catálogo. Todas las filas (y la cabecera) usan la misma rejilla para
    // que las columnas queden alineadas. tipo: «tipos», «secciones» o «categorias».
    crearEditorCatalogo(titulo, elementos, tipo) {
        const columnas = {
            tipos: [["codigo", "Código", "text"], ["nombre", "Nombre visible", "text"], ["color", "Color", "color"],
                ["hora_inicio", "Empieza", "time"], ["hora_fin", "Termina", "time"],
                ["apertura_dias", "Abre (días antes)", "number"], ["cierre_horas", "Cierra (horas antes)", "number"]],
            secciones: [["codigo", "Código", "text"], ["nombre", "Nombre visible", "text"], ["color", "Color", "color"],
                ["edad_minima", "Edad mín.", "number"], ["edad_maxima", "Edad máx.", "number"]],
            categorias: [["codigo", "Código", "text"], ["nombre", "Nombre visible", "text"]]
        }[tipo];
        const bloque = document.createElement("section");
        bloque.className = "bloque-catalogo";
        const encabezado = document.createElement("h3");
        encabezado.className = "titulo-configuracion";
        encabezado.textContent = titulo;
        const lista = document.createElement("div");
        lista.className = "lista-catalogo";
        lista.dataset.tipo = tipo;
        const cabecera = document.createElement("div");
        cabecera.className = "fila-catalogo cabecera-catalogo";
        [...columnas.map(([, texto]) => texto), "Activo", ""].forEach((texto) => {
            const celda = document.createElement("span");
            celda.textContent = texto;
            cabecera.appendChild(celda);
        });
        lista.appendChild(cabecera);
        const añadir = (elemento = {}) => {
            const fila = document.createElement("div");
            fila.className = "fila-catalogo";
            columnas.forEach(([campo, texto, tipoCampo]) => {
                const control = document.createElement("input");
                control.type = tipoCampo;
                control.dataset.campo = campo;
                control.setAttribute("aria-label", texto);
                if (tipoCampo === "number") {
                    control.min = "0";
                    control.placeholder = campo === "apertura_dias" ? "Siempre"
                        : campo === "cierre_horas" ? "Al terminar" : "";
                }
                if (tipoCampo === "text") {
                    control.placeholder = texto;
                }
                control.value = elemento[campo] ?? (tipoCampo === "color" ? "#64748b" : "");
                fila.appendChild(control);
            });
            const activo = document.createElement("label");
            const casilla = document.createElement("input");
            casilla.type = "checkbox";
            casilla.dataset.campo = "activo";
            casilla.checked = elemento.activo === undefined ? true : Boolean(Number(elemento.activo) || elemento.activo === true);
            activo.append(casilla, " Activo");
            const quitar = document.createElement("button");
            quitar.type = "button";
            quitar.className = "boton boton-borrar";
            quitar.textContent = "Quitar";
            // Quitar archiva (activo = 0) para conservar el histórico.
            quitar.onclick = () => {
                casilla.checked = false;
                fila.hidden = true;
                casilla.dispatchEvent(new Event("change", {bubbles: true}));
            };
            fila.append(activo, quitar);
            lista.appendChild(fila);
        };
        elementos.forEach(añadir);
        const nuevo = document.createElement("button");
        nuevo.type = "button";
        nuevo.className = "boton boton-anadir-catalogo";
        nuevo.textContent = "+ Añadir";
        nuevo.onclick = () => añadir();
        bloque.append(encabezado, lista, nuevo);
        if (tipo === "tipos") {
            const ayuda = document.createElement("p");
            ayuda.className = "ayuda-catalogo";
            ayuda.textContent = "Empieza y Termina se proponen al crear un evento de ese tipo. Abre: cuántos días " +
                "antes del inicio pueden las familias empezar a responder y subir la circular (vacío: siempre). " +
                "Cierra: cuántas horas antes del inicio deja de poder responderse (vacío: hasta que termina). " +
                "Cada evento puede cambiar estos plazos.";
            bloque.appendChild(ayuda);
        }
        return bloque;
    }

    leerEditorCatalogo(bloque) {
        return Array.from(bloque.querySelectorAll(".fila-catalogo:not(.cabecera-catalogo)"))
            .map((fila, orden) => {
                const valor = (campo) => fila.querySelector(`[data-campo="${campo}"]`);
                const texto = (campo) => valor(campo) ? valor(campo).value.trim() : null;
                return {
                    codigo: texto("codigo"),
                    nombre: texto("nombre"),
                    color: texto("color"),
                    hora_inicio: texto("hora_inicio"),
                    hora_fin: texto("hora_fin"),
                    apertura_dias: texto("apertura_dias"),
                    cierre_horas: texto("cierre_horas"),
                    edad_minima: texto("edad_minima") || null,
                    edad_maxima: texto("edad_maxima") || null,
                    activo: valor("activo").checked,
                    orden
                };
            })
            .filter((elemento) => elemento.codigo && elemento.nombre);
    }
}
