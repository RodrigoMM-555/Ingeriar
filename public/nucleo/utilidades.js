class IngeriarUtilidades {
    // ¿Tiene el usuario actual este permiso? (E-13). Solo sirve para mostrar u ocultar
    // botones: el servidor vuelve a comprobarlo siempre.
    tienePermiso(codigo) {
        return typeof usuarioActual !== "undefined" &&
            Boolean(usuarioActual?.permisos?.includes(codigo));
    }

    // Miniaturas de imágenes (galería y facturas). Se generan con el Web Worker de
    // vistas previas para no bloquear la interfaz, con un máximo de trabajadores a la vez.
    vaciarColaMiniaturas() {
        this.colaMiniaturas = [];
    }

    encolarMiniatura(url, contenedor, nombre) {
        this.colaMiniaturas ??= [];
        this.trabajadoresActivos ??= 0;
        // El Web Worker resuelve las rutas relativas desde su propia carpeta (nucleo/),
        // así que se le pasa la dirección completa.
        const direccion = new URL(url, document.baseURI).href;
        this.colaMiniaturas.push({url: direccion, contenedor, nombre});
        // Se espera a que la vista termine de añadir los elementos a la página:
        // los que no están en el documento se descartan.
        setTimeout(() => this.procesarCola(), 0);
    }

    procesarCola() {
        while (this.trabajadoresActivos < 4 &&
            this.colaMiniaturas.length > 0) {
            const tarea = this.colaMiniaturas.shift();
            if (!tarea.contenedor.isConnected) {
                continue;
            }
            this.trabajadoresActivos++;
            this.crearMiniatura(tarea).finally(() => {
                this.trabajadoresActivos--;
                this.procesarCola();
            });
        }
    }

    crearMiniatura({url, contenedor, nombre}) {
        return new Promise((resolver) => {
            const mostrarImagen = (origen) => {
                const imagen = document.createElement("img");
                imagen.src = origen;
                imagen.alt = nombre;
                imagen.loading = "lazy";
                contenedor.replaceChildren(imagen);
            };
            if (!window.Worker) {
                mostrarImagen(url);
                resolver();
                return;
            }
            const trabajador = new Worker("nucleo/documento-preview-worker.js");
            trabajador.onmessage = (evento) => {
                trabajador.terminate();
                if (evento.data.kind === "image") {
                    mostrarImagen(URL.createObjectURL(evento.data.blob));
                } else {
                    contenedor.textContent = "Sin vista previa";
                }
                resolver();
            };
            trabajador.onerror = () => {
                trabajador.terminate();
                contenedor.textContent = "Sin vista previa";
                resolver();
            };
            trabajador.postMessage({url});
        });
    }

    // Nombres de los cargos del usuario actual, por ejemplo «Monitor · Tesorería».
    nombreCargos(usuario) {
        return (usuario?.roles || []).map((rol) => rol.nombre).join(" · ");
    }

    capitalizar(texto) {
        return String(texto)
            .replaceAll("_", " ")
            .toLowerCase()
            .replace(/\b\w/g, function (letra) {
                return letra.toUpperCase();
            });
    }

    limiteTexto(tipo) {
        let coincidencia = String(tipo).match(
            /^(?:VARCHAR|CHAR)\s*\(\s*(\d+)\s*\)/i
        );
        return coincidencia === null ? null : Number(coincidencia[1]);
    }

    // Si la sesión se ha cerrado por inactividad (E-18), se vuelve a la pantalla de acceso.
    comprobarSesion(respuesta, datos) {
        if (respuesta.status === 401 && datos && datos.sesion_caducada) {
            window.location.href = "login.html?caducada=1";
        }
    }

    obtenerJson(url) {
        return fetch(url).then((respuesta) => {
            return respuesta.json().catch(() => ({})).then((datos) => {
                this.comprobarSesion(respuesta, datos);
                if (!respuesta.ok) {
                    throw new Error(
                        datos.error || "No se pudo obtener la información"
                    );
                }
                return datos;
            });
        });
    }

    enviarFormulario(url, datos) {
        return fetch(url, {
            method: "POST",
            body: datos
        }).then((respuesta) => {
            return respuesta.json().then((resultado) => {
                this.comprobarSesion(respuesta, resultado);
                if (!respuesta.ok || resultado.error) {
                    throw new Error(
                        resultado.error || "No se pudo completar la operación"
                    );
                }
                return resultado;
            });
        });
    }

    enviarJson(url, datos) {
        return fetch(url, {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(datos)
        }).then((respuesta) => {
            return respuesta.json().then((resultado) => {
                this.comprobarSesion(respuesta, resultado);
                if (!respuesta.ok || resultado.error) {
                    throw new Error(
                        resultado.error || "No se pudo completar la operación"
                    );
                }
                return resultado;
            });
        });
    }

    // Clase CSS del color de un tipo de evento: «reunión de familias» → cal-evento-reunion-de-familias.
    claseTipoEvento(codigo) {
        return "cal-evento-" + (String(codigo || "otro")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-|-$/g, "") || "otro");
    }

    // Pie de un apartado de Configuración con su botón Guardar (E-18). Marca el apartado
    // al cambiar algo («Cambios sin guardar») y lo desmarca al guardar. Los campos dentro de
    // un elemento con data-sin-cambios (por ejemplo, el alta de un cargo) no cuentan.
    crearAccionesGuardar(caja, alGuardar, texto = "Guardar") {
        const acciones = document.createElement("div");
        acciones.className = "acciones-caja";
        const aviso = document.createElement("span");
        aviso.className = "aviso-cambios";
        aviso.textContent = "Cambios sin guardar";
        aviso.hidden = true;
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton boton-crear";
        boton.textContent = texto;
        acciones.append(aviso, boton);
        const marcar = (evento) => {
            if (!evento.target.closest("[data-sin-cambios]")) {
                caja.dataset.cambios = "1";
                aviso.hidden = false;
            }
        };
        caja.addEventListener("input", marcar);
        caja.addEventListener("change", marcar);
        boton.onclick = () => {
            boton.disabled = true;
            Promise.resolve()
                .then(alGuardar)
                .then(() => {
                    delete caja.dataset.cambios;
                    aviso.hidden = true;
                })
                .catch((error) => alert(error.message))
                .finally(() => {
                    boton.disabled = false;
                });
        };
        return acciones;
    }
}

const utilidades = new IngeriarUtilidades();
