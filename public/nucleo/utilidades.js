class IngeriarUtilidades {
    // ¿Tiene el usuario actual este permiso? (E-13). Solo sirve para mostrar u ocultar
    // botones: el servidor vuelve a comprobarlo siempre.
    tienePermiso(codigo) {
        return typeof usuarioActual !== "undefined" &&
            Boolean(usuarioActual?.permisos?.includes(codigo));
    }

    // ---------- Miniaturas en segundo plano ----------
    // Galería, facturas y circulares usan un grupo fijo de Web Workers (hilos aparte) que se
    // crean una vez y se reutilizan: varias miniaturas se generan a la vez sin parar la
    // página. Solo entran en la cola las que están en pantalla o a punto de entrar
    // (IntersectionObserver), así primero se ve lo que se está mirando. Al cambiar de
    // vista se vacía la cola y se libera la memoria de las miniaturas anteriores.

    // Tantos trabajadores como núcleos, entre 2 y 4.
    iniciarTrabajadores() {
        if (this.trabajadores) {
            return;
        }
        this.colaMiniaturas ??= [];
        this.tareasEnCurso = new Map();
        this.siguienteTarea = 1;
        const cantidad = Math.max(2, Math.min(4, navigator.hardwareConcurrency || 2));
        this.trabajadores = Array.from({length: cantidad}, () => {
            const trabajador = new Worker("nucleo/documento-preview-worker.js");
            trabajador.tarea = null;
            trabajador.onmessage = (evento) => this.terminarTarea(trabajador, evento.data);
            trabajador.onerror = (evento) => {
                evento.preventDefault();
                this.terminarTarea(trabajador, {id: trabajador.tarea, kind: "error"});
            };
            return trabajador;
        });
    }

    vaciarColaMiniaturas() {
        this.colaMiniaturas = [];
        this.observadorMiniaturas?.disconnect();
        (this.urlsMiniaturas || []).forEach((url) => URL.revokeObjectURL(url));
        this.urlsMiniaturas = [];
    }

    // Pide la miniatura de «url» para «contenedor». «sinVista»: texto si no se puede generar.
    encolarMiniatura(url, contenedor, nombre, sinVista = "Sin vista previa") {
        // El Web Worker resuelve las rutas relativas desde su propia carpeta (nucleo/),
        // así que se le pasa la dirección completa.
        const tarea = {url: new URL(url, document.baseURI).href, contenedor, nombre, sinVista};
        if (!window.Worker) {
            this.mostrarMiniatura(tarea, tarea.url);
            return;
        }
        this.iniciarTrabajadores();
        if (!window.IntersectionObserver) {
            this.colaMiniaturas.push(tarea);
            setTimeout(() => this.procesarCola(), 0);
            return;
        }
        // Entra en la cola cuando está a menos de 400 px de verse.
        this.observadorMiniaturas ??= new IntersectionObserver((entradas) => {
            entradas.filter((entrada) => entrada.isIntersecting).forEach((entrada) => {
                this.observadorMiniaturas.unobserve(entrada.target);
                const pendiente = entrada.target.tareaMiniatura;
                delete entrada.target.tareaMiniatura;
                if (pendiente) {
                    this.colaMiniaturas.push(pendiente);
                }
            });
            this.procesarCola();
        }, {rootMargin: "400px"});
        contenedor.tareaMiniatura = tarea;
        this.observadorMiniaturas.observe(contenedor);
    }

    // Reparte la cola entre los trabajadores libres (cada uno, una miniatura a la vez).
    procesarCola() {
        for (const trabajador of this.trabajadores || []) {
            while (trabajador.tarea === null && this.colaMiniaturas.length > 0) {
                const tarea = this.colaMiniaturas.shift();
                if (!tarea.contenedor.isConnected) {
                    continue;
                }
                const id = this.siguienteTarea++;
                this.tareasEnCurso.set(id, tarea);
                trabajador.tarea = id;
                trabajador.postMessage({id, url: tarea.url});
            }
        }
    }

    terminarTarea(trabajador, resultado) {
        const tarea = this.tareasEnCurso.get(resultado.id);
        this.tareasEnCurso.delete(resultado.id);
        trabajador.tarea = null;
        // Si mientras tanto se ha cambiado de vista, el resultado se descarta.
        if (tarea && tarea.contenedor.isConnected) {
            if (resultado.kind === "image") {
                const url = URL.createObjectURL(resultado.blob);
                (this.urlsMiniaturas ??= []).push(url);
                this.mostrarMiniatura(tarea, url);
            } else {
                tarea.contenedor.textContent = tarea.sinVista;
            }
        }
        this.procesarCola();
    }

    mostrarMiniatura({contenedor, nombre}, origen) {
        const imagen = document.createElement("img");
        imagen.src = origen;
        imagen.alt = nombre;
        imagen.decoding = "async";
        imagen.loading = "lazy";
        contenedor.replaceChildren(imagen);
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
