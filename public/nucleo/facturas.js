// Facturas en tablero (E-05): columnas Pendientes, Revisadas y Pagadas.
// El servidor (api/facturas.php) decide qué facturas se ven y qué se puede hacer;
// aquí solo se muestran los botones permitidos.
class IngeriarFacturas {
    constructor({utilidades, contenedor}) {
        this.utilidades = utilidades;
        this.contenedor = contenedor;
        this.datos = {permisos: {}, facturas: [], responsables: []};
        this.columnas = [
            {estado: "pendiente", titulo: "Pendientes"},
            {estado: "revisada", titulo: "Revisadas"},
            {estado: "pagada", titulo: "Pagadas"}
        ];
    }

    cargar() {
        return this.utilidades.obtenerJson("api/facturas.php?accion=listar")
            .then((datos) => {
                this.datos = datos;
                this.render();
            });
    }

    render() {
        this.utilidades.vaciarColaMiniaturas();
        const seccion = document.querySelector(this.contenedor);
        seccion.className = "vista vista-facturas";
        seccion.replaceChildren();

        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-galeria";
        const titulo = document.createElement("h2");
        titulo.textContent = this.datos.permisos.ver_todas ? "Facturas" : "Mis facturas";
        cabecera.appendChild(titulo);
        if (this.datos.permisos.subir || this.datos.permisos.gestionar_todas) {
            const nueva = document.createElement("button");
            nueva.type = "button";
            nueva.className = "boton boton-crear";
            nueva.textContent = "+ Nueva factura";
            nueva.onclick = () => this.mostrarFormulario(null);
            cabecera.appendChild(nueva);
        }
        seccion.appendChild(cabecera);

        const tablero = document.createElement("div");
        tablero.className = "tablero-facturas";
        this.columnas.forEach((columna) => tablero.appendChild(this.crearColumna(columna)));
        seccion.appendChild(tablero);
    }

    crearColumna(columna) {
        const facturas = this.datos.facturas.filter((factura) => factura.estado === columna.estado);
        const elemento = document.createElement("section");
        elemento.className = "columna-facturas columna-" + columna.estado;
        elemento.setAttribute("aria-label", columna.titulo);
        const titulo = document.createElement("h3");
        const total = facturas.reduce((suma, factura) => suma + Number(factura.precio), 0);
        titulo.textContent = columna.titulo + " (" + facturas.length + ") · " + this.formatearImporte(total);
        elemento.appendChild(titulo);

        // Arrastrar entre columnas: solo con permiso para cambiar estados.
        // Siempre hay botones equivalentes para usarlo con teclado.
        if (this.datos.permisos.cambiar_estado) {
            elemento.addEventListener("dragover", (evento) => {
                evento.preventDefault();
                elemento.classList.add("columna-destino");
            });
            elemento.addEventListener("dragleave", () => elemento.classList.remove("columna-destino"));
            elemento.addEventListener("drop", (evento) => {
                evento.preventDefault();
                elemento.classList.remove("columna-destino");
                const id = Number(evento.dataTransfer.getData("text/plain"));
                const factura = this.datos.facturas.find((elemento) => elemento.id === id);
                if (factura && factura.estado !== columna.estado) {
                    this.cambiarEstado(factura, columna.estado);
                }
            });
        }

        if (facturas.length === 0) {
            const vacio = document.createElement("p");
            vacio.className = "sin-datos";
            vacio.textContent = "Ninguna factura.";
            elemento.appendChild(vacio);
        }
        if (!this.datos.permisos.ver_todas) {
            facturas.forEach((factura) => elemento.appendChild(this.crearTarjeta(factura)));
            return elemento;
        }
        // Quien ve las facturas de todos (tesorería): agrupadas por responsable, con un
        // separador con su nombre, el número de facturas y el importe.
        const grupos = new Map();
        facturas.forEach((factura) => {
            if (!grupos.has(factura.responsable_id)) {
                grupos.set(factura.responsable_id, []);
            }
            grupos.get(factura.responsable_id).push(factura);
        });
        [...grupos.values()]
            .sort((a, b) => a[0].responsable_nombre.localeCompare(b[0].responsable_nombre, "es"))
            .forEach((grupo) => {
                const separador = document.createElement("div");
                separador.className = "separador-responsable";
                const nombre = document.createElement("span");
                nombre.textContent = grupo[0].responsable_nombre;
                const resumen = document.createElement("small");
                const importe = grupo.reduce((suma, factura) => suma + Number(factura.precio), 0);
                resumen.textContent = grupo.length + " · " + this.formatearImporte(importe);
                separador.append(nombre, resumen);
                elemento.appendChild(separador);
                grupo.forEach((factura) => elemento.appendChild(this.crearTarjeta(factura)));
            });
        return elemento;
    }

