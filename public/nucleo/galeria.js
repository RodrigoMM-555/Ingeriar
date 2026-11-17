// Galería de fotografías (E-03).
// Navegación por carpetas: ronda → tipo de evento → evento → fotos.
// Los permisos los decide el servidor (api/galeria.php); aquí solo se ocultan
// los botones que el usuario no puede usar.
class IngeriarGaleria {
    constructor({utilidades, contenedor}) {
        this.utilidades = utilidades;
        this.contenedor = contenedor;
        this.datos = {puede_editar: false, rondas: []};
        this.ruta = {ronda: null, tipo: null, evento: null};
        this.visor = null;
    }

    cargar() {
        return this.utilidades.obtenerJson("api/galeria.php?accion=listar")
            .then((datos) => {
                this.datos = datos;
                this.corregirRuta();
                this.render();
            });
    }

    // Si la carpeta abierta ya no existe (por ejemplo, tras borrar su última foto),
    // se vuelve al nivel superior que siga existiendo.
    corregirRuta() {
        if (this.ruta.ronda && !this.rondaActual()) {
            this.ruta = {ronda: null, tipo: null, evento: null};
        }
        if (this.ruta.tipo && !this.tipoActual()) {
            this.ruta.tipo = null;
            this.ruta.evento = null;
        }
        if (this.ruta.evento && !this.eventoActual()) {
            this.ruta.evento = null;
        }
    }

    rondaActual() {
        return this.datos.rondas.find((ronda) => ronda.codigo === this.ruta.ronda);
    }

    tipoActual() {
        return this.rondaActual()?.tipos.find((tipo) => tipo.codigo === this.ruta.tipo);
    }

    eventoActual() {
        return this.tipoActual()?.eventos.find((evento) => evento.id === this.ruta.evento);
    }

    contarFotos(elemento) {
        if (elemento.fotos) {
            return elemento.fotos.length;
        }
        const hijos = elemento.tipos || elemento.eventos || [];
        return hijos.reduce((total, hijo) => total + this.contarFotos(hijo), 0);
    }

    render() {
        this.utilidades.vaciarColaMiniaturas();
        const seccion = document.querySelector(this.contenedor);
        seccion.className = "vista vista-galeria";
        seccion.replaceChildren();

        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-galeria";
        const titulo = document.createElement("h2");
        titulo.textContent = "Galería";
        cabecera.appendChild(titulo);
        if (this.datos.puede_editar) {
            const subir = document.createElement("button");
            subir.type = "button";
            subir.className = "boton boton-crear";
            subir.textContent = "+ Subir fotos";
            subir.onclick = () => this.mostrarFormularioSubida();
            cabecera.appendChild(subir);
        }
        seccion.append(cabecera, this.crearMigas());

        const contenido = document.createElement("div");
        contenido.className = "contenido-galeria";
        seccion.appendChild(contenido);

        if (this.ruta.evento) {
            this.renderFotos(contenido, this.eventoActual());
        } else if (this.ruta.tipo) {
            this.renderCarpetas(contenido, this.tipoActual().eventos.map((evento) => ({
                nombre: evento.nombre,
                detalle: this.formatearFecha(evento.fecha_inicio) + " · " +
                    this.textoFotos(evento.fotos.length),
                abrir: () => this.abrir({evento: evento.id})
            })));
        } else if (this.ruta.ronda) {
            this.renderCarpetas(contenido, this.rondaActual().tipos.map((tipo) => ({
                nombre: tipo.nombre,
                detalle: tipo.eventos.length + (tipo.eventos.length === 1
                    ? " evento · " : " eventos · ") + this.textoFotos(this.contarFotos(tipo)),
                abrir: () => this.abrir({tipo: tipo.codigo})
            })));
        } else {
            this.renderCarpetas(contenido, this.datos.rondas.map((ronda) => ({
                nombre: ronda.nombre,
                detalle: this.textoFotos(this.contarFotos(ronda)),
                abrir: () => this.abrir({ronda: ronda.codigo})
            })));
        }
    }

    abrir(cambio) {
        Object.assign(this.ruta, cambio);
        this.render();
    }

    crearMigas() {
        const migas = document.createElement("nav");
        migas.className = "migas-galeria";
        migas.setAttribute("aria-label", "Ubicación en la galería");
        const niveles = [{texto: "Todas las rondas", ruta: {ronda: null, tipo: null, evento: null}}];
        if (this.ruta.ronda) {
            niveles.push({
                texto: this.rondaActual().nombre,
                ruta: {ronda: this.ruta.ronda, tipo: null, evento: null}
            });
        }
        if (this.ruta.tipo) {
            niveles.push({
                texto: this.tipoActual().nombre,
                ruta: {ronda: this.ruta.ronda, tipo: this.ruta.tipo, evento: null}
            });
        }
        if (this.ruta.evento) {
            niveles.push({texto: this.eventoActual().nombre, ruta: null});
        }
        niveles.forEach((nivel, indice) => {
            if (indice > 0) {
                const separador = document.createElement("span");
                separador.textContent = "›";
                separador.setAttribute("aria-hidden", "true");
                migas.appendChild(separador);
            }
            const ultimo = indice === niveles.length - 1;
            const elemento = document.createElement(ultimo ? "span" : "a");
            elemento.textContent = nivel.texto;
            if (!ultimo) {
                elemento.href = "#";
                elemento.onclick = (evento) => {
                    evento.preventDefault();
                    this.ruta = {...nivel.ruta};
                    this.render();
                };
            } else {
                elemento.setAttribute("aria-current", "page");
            }
            migas.appendChild(elemento);
        });
        return migas;
    }

