// Documentación de inscripción (E-14).
// - En la ficha de cada educando: plantillas en blanco, estado de cada documento,
//   entrega (tutor o monitor) y revisión (monitor).
// - En Configuración: gestión de los documentos base y sus plantillas.
// El servidor (api/documentos.php) comprueba siempre los permisos.
class IngeriarDocumentos {
    constructor({utilidades}) {
        this.utilidades = utilidades;
    }

    // Bloque para el modal de documentación de un educando.
    // Fichas de un educando. «alResumir» recibe el texto del resumen (para la pestaña).
    crearBloqueInscripcion(educando, alResumir = null) {
        const bloque = document.createElement("section");
        bloque.className = "bloque-inscripcion";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.appendChild(contenido);

        const cargar = () => this.utilidades.obtenerJson(
            "api/documentos.php?accion=educando&id=" + encodeURIComponent(educando.id)
        ).then((datos) => {
            const obligatorios = datos.documentos.filter((documento) => documento.obligatorio);
            const entregados = obligatorios.filter((documento) => documento.entrega).length;
            if (alResumir) {
                alResumir(entregados + "/" + obligatorios.length);
            }
            contenido.replaceChildren(
                this.crearResumenInscripcion(datos, entregados, obligatorios.length),
                this.crearLeyendaIconos(datos),
                this.crearTablaInscripcion(educando, datos, cargar)
            );
        }).catch((error) => {
            contenido.textContent = error.message;
        });
        cargar();
        return bloque;
    }

    crearResumenInscripcion(datos, entregados, obligatorios) {
        const contar = (estado) => datos.documentos.filter((documento) => documento.entrega?.estado === estado).length;
        const partes = [entregados + " de " + obligatorios + " obligatorias entregadas"];
        if (contar("pendiente")) {
            partes.push(contar("pendiente") + " por revisar");
        }
        if (contar("corregir")) {
            partes.push(contar("corregir") + " a corregir");
        }
        return this.parrafo(partes.join(" · "), "resumen-inscripcion");
    }

    // Leyenda con los iconos que puede usar este usuario.
    crearLeyendaIconos(datos) {
        const leyenda = document.createElement("div");
        leyenda.className = "leyenda-iconos";
        this.accionesVisibles(datos).forEach((accion) => {
            const elemento = document.createElement("span");
            elemento.append(this.icono(accion.icono), " " + accion.texto);
            leyenda.appendChild(elemento);
        });
        return leyenda;
    }

    // Columnas de iconos de esta ventana: las mismas en todas las filas para que queden alineadas.
    accionesVisibles(datos) {
        return [
            {clave: "plantilla", icono: "descargar", texto: "Plantilla en blanco"},
            {clave: "ver", icono: "ver", texto: "Ver entregado"},
            ...(datos.puede_subir ? [{clave: "subir", icono: "subir", texto: "Subir o sustituir"}] : []),
            ...(datos.puede_revisar ? [
                {clave: "correcto", icono: "correcto", texto: "Correcto"},
                {clave: "corregir", icono: "corregir", texto: "Pedir corrección"}
            ] : [])
        ];
    }

