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
        this.colaMiniaturas.push({url, contenedor, nombre});
        this.procesarCola();
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

    obtenerJson(url) {
        return fetch(url).then(function (respuesta) {
            return respuesta.json().catch(() => ({})).then((datos) => {
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
        }).then(function (respuesta) {
            return respuesta.json().then(function (resultado) {
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
        }).then(function (respuesta) {
            return respuesta.json().then(function (resultado) {
                if (!respuesta.ok || resultado.error) {
                    throw new Error(
                        resultado.error || "No se pudo completar la operación"
                    );
                }
                return resultado;
            });
        });
    }
}

const utilidades = new IngeriarUtilidades();