    crearTarjeta(factura) {
        const tarjeta = document.createElement("article");
        tarjeta.className = "tarjeta-factura vista-superficie";

        const abrir = document.createElement("button");
        abrir.type = "button";
        abrir.className = "abrir-factura";
        abrir.setAttribute("aria-label", "Ver la factura " + factura.concepto);
        const miniatura = document.createElement("span");
        miniatura.className = "miniatura-factura";
        miniatura.textContent = factura.tiene_imagen ? "Cargando…" : "Sin imagen";
        const datos = document.createElement("span");
        datos.className = "datos-factura";
        const concepto = document.createElement("strong");
        concepto.textContent = factura.concepto;
        const importe = document.createElement("span");
        importe.className = "importe-factura";
        importe.textContent = this.formatearImporte(factura.precio);
        const detalle = document.createElement("small");
        detalle.textContent = this.formatearFecha(factura.fecha_compra) + " · " +
            this.nombreCategoria(factura.categoria);
        // El responsable ya aparece en el separador de su grupo.
        datos.append(concepto, importe, detalle);
        abrir.append(miniatura, datos);
        abrir.onclick = () => this.mostrarDetalle(factura);
        tarjeta.appendChild(abrir);
        if (factura.tiene_imagen) {
            this.utilidades.encolarMiniatura(this.urlImagen(factura.id), miniatura, factura.concepto);
        }

        if (this.datos.permisos.cambiar_estado) {
            tarjeta.draggable = true;
            tarjeta.addEventListener("dragstart", (evento) => {
                evento.dataTransfer.setData("text/plain", String(factura.id));
            });
            tarjeta.appendChild(this.crearBotonesEstado(factura));
        }
        return tarjeta;
    }

    crearBotonesEstado(factura) {
        const acciones = document.createElement("div");
        acciones.className = "acciones-estado";
        const indice = this.columnas.findIndex((columna) => columna.estado === factura.estado);
        const anterior = this.columnas[indice - 1];
        const siguiente = this.columnas[indice + 1];
        if (anterior) {
            acciones.appendChild(this.crearBotonEstado("← " + anterior.titulo.slice(0, -1), factura, anterior.estado));
        }
        if (siguiente) {
            acciones.appendChild(this.crearBotonEstado(siguiente.titulo.slice(0, -1) + " →", factura, siguiente.estado));
        }
        return acciones;
    }