    crearTablaInscripcion(educando, datos, recargar) {
        if (datos.documentos.length === 0) {
            return this.parrafo("No hay documentos de inscripción configurados.", "sin-datos");
        }
        const acciones = this.accionesVisibles(datos);
        const lista = document.createElement("div");
        lista.className = "lista-inscripcion";
        lista.style.setProperty("--iconos-inscripcion", String(acciones.length));
        datos.documentos.forEach((documento) => {
            const fila = document.createElement("article");
            fila.className = "fila-inscripcion vista-superficie";

            const nombre = document.createElement("div");
            nombre.className = "nombre-inscripcion";
            const titulo = document.createElement("strong");
            titulo.textContent = documento.nombre;
            nombre.appendChild(titulo);
            if (!documento.obligatorio) {
                nombre.appendChild(this.parrafo("Opcional", "detalle-inscripcion"));
            }

            // Cada columna de iconos tiene siempre su hueco, aunque la acción no aplique.
            const iconos = document.createElement("div");
            iconos.className = "iconos-inscripcion";
            const entrega = documento.entrega;
            acciones.forEach((accion) => {
                let elemento = null;
                if (accion.clave === "plantilla" && documento.tiene_plantilla) {
                    elemento = this.enlaceIcono("descargar", "Descargar en blanco: " + documento.nombre,
                        "api/documentos.php?accion=plantilla&codigo=" + encodeURIComponent(documento.codigo));
                } else if (accion.clave === "ver" && entrega) {
                    elemento = this.enlaceIcono("ver", "Ver entregado: " + documento.nombre,
                        "api/documentos.php?accion=ver&id=" + entrega.id);
                    elemento.target = "_blank";
                    elemento.rel = "noopener";
                } else if (accion.clave === "subir") {
                    elemento = this.crearBotonSubir(educando, documento, recargar);
                } else if (accion.clave === "correcto" && entrega) {
                    elemento = this.crearBotonRevisar(documento, "revisado", recargar);
                } else if (accion.clave === "corregir" && entrega) {
                    elemento = this.crearBotonRevisar(documento, "corregir", recargar);
                }
                if (elemento === null) {
                    elemento = document.createElement("span");
                    elemento.className = "hueco-icono";
                }
                iconos.appendChild(elemento);
            });
            fila.append(nombre, this.crearEstado(entrega), iconos);
            lista.appendChild(fila);
        });
        return lista;
    }

    crearEstado(entrega) {
        const estado = document.createElement("div");
        estado.className = "estado-inscripcion";
        const etiqueta = document.createElement("span");
        const textos = {pendiente: "Por revisar", revisado: "Revisado", corregir: "A corregir"};
        const clave = entrega ? entrega.estado : "falta";
        etiqueta.className = "etiqueta-estado estado-" + clave;
        etiqueta.textContent = entrega ? (textos[entrega.estado] || entrega.estado) : "Sin entregar";
        estado.appendChild(etiqueta);
        if (entrega) {
            const detalles = {
                pendiente: "Entregado el " + this.formatearFecha(entrega.fecha_subida),
                revisado: (entrega.revisado_por || "Un monitor") + " · " + this.formatearFecha(entrega.revisado_en),
                corregir: entrega.observaciones || ""
            };
            estado.appendChild(this.parrafo(detalles[entrega.estado] || "", "detalle-inscripcion"));
        }
        return estado;
    }

    // Iconos dibujados en SVG (iguales en todos los navegadores, sin archivos externos).
    icono(nombre) {
        const trazos = {
            descargar: ["M12 4v11", "M7 10l5 5 5-5", "M5 20h14"],
            ver: ["M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z", "M12 9.2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 1 0 0-5.6z"],
            subir: ["M12 20V9", "M7 14l5-5 5 5", "M5 4h14"],
            correcto: ["M5 12.5l4.5 4.5L19 7.5"],
            corregir: ["M4 20h4L19 9l-4-4L4 16v4z", "M13.5 6.5l4 4"]
        };
        const espacio = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(espacio, "svg");
        svg.setAttribute("viewBox", "0 0 24 24");
        svg.setAttribute("aria-hidden", "true");
        svg.classList.add("icono");
        (trazos[nombre] || []).forEach((trazo) => {
            const camino = document.createElementNS(espacio, "path");
            camino.setAttribute("d", trazo);
            svg.appendChild(camino);
        });
        return svg;
    }

    enlaceIcono(icono, texto, url) {
        const enlace = document.createElement("a");
        enlace.className = "boton-icono icono-" + icono;
        enlace.href = url;
        enlace.title = texto;
        enlace.setAttribute("aria-label", texto);
        enlace.appendChild(this.icono(icono));
        return enlace;
    }

