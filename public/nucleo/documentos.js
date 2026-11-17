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
    crearBloqueInscripcion(educando) {
        const bloque = document.createElement("section");
        bloque.className = "bloque-inscripcion";
        const titulo = document.createElement("h3");
        titulo.textContent = "Documentación de inscripción";
        const contenido = document.createElement("div");
        contenido.textContent = "Cargando…";
        bloque.append(titulo, contenido);

        const cargar = () => this.utilidades.obtenerJson(
            "api/documentos.php?accion=educando&id=" + encodeURIComponent(educando.id)
        ).then((datos) => {
            contenido.replaceChildren(this.crearTablaInscripcion(educando, datos, cargar));
        }).catch((error) => {
            contenido.textContent = error.message;
        });
        cargar();
        return bloque;
    }

    crearTablaInscripcion(educando, datos, recargar) {
        if (datos.documentos.length === 0) {
            const vacio = document.createElement("p");
            vacio.className = "sin-datos";
            vacio.textContent = "No hay documentos de inscripción configurados.";
            return vacio;
        }
        const lista = document.createElement("div");
        lista.className = "lista-inscripcion";
        datos.documentos.forEach((documento) => {
            const fila = document.createElement("article");
            fila.className = "fila-inscripcion vista-superficie";

            const nombre = document.createElement("strong");
            nombre.textContent = documento.nombre + (documento.obligatorio ? "" : " (opcional)");
            const estado = this.crearEstado(documento.entrega);
            const acciones = document.createElement("div");
            acciones.className = "acciones-inscripcion";

            if (documento.tiene_plantilla) {
                const plantilla = document.createElement("a");
                plantilla.className = "boton boton-descargar";
                plantilla.href = "api/documentos.php?accion=plantilla&codigo=" +
                    encodeURIComponent(documento.codigo);
                plantilla.textContent = "Descargar en blanco";
                acciones.appendChild(plantilla);
            }
            if (documento.entrega) {
                const ver = document.createElement("a");
                ver.className = "boton";
                ver.href = "api/documentos.php?accion=ver&id=" + documento.entrega.id;
                ver.target = "_blank";
                ver.rel = "noopener";
                ver.textContent = "Ver entregado";
                acciones.appendChild(ver);
            }
            if (datos.puede_subir) {
                acciones.appendChild(this.crearBotonSubir(educando, documento, recargar));
            }
            if (datos.puede_revisar && documento.entrega) {
                acciones.append(
                    this.crearBotonRevisar("Revisado ✔", "boton-crear", documento, "revisado", recargar),
                    this.crearBotonRevisar("Pedir corrección", "boton-editar", documento, "corregir", recargar)
                );
            }
            fila.append(nombre, estado, acciones);
            lista.appendChild(fila);
        });
        return lista;
    }

    crearEstado(entrega) {
        const estado = document.createElement("span");
        if (!entrega) {
            estado.className = "estado-documento estado-falta";
            estado.textContent = "Sin entregar";
            return estado;
        }
        const fecha = this.formatearFecha(entrega.fecha_subida);
        const textos = {
            pendiente: "Entregado el " + fecha + " · pendiente de revisión",
            revisado: "Revisado por " + (entrega.revisado_por || "un monitor") + " el " +
                this.formatearFecha(entrega.revisado_en),
            corregir: "A corregir: " + (entrega.observaciones || "")
        };
        estado.className = "estado-documento estado-" + entrega.estado;
        estado.textContent = textos[entrega.estado] || entrega.estado;
        return estado;
    }

    // Botón de subida: abre el selector de archivos y entrega en cuanto se elige uno.
    crearBotonSubir(educando, documento, recargar) {
        const etiqueta = document.createElement("label");
        etiqueta.className = "boton boton-crear boton-archivo";
        etiqueta.textContent = documento.entrega ? "Sustituir" : "Subir";
        const archivo = document.createElement("input");
        archivo.type = "file";
        archivo.accept = ".pdf,.jpg,.jpeg,.png,.docx";
        archivo.setAttribute("aria-label", "Subir " + documento.nombre);
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

    crearBotonRevisar(texto, clase, documento, estado, recargar) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton " + clase;
        boton.textContent = texto;
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
        if (nuevo) {
            formulario.appendChild(campo("text", "codigo", "", "código (p. ej. ficha_campamento)"));
        } else {
            const codigo = document.createElement("input");
            codigo.type = "hidden";
            codigo.name = "codigo";
            codigo.value = documento.codigo;
            formulario.appendChild(codigo);
        }
        formulario.appendChild(campo("text", "nombre", documento?.nombre, "Nombre visible"));
        if (documento?.tipo === "participacion") {
            const nota = document.createElement("small");
            nota.textContent = "Circular de los eventos";
            formulario.appendChild(nota);
        } else {
            formulario.append(
                campo("checkbox", "obligatorio", documento?.obligatorio ?? true, "Obligatorio"),
                campo("checkbox", "activo", documento?.activo ?? true, "Activo")
            );
        }
        const orden = document.createElement("input");
        orden.type = "hidden";
        orden.name = "orden";
        orden.value = documento?.orden ?? 100;
        formulario.appendChild(orden);

        if (documento?.tiene_plantilla) {
            const actual = document.createElement("a");
            actual.className = "boton boton-descargar";
            actual.href = "api/documentos.php?accion=plantilla&codigo=" + encodeURIComponent(documento.codigo);
            actual.textContent = "Plantilla actual";
            formulario.appendChild(actual);
        } else if (!nuevo) {
            const falta = document.createElement("small");
            falta.className = "estado-documento estado-falta";
            falta.textContent = "Sin plantilla";
            formulario.appendChild(falta);
        }
        const archivo = campo("file", "plantilla", null, "Nueva plantilla");
        archivo.querySelector("input").accept = ".pdf,.jpg,.jpeg,.png,.docx";
        formulario.appendChild(archivo);

        const guardar = document.createElement("button");
        guardar.type = "submit";
        guardar.className = "boton boton-crear";
        guardar.textContent = nuevo ? "+ Añadir documento" : "Guardar";
        formulario.appendChild(guardar);
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