    renderCarpetas(contenido, carpetas) {
        if (carpetas.length === 0) {
            const vacio = document.createElement("p");
            vacio.className = "sin-datos";
            vacio.textContent = this.datos.puede_editar
                ? "Todavía no hay fotos. Usa «Subir fotos» para añadir las primeras."
                : "Todavía no hay fotos de las secciones de tus educandos.";
            contenido.appendChild(vacio);
            return;
        }
        const rejilla = document.createElement("div");
        rejilla.className = "rejilla-carpetas";
        carpetas.forEach((carpeta) => {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "carpeta-galeria vista-superficie";
            const icono = document.createElement("span");
            icono.className = "icono-carpeta";
            icono.setAttribute("aria-hidden", "true");
            const nombre = document.createElement("strong");
            nombre.textContent = carpeta.nombre;
            const detalle = document.createElement("span");
            detalle.textContent = carpeta.detalle;
            boton.append(icono, nombre, detalle);
            boton.onclick = carpeta.abrir;
            rejilla.appendChild(boton);
        });
        contenido.appendChild(rejilla);
    }

    renderFotos(contenido, evento) {
        const rejilla = document.createElement("div");
        rejilla.className = "rejilla-fotos";
        evento.fotos.forEach((foto, indice) => {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "miniatura-foto";
            boton.setAttribute("aria-label", "Ver " + foto.nombre);
            boton.textContent = "Cargando…";
            boton.onclick = () => this.abrirVisor(evento, indice);
            rejilla.appendChild(boton);
            this.utilidades.encolarMiniatura(this.urlFoto(foto.id), boton, foto.nombre);
        });
        contenido.appendChild(rejilla);
    }

    urlFoto(id, descargar = false) {
        return "api/galeria.php?accion=ver&id=" + encodeURIComponent(id) +
            (descargar ? "&descargar=1" : "");
    }

    abrirVisor(evento, indice) {
        this.cerrarVisor();
        const ultimoFoco = document.activeElement;
        const visor = document.createElement("div");
        visor.className = "visor-galeria";
        visor.setAttribute("role", "dialog");
        visor.setAttribute("aria-modal", "true");
        visor.setAttribute("aria-label", "Visor de fotos");

        const imagen = document.createElement("img");
        const pie = document.createElement("div");
        pie.className = "pie-visor";
        const nombre = document.createElement("span");
        const descargar = document.createElement("a");
        descargar.className = "boton";
        descargar.textContent = "Descargar";
        pie.append(nombre, descargar);

        const borrar = document.createElement("button");
        if (this.datos.puede_editar) {
            borrar.type = "button";
            borrar.className = "boton boton-borrar";
            borrar.textContent = "Borrar";
            pie.appendChild(borrar);
        }

        const anterior = this.crearBotonVisor("‹", "Foto anterior", "visor-anterior");
        const siguiente = this.crearBotonVisor("›", "Foto siguiente", "visor-siguiente");
        const cerrar = this.crearBotonVisor("×", "Cerrar visor", "visor-cerrar");

        const mostrar = (nuevoIndice) => {
            indice = (nuevoIndice + evento.fotos.length) % evento.fotos.length;
            const foto = evento.fotos[indice];
            imagen.src = this.urlFoto(foto.id);
            imagen.alt = foto.nombre;
            nombre.textContent = foto.nombre + " (" + (indice + 1) + " de " +
                evento.fotos.length + ")";
            descargar.href = this.urlFoto(foto.id, true);
            anterior.hidden = siguiente.hidden = evento.fotos.length < 2;
        };

        anterior.onclick = () => mostrar(indice - 1);
        siguiente.onclick = () => mostrar(indice + 1);
        cerrar.onclick = () => this.cerrarVisor();
        borrar.onclick = () => this.borrarFoto(evento.fotos[indice]);
        visor.onclick = (clic) => {
            if (clic.target === visor) {
                this.cerrarVisor();
            }
        };
        const teclado = (tecla) => {
            if (tecla.key === "Escape") {
                this.cerrarVisor();
            } else if (tecla.key === "ArrowLeft") {
                mostrar(indice - 1);
            } else if (tecla.key === "ArrowRight") {
                mostrar(indice + 1);
            }
        };
        document.addEventListener("keydown", teclado);

        visor.append(cerrar, anterior, imagen, siguiente, pie);
        document.body.appendChild(visor);
        document.body.classList.add("modal-abierto");
        this.visor = {elemento: visor, teclado, ultimoFoco};
        mostrar(indice);
        cerrar.focus();
    }