    parrafo(texto, clase) {
        const parrafo = document.createElement("p");
        parrafo.className = clase;
        parrafo.textContent = texto;
        return parrafo;
    }

    // Botón de subida: abre el selector de archivos y entrega en cuanto se elige uno.
    crearBotonSubir(educando, documento, recargar) {
        const etiqueta = document.createElement("label");
        etiqueta.className = "boton-icono icono-subir boton-archivo";
        const texto = (documento.entrega ? "Sustituir: " : "Subir: ") + documento.nombre;
        etiqueta.title = texto;
        etiqueta.appendChild(this.icono("subir"));
        const archivo = document.createElement("input");
        archivo.type = "file";
        archivo.accept = ".pdf,.jpg,.jpeg,.png,.docx";
        archivo.setAttribute("aria-label", texto);
        archivo.onchange = () => {
            if (!archivo.files[0]) {
                return;
            }
            const datos = new FormData();
            datos.append("id_educando", educando.id);
            datos.append("codigo", documento.codigo);
            datos.append("archivo", archivo.files[0]);
            etiqueta.classList.add("cargando");
            this.utilidades.enviarFormulario("api/documentos.php?accion=subir", datos)
                .then(recargar)
                .catch((error) => {
                    alert(error.message);
                    etiqueta.classList.remove("cargando");
                });
        };
        etiqueta.appendChild(archivo);
        return etiqueta;
    }

    // Correcto o pedir corrección. El icono del estado actual queda marcado.
    crearBotonRevisar(documento, estado, recargar) {
        const icono = estado === "revisado" ? "correcto" : "corregir";
        const texto = (estado === "revisado" ? "Marcar como correcto: " : "Pedir corrección: ") + documento.nombre;
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton-icono icono-" + icono + (documento.entrega.estado === estado ? " marcado" : "");
        boton.title = texto;
        boton.setAttribute("aria-label", texto);
        boton.appendChild(this.icono(icono));
        boton.onclick = () => {
            let observaciones = "";
            if (estado === "corregir") {
                observaciones = prompt("¿Qué hay que corregir en «" + documento.nombre + "»?") || "";
                if (observaciones.trim() === "") {
                    return;
                }
            }
            const datos = new FormData();
            datos.append("id", documento.entrega.id);
            datos.append("estado", estado);
            datos.append("observaciones", observaciones);
            this.utilidades.enviarFormulario("api/documentos.php?accion=revisar", datos)
                .then(recargar)
                .catch((error) => alert(error.message));
        };
        return boton;
    }

    // Bloque de Configuración: documentos base y sus plantillas en blanco.
    crearBloquePlantillas() {
        const bloque = document.createElement("div");
        bloque.className = "caja-configuracion vista-superficie";
        const titulo = document.createElement("h3");
        titulo.className = "titulo-configuracion";
        titulo.textContent = "Documentos base y plantillas";
        const ayuda = document.createElement("p");
        ayuda.textContent = "Fichas que las familias descargan y entregan al inscribir a un " +
            "educando, y la circular de participación que se adjunta a los eventos. " +
            "Sube aquí las plantillas en blanco de tu grupo.";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.append(titulo, ayuda, contenido);

        const cargar = () => this.utilidades.obtenerJson("api/documentos.php?accion=plantillas&todos=1")
            .then((documentos) => {
                contenido.replaceChildren();
                documentos.forEach((documento) => {
                    contenido.appendChild(this.crearFilaPlantilla(documento, cargar));
                });
                contenido.appendChild(this.crearFilaPlantilla(null, cargar));
            })
            .catch((error) => {
                contenido.textContent = error.message;
            });
        cargar();
        return bloque;
    }