    crearBotonEstado(texto, factura, estado) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton boton-estado";
        boton.textContent = texto;
        boton.setAttribute("aria-label", "Marcar «" + factura.concepto + "» como " + estado);
        boton.onclick = () => this.cambiarEstado(factura, estado);
        return boton;
    }

    cambiarEstado(factura, estado) {
        const datos = new FormData();
        datos.append("id", factura.id);
        datos.append("estado", estado);
        return this.utilidades.enviarFormulario("api/facturas.php?accion=estado", datos)
            .then(() => this.cargar())
            .catch((error) => alert(error.message));
    }

    mostrarDetalle(factura) {
        const {modal, contenedor} = this.abrirModal("Factura " + factura.numero_fact);
        const detalle = document.createElement("div");
        detalle.className = "detalle-factura";
        if (factura.tiene_imagen) {
            const enlace = document.createElement("a");
            enlace.href = this.urlImagen(factura.id);
            enlace.target = "_blank";
            enlace.rel = "noopener";
            const imagen = document.createElement("img");
            imagen.src = this.urlImagen(factura.id);
            imagen.alt = "Imagen de la factura " + factura.concepto;
            enlace.appendChild(imagen);
            detalle.appendChild(enlace);
        }
        const lista = document.createElement("dl");
        [
            ["Concepto", factura.concepto],
            ["Importe", this.formatearImporte(factura.precio)],
            ["Estado", factura.estado],
            ["Número", factura.numero_fact],
            ["CIF del proveedor", factura.CIF_prov],
            ["Fecha de emisión", this.formatearFecha(factura.fecha_emision)],
            ["Fecha de compra", this.formatearFecha(factura.fecha_compra)],
            ["Categoría", this.nombreCategoria(factura.categoria)],
            ["Responsable", factura.responsable_nombre],
            ["Último cambio de estado", factura.estado_cambiado_en
                ? factura.estado_cambiado_por_nombre + " · " + factura.estado_cambiado_en
                : "—"]
        ].forEach(([termino, valor]) => {
            const dt = document.createElement("dt");
            dt.textContent = termino;
            const dd = document.createElement("dd");
            dd.textContent = valor ?? "";
            lista.append(dt, dd);
        });
        detalle.appendChild(lista);
        contenedor.appendChild(detalle);

        const acciones = document.createElement("div");
        acciones.className = "acciones-formulario";
        if (factura.tiene_imagen) {
            const descargar = document.createElement("a");
            descargar.className = "boton boton-descargar";
            descargar.href = this.urlImagen(factura.id) + "&descargar=1";
            descargar.textContent = "Descargar imagen";
            acciones.appendChild(descargar);
        }
        if (factura.editable) {
            const editar = document.createElement("button");
            editar.type = "button";
            editar.className = "boton boton-editar";
            editar.textContent = "Editar";
            editar.onclick = () => this.mostrarFormulario(factura);
            const borrar = document.createElement("button");
            borrar.type = "button";
            borrar.className = "boton boton-borrar";
            borrar.textContent = "Borrar";
            borrar.onclick = () => this.borrar(factura);
            acciones.append(editar, borrar);
        } else if (factura.estado === "pagada") {
            const aviso = document.createElement("p");
            aviso.className = "sin-datos";
            aviso.textContent = "Las facturas pagadas no se pueden modificar.";
            acciones.appendChild(aviso);
        }
        contenedor.appendChild(acciones);
        modal.querySelector(".modal-cerrar").focus();
    }

    mostrarFormulario(factura) {
        const {contenedor} = this.abrirModal(factura ? "Editar factura" : "Nueva factura");
        const formulario = document.createElement("form");
        formulario.className = "formulario vista-superficie vista-formulario";
        const campo = (nombre, texto, tipo, valor, opciones = {}) => {
            const grupo = document.createElement("div");
            grupo.className = "campo";
            const etiqueta = document.createElement("label");
            etiqueta.textContent = texto;
            etiqueta.htmlFor = "factura-" + nombre;
            const control = document.createElement(tipo === "textarea" ? "textarea" : "input");
            if (tipo !== "textarea") {
                control.type = tipo;
            }
            control.id = "factura-" + nombre;
            control.name = nombre;
            control.required = opciones.required ?? true;
            if (valor !== undefined && valor !== null) {
                control.value = valor;
            }
            Object.assign(control, opciones.atributos || {});
            grupo.append(etiqueta, control);
            formulario.appendChild(grupo);
            return control;
        };
        campo("numero_fact", "Número de factura", "number", factura?.numero_fact, {atributos: {min: 1}});
        campo("CIF_prov", "CIF del proveedor", "text", factura?.CIF_prov, {atributos: {maxLength: 20}});
        campo("fecha_emision", "Fecha de emisión", "date", factura?.fecha_emision?.substring(0, 10));
        campo("fecha_compra", "Fecha de compra", "date", factura?.fecha_compra?.substring(0, 10));
        campo("concepto", "Concepto", "textarea", factura?.concepto, {atributos: {maxLength: 1000}});
        campo("precio", "Importe (€)", "number", factura?.precio, {atributos: {min: 0, step: "0.01"}});

        const grupoCategoria = document.createElement("div");
        grupoCategoria.className = "campo";
        const etiquetaCategoria = document.createElement("label");
        etiquetaCategoria.textContent = "Categoría";
        etiquetaCategoria.htmlFor = "factura-categoria";
        const categoria = document.createElement("select");
        categoria.id = "factura-categoria";
        categoria.name = "categoria";
        categoria.required = true;
        this.categorias().filter((opcion) => opcion.activo !== false && opcion.activo !== 0)
            .forEach((opcion) => {
                const elemento = document.createElement("option");
                elemento.value = opcion.codigo;
                elemento.textContent = opcion.nombre;
                categoria.appendChild(elemento);
            });
        if (factura) {
            categoria.value = factura.categoria;
        }
        grupoCategoria.append(etiquetaCategoria, categoria);
        formulario.appendChild(grupoCategoria);

        if (this.datos.permisos.gestionar_todas && this.datos.responsables.length > 0) {
            const grupo = document.createElement("div");
            grupo.className = "campo";
            const etiqueta = document.createElement("label");
            etiqueta.textContent = "Responsable";
            etiqueta.htmlFor = "factura-responsable";
            const selector = document.createElement("select");
            selector.id = "factura-responsable";
            selector.name = "responsable_id";
            this.datos.responsables.forEach((responsable) => {
                const opcion = document.createElement("option");
                opcion.value = responsable.id;
                opcion.textContent = responsable.nombre;
                selector.appendChild(opcion);
            });
            selector.value = String(factura?.responsable_id ?? usuarioActual?.id ?? "");
            grupo.append(etiqueta, selector);
            formulario.appendChild(grupo);
        }

        const imagen = campo(
            "imagen",
            factura ? "Cambiar la imagen (opcional)" : "Imagen de la factura (JPEG, PNG o WebP)",
            "file",
            undefined,
            {required: !factura, atributos: {accept: "image/jpeg,image/png,image/webp"}}
        );
        imagen.removeAttribute("value");

        const acciones = document.createElement("div");
        acciones.className = "acciones-formulario";
        const guardar = document.createElement("button");
        guardar.type = "submit";
        guardar.className = "boton boton-crear";
        guardar.textContent = "Guardar";
        const cancelar = document.createElement("button");
        cancelar.type = "button";
        cancelar.className = "boton boton-cancelar";
        cancelar.textContent = "Cancelar";
        cancelar.onclick = () => this.cerrarModal();
        acciones.append(guardar, cancelar);
        formulario.appendChild(acciones);

        formulario.onsubmit = (envio) => {
            envio.preventDefault();
            const datos = new FormData(formulario);
            if (factura) {
                datos.append("id", factura.id);
            }
            guardar.disabled = true;
            this.utilidades.enviarFormulario(
                "api/facturas.php?accion=" + (factura ? "editar" : "crear"),
                datos
            ).then(() => {
                this.cerrarModal();
                return this.cargar();
            }).catch((error) => {
                alert(error.message);
                guardar.disabled = false;
            });
        };
        contenedor.appendChild(formulario);
        formulario.querySelector("input").focus();
    }

    borrar(factura) {
        if (!confirm("¿Borrar la factura «" + factura.concepto + "»? No se puede deshacer.")) {
            return;
        }
        const datos = new FormData();
        datos.append("id", factura.id);
        this.utilidades.enviarFormulario("api/facturas.php?accion=borrar", datos)
            .then(() => {
                this.cerrarModal();
                return this.cargar();
            })
            .catch((error) => alert(error.message));
    }

    abrirModal(texto) {
        const modal = document.querySelector("#modal-formulario");
        const contenedor = document.querySelector("#contenedor-formulario");
        contenedor.replaceChildren();
        const titulo = document.createElement("h2");
        titulo.id = "modal-formulario-titulo";
        titulo.textContent = texto;
        contenedor.appendChild(titulo);
        modal.hidden = false;
        document.body.classList.add("modal-abierto");
        return {modal, contenedor};
    }

    cerrarModal() {
        document.querySelector("#modal-formulario").hidden = true;
        document.querySelector("#contenedor-formulario").replaceChildren();
        document.body.classList.remove("modal-abierto");
    }

    urlImagen(id) {
        return "api/facturas.php?accion=imagen&id=" + encodeURIComponent(id);
    }

    categorias() {
        try {
            return JSON.parse(document.documentElement.dataset.categoriasFactura || "[]");
        } catch (error) {
            return [];
        }
    }

    nombreCategoria(codigo) {
        return this.categorias().find((categoria) => categoria.codigo === codigo)?.nombre || codigo;
    }

    formatearImporte(valor) {
        return Number(valor).toLocaleString("es-ES", {style: "currency", currency: "EUR"});
    }

    formatearFecha(fecha) {
        const [ano, mes, dia] = String(fecha || "").substring(0, 10).split("-");
        return dia ? dia + "/" + mes + "/" + ano : "";
    }
}
