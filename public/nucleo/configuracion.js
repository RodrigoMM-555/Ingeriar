class IngeriarConfiguracion {
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
        document.documentElement.dataset.secciones = JSON.stringify(
            configuracion.catalogos?.secciones || []
        );
        document.documentElement.dataset.categoriasFactura = JSON.stringify(
            configuracion.catalogos?.categorias_factura || []
        );
        const logo = document.querySelector("#logo-grupo");
        if (logo) {
            logo.hidden = !configuracion.logo_archivo;
            logo.src = configuracion.logo_archivo
                ? "api/superapi.php?ruta=configuracion_logo"
                : "";
        }
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

    mostrar() {
        return this.utilidades.obtenerJson(
            "api/superapi.php?ruta=configuracion"
        ).then((configuracion) => {
            let seccion = document.querySelector("main > section");
            if (!seccion) {
                throw new Error("No se encontró el contenedor principal");
            }
            seccion.className = "vista vista-configuracion";
            seccion.innerHTML = "";

            let titulo = document.createElement("h2");
            titulo.textContent = "Configuración";
            seccion.appendChild(titulo);

            // Cada apartado va en su propia caja (.caja-configuracion); el formulario
            // de datos generales agrupa varias cajas y un único botón Guardar.
            let formulario = document.createElement("form");
            formulario.className = "formulario-configuracion";

            const campos = [
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
                ["Asistencias y archivos", [
                    ["permitir_asistencia_pasada", "Permitir asistencia en eventos pasados", "checkbox"],
                    ["tamano_maximo_archivos_mb", "Tamaño máximo de archivos (MB)", "number"],
                    ["formatos_entrega", "Formatos de entrega", "text"]
                ]],
                ["Conservación de datos", [
                    ["plazo_conservacion_anos", "Años que se conservan los datos de un educando dado de baja", "number"]
                ]]
            ];
            const catalogos = configuracion.catalogos || {
                tipos_evento: [],
                secciones: [],
                categorias_factura: []
            };
            campos.forEach(([nombreGrupo, definiciones]) => {
                const caja = document.createElement("div");
                caja.className = "caja-configuracion vista-superficie";
                let grupoTitulo = document.createElement("h3");
                grupoTitulo.className = "titulo-configuracion";
                grupoTitulo.textContent = nombreGrupo;
                caja.appendChild(grupoTitulo);
                formulario.appendChild(caja);
                definiciones.forEach(([clave, texto, tipo]) => {
                let grupo = document.createElement("div");
                grupo.className = "campo";

                let etiqueta = document.createElement("label");
                etiqueta.textContent = texto;

                let entrada = document.createElement(
                    tipo === "textarea" ? "textarea" : "input"
                );
                entrada.name = clave;
                if (entrada.tagName === "INPUT") {
                    entrada.type = tipo;
                }
                if (tipo === "select") {
                    entrada = document.createElement("select");
                    entrada.name = clave;
                    const opciones = clave === "formato_nombre"
                        ? [["nombre_apellidos", "Nombre Apellidos"],
                            ["apellidos_nombre", "Apellidos, Nombre"]]
                        : [];
                    opciones.forEach(([valor, texto]) => {
                        let opcion = document.createElement("option");
                        opcion.value = valor;
                        opcion.textContent = texto;
                        entrada.appendChild(opcion);
                    });
                }
                if (tipo === "checkbox") {
                    entrada.checked = Boolean(configuracion[clave]);
                } else if (tipo !== "file") {
                    entrada.value = configuracion[clave] ?? "";
                }
                if (tipo === "file") {
                    entrada.accept = "image/jpeg,image/png,image/webp";
                }
                grupo.append(etiqueta, entrada);
                caja.appendChild(grupo);
                });
            });
            const cajaListas = document.createElement("div");
            cajaListas.className = "caja-configuracion vista-superficie";
            const tiposTitulo = document.createElement("h3");
            tiposTitulo.className = "titulo-configuracion";
            tiposTitulo.textContent = "Listas de la aplicación";
            cajaListas.appendChild(tiposTitulo);
            formulario.appendChild(cajaListas);
            const tipos = this.crearEditorCatalogo(
                "Tipos de evento", catalogos.tipos_evento, false
            );
            const secciones = this.crearEditorCatalogo(
                "Secciones", catalogos.secciones, true
            );
            const categoriasFactura = this.crearEditorCatalogo(
                "Categorías de facturas",
                catalogos.categorias_factura,
                false
            );
            cajaListas.append(tipos, secciones, categoriasFactura);

            // Botón fijo al pie: guarda todas las cajas del formulario.
            const acciones = document.createElement("div");
            acciones.className = "acciones-configuracion vista-superficie";
            const aviso = document.createElement("span");
            aviso.textContent = "Los apartados anteriores se guardan juntos.";
            let guardar = document.createElement("button");
            guardar.type = "submit";
            guardar.textContent = "Guardar configuración";
            guardar.className = "boton boton-crear";
            acciones.append(aviso, guardar);
            formulario.appendChild(acciones);

            formulario.onsubmit = (evento) => {
                evento.preventDefault();
                let datos = Object.fromEntries(
                    new FormData(evento.target).entries()
                );
                datos.modo_oscuro = evento.target.modo_oscuro.checked;
                datos.mostrar_educandos_sin_seccion =
                    evento.target.mostrar_educandos_sin_seccion.checked;
                datos.permitir_asistencia_pasada =
                    evento.target.permitir_asistencia_pasada.checked;
                const catalogosActualizados = {
                    tipos_evento: this.leerEditorCatalogo(tipos),
                    secciones: this.leerEditorCatalogo(secciones),
                    categorias_factura: this.leerEditorCatalogo(categoriasFactura)
                };
                this.guardar(
                    datos,
                    evento.target.logo_archivo.files[0],
                    catalogosActualizados
                );
            };

            seccion.appendChild(formulario);
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
            }
        });
    }

    // Cargos y permisos (E-13): una columna por cargo y una fila por permiso.
    // Cada columna se guarda por separado. El administrador no se puede modificar.
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

        const cargar = () => this.utilidades.obtenerJson("api/superapi.php?ruta=roles")
            .then((datos) => {
                contenido.replaceChildren(this.crearTablaRoles(datos, cargar));
                contenido.appendChild(this.crearFormularioCargo(cargar));
            })
            .catch((error) => {
                contenido.textContent = error.message;
            });
        cargar();
        return bloque;
    }

    crearTablaRoles(datos, recargar) {
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

        const filaGuardar = document.createElement("tr");
        filaGuardar.appendChild(document.createElement("th"));
        columnas.forEach((columna) => {
            const celda = document.createElement("td");
            if (columna.rol.codigo !== "administrador") {
                const guardar = document.createElement("button");
                guardar.type = "button";
                guardar.className = "boton boton-crear";
                guardar.textContent = "Guardar";
                guardar.onclick = () => this.utilidades.enviarJson(
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
                ).then(recargar).catch((error) => alert(error.message));
                celda.appendChild(guardar);
            }
            filaGuardar.appendChild(celda);
        });
        tabla.appendChild(filaGuardar);
        return tabla;
    }

    // Alta de un cargo adicional nuevo (los básicos son fijos).
    crearFormularioCargo(recargar) {
        const formulario = document.createElement("form");
        formulario.className = "formulario-cargo";
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
                datos.fecha_limite + "), con sus entregas y asistencias.";
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

    crearEditorCatalogo(titulo, elementos, incluyeEdades) {
        const bloque = document.createElement("section");
        bloque.className = "bloque-catalogo";
        const encabezado = document.createElement("h3");
        encabezado.className = "titulo-configuracion";
        encabezado.textContent = titulo;
        const lista = document.createElement("div");
        lista.className = "lista-catalogo";
        lista.dataset.edades = incluyeEdades ? "true" : "false";
        const añadir = (elemento = {}) => {
            const fila = document.createElement("div");
            fila.className = "fila-catalogo";
            fila.innerHTML = `
                <input data-campo="codigo" placeholder="Código interno">
                <input data-campo="nombre" placeholder="Nombre visible">
                ${incluyeEdades ? `
                    <input data-campo="edad_minima" type="number" min="0"
                        placeholder="Edad mín.">
                    <input data-campo="edad_maxima" type="number" min="0"
                        placeholder="Edad máx.">` : ""}
                <label><input data-campo="activo" type="checkbox"> Activo</label>
                <button type="button" class="boton boton-borrar">Quitar</button>`;
            Object.entries(elemento).forEach(([clave, valor]) => {
                const control = fila.querySelector(`[data-campo="${clave}"]`);
                if (!control) {
                    return;
                }
                if (control.type === "checkbox") {
                    control.checked = Boolean(Number(valor) || valor === true);
                } else {
                    control.value = valor ?? "";
                }
            });
            fila.querySelector(".boton-borrar").onclick = () => {
                fila.querySelector('[data-campo="activo"]').checked = false;
                fila.hidden = true;
            };
            lista.appendChild(fila);
        };
        elementos.forEach(añadir);
        const nuevo = document.createElement("button");
        nuevo.type = "button";
        nuevo.className = "boton";
        nuevo.textContent = "Añadir";
        nuevo.onclick = () => añadir();
        bloque.append(encabezado, lista, nuevo);
        return bloque;
    }

    crearCampoLista(clave, titulo, valor) {
        const grupo = document.createElement("div");
        grupo.className = "campo campo-lista-simple";
        const etiqueta = document.createElement("label");
        etiqueta.textContent = titulo;
        const ayuda = document.createElement("small");
        ayuda.textContent = "Separados por comas";
        const entrada = document.createElement("textarea");
        entrada.name = clave;
        entrada.value = valor || "";
        entrada.rows = 3;
        grupo.append(etiqueta, ayuda, entrada);
        return grupo;
    }

    leerEditorCatalogo(bloque) {
        return Array.from(bloque.querySelectorAll(".fila-catalogo"))
            .map((fila, orden) => {
                const valor = (campo) => fila.querySelector(
                    `[data-campo="${campo}"]`
                );
                return {
                    codigo: valor("codigo").value.trim(),
                    nombre: valor("nombre").value.trim(),
                    edad_minima: valor("edad_minima")?.value || null,
                    edad_maxima: valor("edad_maxima")?.value || null,
                    activo: valor("activo").checked,
                    orden
                };
            })
            .filter((elemento) => elemento.codigo && elemento.nombre);
    }

    guardar(datos, logo, catalogos) {
        const formulario = new FormData();
        formulario.append("configuracion", JSON.stringify(datos));
        if (logo) {
            formulario.append("logo", logo);
        }
        const guardarConfiguracion = fetch("api/superapi.php?ruta=configuracion", {
            method: "POST",
            body: formulario
        }).then((respuesta) => respuesta.json().then((resultado) => {
            if (!respuesta.ok) {
                throw new Error(resultado.error || "No se pudo guardar");
            }
            return resultado;
        }));
        return guardarConfiguracion.then((resultado) => {
            if (!resultado.ok) {
                throw new Error(resultado.error || "No se pudo guardar");
            }
            return fetch("api/superapi.php?ruta=catalogos", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(catalogos)
            }).then((respuesta) => respuesta.json().then((catalogosResultado) => {
                if (!respuesta.ok || !catalogosResultado.ok) {
                    throw new Error(
                        catalogosResultado.error || "No se pudieron guardar los catálogos"
                    );
                }
                datos.catalogos = catalogosResultado.catalogos;
                document.querySelector(this.nombre).textContent =
                    datos.nombre_grupo;
                this.aplicarConfiguracion(datos);
                window.dispatchEvent(new CustomEvent("configuracion-actualizada"));
                alert("Configuración guardada");
            }));
        });
    }
}