    crearFilaPlantilla(documento, recargar) {
        const formulario = document.createElement("form");
        formulario.className = "fila-plantilla";
        const nuevo = documento === null;
        const campo = (tipo, nombre, valor, etiquetaTexto) => {
            const etiqueta = document.createElement("label");
            const control = document.createElement("input");
            control.type = tipo;
            control.name = nombre;
            if (tipo === "checkbox") {
                control.checked = Boolean(valor);
                etiqueta.append(control, " " + etiquetaTexto);
            } else {
                control.value = valor ?? "";
                control.placeholder = etiquetaTexto;
                control.setAttribute("aria-label", etiquetaTexto);
                etiqueta.appendChild(control);
            }
            return etiqueta;
        };
        // Todas las filas tienen las mismas cinco celdas (nombre, opciones, plantilla actual,
        // archivo nuevo y botón), aunque alguna quede vacía: así las columnas y los botones
        // de guardar quedan alineados entre filas.
        const celda = (clase) => {
            const elemento = document.createElement("div");
            elemento.className = "celda-plantilla " + clase;
            formulario.appendChild(elemento);
            return elemento;
        };
        const celdaNombre = celda("celda-plantilla-nombre");
        if (nuevo) {
            celdaNombre.appendChild(campo("text", "codigo", "", "código (p. ej. ficha_campamento)"));
        } else {
            const codigo = document.createElement("input");
            codigo.type = "hidden";
            codigo.name = "codigo";
            codigo.value = documento.codigo;
            celdaNombre.appendChild(codigo);
        }
        celdaNombre.appendChild(campo("text", "nombre", documento?.nombre, "Nombre visible"));
        const celdaOpciones = celda("celda-plantilla-opciones");
        if (documento?.tipo === "participacion") {
            const nota = document.createElement("small");
            nota.textContent = "Circular de los eventos";
            celdaOpciones.appendChild(nota);
        } else {
            celdaOpciones.append(
                campo("checkbox", "obligatorio", documento?.obligatorio ?? true, "Obligatorio"),
                campo("checkbox", "activo", documento?.activo ?? true, "Activo")
            );
        }
        const orden = document.createElement("input");
        orden.type = "hidden";
        orden.name = "orden";
        orden.value = documento?.orden ?? 100;
        formulario.appendChild(orden);

        const celdaActual = celda("celda-plantilla-actual");
        if (documento?.tiene_plantilla) {
            const actual = document.createElement("a");
            actual.className = "boton boton-descargar";
            actual.href = "api/documentos.php?accion=plantilla&codigo=" + encodeURIComponent(documento.codigo);
            actual.textContent = "Plantilla actual";
            celdaActual.appendChild(actual);
        } else if (!nuevo) {
            const falta = document.createElement("small");
            falta.className = "estado-documento estado-falta";
            falta.textContent = "Sin plantilla";
            celdaActual.appendChild(falta);
        }
        const archivo = campo("file", "plantilla", null, "Nueva plantilla");
        archivo.querySelector("input").accept = ".pdf,.jpg,.jpeg,.png,.docx";
        celda("celda-plantilla-archivo").appendChild(archivo);

        const guardar = document.createElement("button");
        guardar.type = "submit";
        guardar.className = "boton boton-crear";
        guardar.textContent = nuevo ? "+ Añadir documento" : "Guardar";
        celda("celda-plantilla-guardar").appendChild(guardar);
        formulario.onsubmit = (envio) => {
            envio.preventDefault();
            const datos = new FormData(formulario);
            // Las casillas desmarcadas no se envían: se indica su valor explícitamente.
            if (documento?.tipo !== "participacion") {
                datos.set("obligatorio", formulario.elements.obligatorio.checked ? "1" : "0");
                datos.set("activo", formulario.elements.activo.checked ? "1" : "0");
            }
            this.utilidades.enviarFormulario("api/documentos.php?accion=guardar_plantilla", datos)
                .then(recargar)
                .catch((error) => alert(error.message));
        };
        return formulario;
    }

    formatearFecha(fecha) {
        const [ano, mes, dia] = String(fecha || "").substring(0, 10).split("-");
        return dia ? dia + "/" + mes + "/" + ano : "";
    }
}