    crearBotonVisor(texto, etiqueta, clase) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton-visor " + clase;
        boton.textContent = texto;
        boton.setAttribute("aria-label", etiqueta);
        return boton;
    }

    cerrarVisor() {
        if (!this.visor) {
            return;
        }
        document.removeEventListener("keydown", this.visor.teclado);
        this.visor.elemento.remove();
        document.body.classList.remove("modal-abierto");
        if (this.visor.ultimoFoco instanceof HTMLElement) {
            this.visor.ultimoFoco.focus();
        }
        this.visor = null;
    }

    borrarFoto(foto) {
        if (!confirm("¿Borrar «" + foto.nombre + "»? No se puede deshacer.")) {
            return;
        }
        const datos = new FormData();
        datos.append("id", foto.id);
        this.utilidades.enviarFormulario("api/galeria.php?accion=borrar", datos)
            .then(() => {
                this.cerrarVisor();
                return this.cargar();
            })
            .catch((error) => alert(error.message));
    }

    mostrarFormularioSubida() {
        return this.utilidades.obtenerJson("api/superapi.php?ruta=tabla&tabla=eventos")
            .then((datos) => {
                const modal = document.querySelector("#modal-formulario");
                const contenedor = document.querySelector("#contenedor-formulario");
                contenedor.replaceChildren();

                const titulo = document.createElement("h2");
                titulo.id = "modal-formulario-titulo";
                titulo.textContent = "Subir fotos";

                const formulario = document.createElement("form");
                formulario.className = "formulario vista-superficie vista-formulario";

                const campoEvento = document.createElement("div");
                campoEvento.className = "campo";
                const etiquetaEvento = document.createElement("label");
                etiquetaEvento.textContent = "Evento";
                etiquetaEvento.htmlFor = "galeria-evento";
                const selector = document.createElement("select");
                selector.id = "galeria-evento";
                selector.name = "evento";
                selector.required = true;
                const eventos = (datos.registros || []).slice().sort((a, b) =>
                    String(b.fecha_inicio).localeCompare(String(a.fecha_inicio))
                );
                eventos.forEach((evento) => {
                    const opcion = document.createElement("option");
                    opcion.value = evento.id;
                    opcion.textContent = this.formatearFecha(evento.fecha_inicio) +
                        " · " + evento.nombre;
                    selector.appendChild(opcion);
                });
                if (this.ruta.evento) {
                    selector.value = String(this.ruta.evento);
                }
                campoEvento.append(etiquetaEvento, selector);

                const campoFotos = document.createElement("div");
                campoFotos.className = "campo";
                const etiquetaFotos = document.createElement("label");
                etiquetaFotos.textContent = "Fotos (JPEG, PNG o WebP)";
                etiquetaFotos.htmlFor = "galeria-fotos";
                const fotos = document.createElement("input");
                fotos.type = "file";
                fotos.id = "galeria-fotos";
                fotos.name = "fotos[]";
                fotos.multiple = true;
                fotos.required = true;
                fotos.accept = "image/jpeg,image/png,image/webp";
                const ayuda = document.createElement("small");
                ayuda.textContent = "La galería se ordena sola por ronda y tipo de evento.";
                campoFotos.append(etiquetaFotos, fotos, ayuda);

                const acciones = document.createElement("div");
                acciones.className = "acciones-formulario";
                const guardar = document.createElement("button");
                guardar.type = "submit";
                guardar.className = "boton boton-crear";
                guardar.textContent = "Subir";
                const cancelar = document.createElement("button");
                cancelar.type = "button";
                cancelar.className = "boton boton-cancelar";
                cancelar.textContent = "Cancelar";
                cancelar.onclick = () => this.cerrarModal();
                acciones.append(guardar, cancelar);

                formulario.append(campoEvento, campoFotos, acciones);
                formulario.onsubmit = (envio) => {
                    envio.preventDefault();
                    guardar.disabled = true;
                    guardar.textContent = "Subiendo…";
                    this.utilidades.enviarFormulario(
                        "api/galeria.php?accion=subir",
                        new FormData(formulario)
                    ).then(() => {
                        this.cerrarModal();
                        return this.cargar();
                    }).catch((error) => {
                        alert(error.message);
                        guardar.disabled = false;
                        guardar.textContent = "Subir";
                    });
                };

                contenedor.append(titulo, formulario);
                modal.hidden = false;
                document.body.classList.add("modal-abierto");
                selector.focus();
            })
            .catch((error) => alert(error.message));
    }

    cerrarModal() {
        document.querySelector("#modal-formulario").hidden = true;
        document.querySelector("#contenedor-formulario").replaceChildren();
        document.body.classList.remove("modal-abierto");
    }

    formatearFecha(fecha) {
        const [ano, mes, dia] = String(fecha).substring(0, 10).split("-");
        return dia + "/" + mes + "/" + ano;
    }

    textoFotos(cantidad) {
        return cantidad + (cantidad === 1 ? " foto" : " fotos");
    }
}
