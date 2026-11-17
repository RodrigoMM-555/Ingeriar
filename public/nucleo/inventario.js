// Inventario de material y listas de material (E-04, plano visual en E-15).
// Pestaña «Inventario»: ubicaciones en árbol libre y materiales con su cantidad en cada una.
// Se ve como plano (cada ubicación es una caja con sus fichas de material y sus
// sububicaciones dentro) o como tabla. En el plano se arrastran fichas entre cajas para
// mover material y cajas dentro de otras para reorganizar las ubicaciones.
// Pestaña «Listas de material»: a la izquierda el mismo plano, a la derecha lo que se
// lleva. Se pasa material arrastrando o con el botón «→» (para teclado). La lista no
// descuenta del inventario: genera un documento con qué coger, de dónde y qué comprar.
class IngeriarInventario {
    constructor({utilidades, contenedor}) {
        this.utilidades = utilidades;
        this.contenedor = contenedor;
        this.inventario = {ubicaciones: [], materiales: []};
        this.listas = [];
        this.pestana = "inventario";
        this.vistaInventario = "plano";
        // Lo que se está arrastrando: {tipo: "material" | "ubicacion" | "toma", ...}.
        this.arrastre = null;
        this.filtroUbicacion = null;
        this.busqueda = "";
        this.lista = null;
        this.cambiosSinGuardar = false;
    }

    cargar() {
        return this.utilidades.obtenerJson("api/inventario.php?accion=datos")
            .then((datos) => {
                this.inventario = datos.inventario;
                this.listas = datos.listas;
                this.render();
            });
    }

    // Envía una acción y actualiza los datos con lo que devuelve el servidor.
    enviar(accion, datos) {
        return this.utilidades.enviarJson("api/inventario.php?accion=" + accion, datos)
            .then((respuesta) => {
                if (respuesta.inventario) {
                    this.inventario = respuesta.inventario;
                }
                if (respuesta.listas) {
                    this.listas = respuesta.listas;
                }
                return respuesta;
            });
    }

    // ---------- Utilidades del árbol ----------

    ubicacion(id) {
        return this.inventario.ubicaciones.find((ubicacion) => ubicacion.id === id);
    }

    hijas(padre) {
        return this.inventario.ubicaciones
            .filter((ubicacion) => ubicacion.padre === padre)
            .sort((a, b) => a.nombre.localeCompare(b.nombre, "es"));
    }

    // «Trastero › Zona A › Estantería 1»
    ruta(id) {
        const partes = [];
        let actual = this.ubicacion(id);
        while (actual) {
            partes.unshift(actual.nombre);
            actual = this.ubicacion(actual.padre);
        }
        return partes.join(" › ") || "(ubicación borrada)";
    }

    // La ubicación y todas las que tiene dentro.
    descendientes(id) {
        return [id, ...this.hijas(id).flatMap((hija) => this.descendientes(hija.id))];
    }

    // Todas las ubicaciones ordenadas como en el árbol, con su ruta.
    ubicacionesOrdenadas(padre = null) {
        return this.hijas(padre).flatMap((ubicacion) => [
            {id: ubicacion.id, ruta: this.ruta(ubicacion.id)},
            ...this.ubicacionesOrdenadas(ubicacion.id)
        ]);
    }

    material(id) {
        return this.inventario.materiales.find((material) => material.id === id);
    }

    total(material) {
        return material.existencias.reduce((suma, existencia) => suma + existencia.cantidad, 0);
    }

    // Materiales con existencias en una ubicación (sin contar las de dentro), por nombre.
    materialesEn(ubicacionId) {
        return this.inventario.materiales
            .map((material) => ({
                material,
                cantidad: material.existencias.find((existencia) => existencia.ubicacion === ubicacionId)?.cantidad || 0
            }))
            .filter((elemento) => elemento.cantidad > 0)
            .sort((a, b) => a.material.nombre.localeCompare(b.material.nombre, "es"));
    }

    // ---------- Arrastrar y soltar (E-15) ----------

    // Lo arrastrado se guarda en this.arrastre; dataTransfer solo hace falta para que
    // Firefox empiece a arrastrar.
    hacerArrastrable(elemento, datos) {
        elemento.draggable = true;
        elemento.addEventListener("dragstart", (evento) => {
            evento.stopPropagation();
            this.arrastre = datos;
            evento.dataTransfer.effectAllowed = "move";
            evento.dataTransfer.setData("text/plain", JSON.stringify(datos));
            elemento.classList.add("arrastrando");
        });
        elemento.addEventListener("dragend", () => {
            this.arrastre = null;
            elemento.classList.remove("arrastrando");
            this.limpiarDestinos();
        });
    }

    // Convierte un elemento en destino. «acepta» decide si lo arrastrado puede caer aquí;
    // con cajas anidadas, la más interior que acepta se queda con el evento.
    zonaSoltar(elemento, acepta, alSoltar) {
        elemento.addEventListener("dragover", (evento) => {
            if (!this.arrastre || !acepta(this.arrastre)) {
                return;
            }
            evento.preventDefault();
            evento.stopPropagation();
            this.limpiarDestinos(elemento);
            elemento.classList.add("columna-destino");
        });
        elemento.addEventListener("dragleave", (evento) => {
            if (!elemento.contains(evento.relatedTarget)) {
                elemento.classList.remove("columna-destino");
            }
        });
        elemento.addEventListener("drop", (evento) => {
            if (!this.arrastre || !acepta(this.arrastre)) {
                return;
            }
            evento.preventDefault();
            evento.stopPropagation();
            const arrastre = this.arrastre;
            this.arrastre = null;
            this.limpiarDestinos();
            alSoltar(arrastre);
        });
    }

    limpiarDestinos(excepto = null) {
        document.querySelectorAll(".columna-destino").forEach((elemento) => {
            if (elemento !== excepto) {
                elemento.classList.remove("columna-destino");
            }
        });
    }

    // ---------- Plano del inventario (E-15) ----------

    renderPestanaInventario() {
        const contenedor = document.createElement("div");
        contenedor.className = "pestana-inventario";
        const selector = document.createElement("div");
        selector.className = "selector-vista-inventario";
        [["plano", "Plano"], ["tabla", "Tabla"]].forEach(([valor, texto]) => {
            const boton = this.boton(texto, this.vistaInventario === valor ? "boton-editar" : "boton-cancelar", () => {
                this.vistaInventario = valor;
                this.render();
            });
            boton.setAttribute("aria-pressed", String(this.vistaInventario === valor));
            selector.appendChild(boton);
        });
        contenedor.appendChild(selector);
        contenedor.appendChild(this.vistaInventario === "plano" ? this.renderPlanoInventario() : this.renderInventario());
        return contenedor;
    }

    renderPlanoInventario() {
        const panel = document.createElement("section");
        panel.className = "panel-inventario vista-superficie";
        const herramientas = document.createElement("div");
        herramientas.className = "filtros-tabla";
        const buscador = this.buscadorPlano();
        herramientas.append(
            buscador,
            this.boton("+ Ubicación", "boton-crear", () => this.formularioUbicacion(null, null)),
            this.boton("+ Material", "boton-crear", () => this.formularioMaterial(null))
        );
        const plano = this.crearPlano("inventario");
        buscador.oninput = () => {
            this.busqueda = buscador.value;
            this.marcarBusqueda(plano);
        };
        panel.append(
            herramientas,
            this.parrafo("Arrastra una ficha a otra caja para mover material. Arrastra el título de una caja " +
                "dentro de otra para reorganizar, o al fondo para que sea una ubicación principal. " +
                "Pulsa una ficha para editar el material.", "ayuda-inventario"),
            plano
        );
        return panel;
    }

    buscadorPlano() {
        const buscador = document.createElement("input");
        buscador.type = "search";
        buscador.className = "buscador-tabla";
        buscador.placeholder = "Buscar material…";
        buscador.value = this.busqueda;
        buscador.setAttribute("aria-label", "Buscar material");
        return buscador;
    }

    // Resalta las fichas que coinciden con la búsqueda y atenúa las demás.
    marcarBusqueda(plano) {
        const texto = this.busqueda.trim().toLowerCase();
        plano.querySelectorAll(".ficha-material").forEach((ficha) => {
            const coincide = texto !== "" && ficha.dataset.nombre.includes(texto);
            ficha.classList.toggle("coincide", coincide);
            ficha.classList.toggle("atenuada", texto !== "" && !coincide);
        });
    }

    // modo «inventario»: mover material y ubicaciones. modo «lista»: coger material para la lista.
    crearPlano(modo) {
        const plano = document.createElement("div");
        plano.className = "plano-inventario";
        if (modo === "inventario") {
            // El fondo del plano: soltar una caja aquí la convierte en ubicación principal.
            this.zonaSoltar(plano,
                (arrastre) => arrastre.tipo === "ubicacion" && this.ubicacion(arrastre.id)?.padre !== null,
                (arrastre) => this.moverUbicacion(arrastre.id, null));
        }
        this.hijas(null).forEach((ubicacion) => plano.appendChild(this.crearCaja(ubicacion, modo, 0)));
        if (modo === "inventario") {
            const sinExistencias = this.inventario.materiales
                .filter((material) => this.total(material) === 0)
                .sort((a, b) => a.nombre.localeCompare(b.nombre, "es"));
            if (sinExistencias.length > 0) {
                const caja = document.createElement("section");
                caja.className = "caja-ubicacion caja-sin-existencias";
                const titulo = document.createElement("strong");
                titulo.textContent = "Sin existencias";
                const fichas = document.createElement("div");
                fichas.className = "fichas-caja";
                sinExistencias.forEach((material) => fichas.appendChild(this.crearFicha(material, null, 0, modo)));
                caja.append(titulo, fichas);
                plano.appendChild(caja);
            }
        }
        if (this.inventario.ubicaciones.length === 0) {
            plano.appendChild(this.parrafo("Empieza creando una ubicación, por ejemplo «Trastero» o «Local».", "sin-datos"));
        }
        this.marcarBusqueda(plano);
        return plano;
    }

    crearCaja(ubicacion, modo, nivel) {
        const caja = document.createElement("section");
        caja.className = "caja-ubicacion nivel-" + (nivel % 2 === 0 ? "par" : "impar");
        caja.setAttribute("aria-label", this.ruta(ubicacion.id));

        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-caja";
        const nombre = document.createElement("strong");
        nombre.textContent = ubicacion.nombre;
        cabecera.appendChild(nombre);
        if (modo === "inventario") {
            // El título es el asa para arrastrar la caja.
            cabecera.classList.add("asa-caja");
            cabecera.title = "Arrastra para meter «" + ubicacion.nombre + "» en otra ubicación";
            this.hacerArrastrable(cabecera, {tipo: "ubicacion", id: ubicacion.id});
            const acciones = document.createElement("span");
            acciones.className = "acciones-ubicacion";
            acciones.append(
                this.boton("+", "boton-mini", () => this.formularioUbicacion(null, ubicacion.id), "Añadir dentro de " + ubicacion.nombre),
                this.boton("✎", "boton-mini", () => this.formularioUbicacion(ubicacion, ubicacion.padre), "Renombrar o mover " + ubicacion.nombre),
                this.boton("×", "boton-mini boton-mini-borrar", () => this.borrarUbicacion(ubicacion), "Borrar " + ubicacion.nombre)
            );
            cabecera.appendChild(acciones);
            // Soltar sobre la caja de origen (o dentro de la caja que se arrastra) no hace
            // nada: se corta aquí para que no lo recoja la caja de fuera.
            const esOrigen = () => this.arrastre && (
                (this.arrastre.tipo === "material" && this.arrastre.ubicacion === ubicacion.id) ||
                (this.arrastre.tipo === "ubicacion" && this.descendientes(this.arrastre.id).includes(ubicacion.id)));
            ["dragover", "drop"].forEach((tipo) => caja.addEventListener(tipo, (evento) => {
                if (esOrigen()) {
                    evento.stopPropagation();
                    this.limpiarDestinos();
                }
            }));
            this.zonaSoltar(caja,
                (arrastre) => this.aceptaEnCaja(arrastre, ubicacion.id),
                (arrastre) => arrastre.tipo === "material"
                    ? this.moverMaterial(arrastre.material, arrastre.ubicacion, ubicacion.id)
                    : this.moverUbicacion(arrastre.id, ubicacion.id));
        }
        caja.appendChild(cabecera);

        const fichas = document.createElement("div");
        fichas.className = "fichas-caja";
        this.materialesEn(ubicacion.id).forEach(({material, cantidad}) => {
            const disponible = modo === "lista" ? cantidad - this.yaCogido(material.id, ubicacion.id) : cantidad;
            fichas.appendChild(this.crearFicha(material, ubicacion.id, disponible, modo));
        });
        const hijas = this.hijas(ubicacion.id);
        if (fichas.children.length === 0 && hijas.length === 0) {
            fichas.appendChild(this.parrafo("Vacía", "caja-vacia"));
        }
        caja.appendChild(fichas);
        if (hijas.length > 0) {
            const contenedorHijas = document.createElement("div");
            contenedorHijas.className = "hijas-caja";
            hijas.forEach((hija) => contenedorHijas.appendChild(this.crearCaja(hija, modo, nivel + 1)));
            caja.appendChild(contenedorHijas);
        }
        return caja;
    }

    aceptaEnCaja(arrastre, ubicacionId) {
        if (arrastre.tipo === "material") {
            return arrastre.ubicacion !== ubicacionId;
        }
        if (arrastre.tipo === "ubicacion") {
            // Ni en sí misma, ni dentro de una suya, ni donde ya está.
            return !this.descendientes(arrastre.id).includes(ubicacionId) &&
                this.ubicacion(arrastre.id)?.padre !== ubicacionId;
        }
        return false;
    }

    // Ficha de un material en una ubicación (ubicacionId null: sin existencias).
    crearFicha(material, ubicacionId, cantidad, modo) {
        const ficha = document.createElement("div");
        ficha.className = "ficha-material";
        ficha.dataset.nombre = material.nombre.toLowerCase();
        if (material.notas) {
            ficha.title = material.notas;
        }
        const nombre = document.createElement("button");
        nombre.type = "button";
        nombre.className = "nombre-ficha";
        nombre.textContent = material.nombre;
        const numero = document.createElement("span");
        numero.className = "cantidad-ficha";
        numero.textContent = cantidad + (material.unidad && material.unidad !== "uds" ? " " + material.unidad : "");
        ficha.append(nombre, numero);

        if (modo === "inventario") {
            nombre.setAttribute("aria-label", "Editar " + material.nombre);
            nombre.onclick = () => this.formularioMaterial(material);
            if (ubicacionId) {
                this.hacerArrastrable(ficha, {tipo: "material", material: material.id, ubicacion: ubicacionId});
                ficha.appendChild(this.boton("⇄", "boton-ficha", () => this.moverConSelector(material, ubicacionId),
                    "Mover " + material.nombre + " de " + this.ruta(ubicacionId) + " a otra ubicación"));
            }
        } else {
            nombre.tabIndex = -1;
            if (cantidad > 0) {
                this.hacerArrastrable(ficha, {tipo: "material", material: material.id, ubicacion: ubicacionId});
                ficha.appendChild(this.boton("→", "boton-ficha", () => this.mover(material.id, ubicacionId),
                    "Llevar " + material.nombre + " de " + this.ruta(ubicacionId)));
            } else {
                ficha.classList.add("agotada");
                ficha.title = "Ya te llevas todo lo que hay aquí";
            }
        }
        return ficha;
    }

    // Arrastrar una ficha a otra caja: pregunta cuántas y lo hace en el servidor.
    moverMaterial(materialId, origen, destino) {
        const material = this.material(materialId);
        const hay = material?.existencias.find((existencia) => existencia.ubicacion === origen)?.cantidad || 0;
        if (!material || hay === 0) {
            return;
        }
        this.pedirCantidad("Mover material",
            "«" + material.nombre + "»: de " + this.ruta(origen) + " a " + this.ruta(destino) + ". Hay " + hay + ".",
            hay)
            .then((respuesta) => respuesta && this.enviarMovimiento(material, origen, destino, respuesta.cantidad));
    }

    // Botón «⇄» (sin ratón): se elige el destino y la cantidad en el mismo cuadro.
    moverConSelector(material, origen) {
        const hay = material.existencias.find((existencia) => existencia.ubicacion === origen)?.cantidad || 0;
        const destinos = this.ubicacionesOrdenadas()
            .filter((opcion) => opcion.id !== origen)
            .map((opcion) => ({valor: opcion.id, texto: opcion.ruta}));
        if (destinos.length === 0) {
            alert("No hay otra ubicación a la que moverlo.");
            return;
        }
        this.pedirCantidad("Mover material", "«" + material.nombre + "» desde " + this.ruta(origen) + ". Hay " + hay + ".",
            hay, destinos)
            .then((respuesta) => respuesta && this.enviarMovimiento(material, origen, respuesta.destino, respuesta.cantidad));
    }

    enviarMovimiento(material, origen, destino, cantidad) {
        return this.enviar("mover_material", {material: material.id, origen, destino, cantidad})
            .then(() => this.render())
            .catch((error) => alert(error.message));
    }

    moverUbicacion(id, nuevoPadre) {
        const ubicacion = this.ubicacion(id);
        if (!ubicacion || ubicacion.padre === nuevoPadre) {
            return;
        }
        this.enviar("guardar_ubicacion", {id, nombre: ubicacion.nombre, padre: nuevoPadre || ""})
            .then(() => this.render())
            .catch((error) => alert(error.message));
    }

    // Cuadro para elegir cuántas unidades (y, si se pasan destinos, a dónde). Devuelve una
    // promesa con {cantidad, destino} o null si se cancela.
    pedirCantidad(titulo, texto, maximo, destinos = null) {
        return new Promise((resolver) => {
            const {formulario, cerrar} = this.abrirModal(titulo);
            formulario.appendChild(this.parrafo(texto, "ayuda-inventario"));
            const selector = destinos ? this.campoSelector(formulario, "A dónde", destinos, destinos[0].valor) : null;
            const grupo = document.createElement("div");
            grupo.className = "campo campo-cantidad-mover";
            const etiqueta = document.createElement("label");
            etiqueta.textContent = "Cantidad (de 1 a " + maximo + ")";
            const deslizador = document.createElement("input");
            deslizador.type = "range";
            deslizador.min = 1;
            deslizador.max = maximo;
            deslizador.value = maximo;
            deslizador.setAttribute("aria-hidden", "true");
            deslizador.tabIndex = -1;
            const numero = document.createElement("input");
            numero.type = "number";
            numero.min = 1;
            numero.max = maximo;
            numero.step = 1;
            numero.value = maximo;
            numero.required = true;
            deslizador.oninput = () => {
                numero.value = deslizador.value;
            };
            numero.oninput = () => {
                deslizador.value = numero.value;
            };
            etiqueta.appendChild(numero);
            grupo.append(etiqueta, deslizador);
            formulario.appendChild(grupo);
            const terminar = (valor) => {
                cerrar();
                resolver(valor);
            };
            this.accionesFormulario(formulario, () => terminar(null), () => {
                const cantidad = Math.min(maximo, Math.max(1, parseInt(numero.value, 10) || 0));
                terminar({cantidad, destino: selector?.value});
                return Promise.resolve();
            }, "Aceptar");
            numero.focus();
            numero.select();
        });
    }

    // ---------- Vista general ----------

    render() {
        const seccion = document.querySelector(this.contenedor);
        seccion.className = "vista vista-inventario";
        seccion.replaceChildren();

        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-galeria";
        const titulo = document.createElement("h2");
        titulo.textContent = "Inventario";
        const pestanas = document.createElement("div");
        pestanas.className = "pestanas-inventario";
        pestanas.setAttribute("role", "tablist");
        [["inventario", "Material y ubicaciones"], ["listas", "Listas de material"]].forEach(([valor, texto]) => {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "boton " + (this.pestana === valor ? "boton-crear" : "boton-cancelar");
            boton.setAttribute("role", "tab");
            boton.setAttribute("aria-selected", String(this.pestana === valor));
            boton.textContent = texto;
            boton.onclick = () => {
                if (this.cambiosSinGuardar && !confirm("Hay cambios sin guardar en la lista. ¿Salir igualmente?")) {
                    return;
                }
                this.cambiosSinGuardar = false;
                this.pestana = valor;
                this.lista = null;
                this.render();
            };
            pestanas.appendChild(boton);
        });
        cabecera.append(titulo, pestanas);
        seccion.appendChild(cabecera);

        if (this.pestana === "inventario") {
            seccion.appendChild(this.renderPestanaInventario());
        } else if (this.lista) {
            seccion.appendChild(this.renderEditorLista());
        } else {
            seccion.appendChild(this.renderListas());
        }
    }

    // ---------- Pestaña Inventario ----------

    renderInventario() {
        const rejilla = document.createElement("div");
        rejilla.className = "rejilla-inventario";

        const arbol = document.createElement("section");
        arbol.className = "panel-inventario vista-superficie";
        const tituloArbol = document.createElement("h3");
        tituloArbol.textContent = "Ubicaciones";
        const nuevaRaiz = this.boton("+ Ubicación", "boton-crear", () => this.formularioUbicacion(null, null));
        const todas = this.boton("Todo el material", this.filtroUbicacion ? "boton-cancelar" : "boton-editar", () => {
            this.filtroUbicacion = null;
            this.render();
        });
        arbol.append(tituloArbol, nuevaRaiz, todas, this.crearArbol(null));
        if (this.inventario.ubicaciones.length === 0) {
            arbol.appendChild(this.parrafo("Empieza creando una ubicación, por ejemplo «Trastero» o «Local».", "sin-datos"));
        }

        rejilla.append(arbol, this.renderMateriales());
        return rejilla;
    }

    crearArbol(padre) {
        const lista = document.createElement("ul");
        lista.className = "arbol-ubicaciones";
        this.hijas(padre).forEach((ubicacion) => {
            const elemento = document.createElement("li");
            const fila = document.createElement("div");
            fila.className = "nodo-ubicacion" + (this.filtroUbicacion === ubicacion.id ? " activo" : "");
            const nombre = this.boton(ubicacion.nombre, "nombre-ubicacion", () => {
                this.filtroUbicacion = ubicacion.id;
                this.render();
            });
            nombre.title = "Ver el material de " + this.ruta(ubicacion.id);
            const acciones = document.createElement("span");
            acciones.className = "acciones-ubicacion";
            acciones.append(
                this.boton("+", "boton-mini", () => this.formularioUbicacion(null, ubicacion.id), "Añadir dentro de " + ubicacion.nombre),
                this.boton("✎", "boton-mini", () => this.formularioUbicacion(ubicacion, ubicacion.padre), "Renombrar o mover " + ubicacion.nombre),
                this.boton("×", "boton-mini boton-mini-borrar", () => this.borrarUbicacion(ubicacion), "Borrar " + ubicacion.nombre)
            );
            fila.append(nombre, acciones);
            elemento.appendChild(fila);
            if (this.hijas(ubicacion.id).length > 0) {
                elemento.appendChild(this.crearArbol(ubicacion.id));
            }
            lista.appendChild(elemento);
        });
        return lista;
    }

    renderMateriales() {
        const panel = document.createElement("section");
        panel.className = "panel-inventario vista-superficie";
        const titulo = document.createElement("h3");
        titulo.textContent = this.filtroUbicacion ? "Material en " + this.ruta(this.filtroUbicacion) : "Todo el material";

        const herramientas = document.createElement("div");
        herramientas.className = "filtros-tabla";
        const buscador = document.createElement("input");
        buscador.type = "search";
        buscador.className = "buscador-tabla";
        buscador.placeholder = "Buscar material…";
        buscador.value = this.busqueda;
        buscador.setAttribute("aria-label", "Buscar material");
        const nuevo = this.boton("+ Material", "boton-crear", () => this.formularioMaterial(null));
        herramientas.append(buscador, nuevo);

        const contenedorTabla = document.createElement("div");
        const pintarTabla = () => contenedorTabla.replaceChildren(this.crearTablaMateriales());
        buscador.oninput = () => {
            this.busqueda = buscador.value;
            pintarTabla();
        };
        pintarTabla();
        panel.append(titulo, herramientas, contenedorTabla);
        return panel;
    }

    materialesFiltrados() {
        const texto = this.busqueda.trim().toLowerCase();
        const dentro = this.filtroUbicacion ? new Set(this.descendientes(this.filtroUbicacion)) : null;
        return this.inventario.materiales
            .filter((material) => !texto || material.nombre.toLowerCase().includes(texto))
            .filter((material) => !dentro || material.existencias.some(
                (existencia) => dentro.has(existencia.ubicacion) && existencia.cantidad > 0
            ))
            .sort((a, b) => a.nombre.localeCompare(b.nombre, "es"));
    }

    crearTablaMateriales() {
        const materiales = this.materialesFiltrados();
        if (materiales.length === 0) {
            return this.parrafo("No hay material que coincida.", "sin-datos");
        }
        const tabla = document.createElement("table");
        tabla.className = "tabla-inventario";
        const cabecera = document.createElement("tr");
        ["Material", "Total", "Dónde está", ""].forEach((texto) => {
            const celda = document.createElement("th");
            celda.textContent = texto;
            cabecera.appendChild(celda);
        });
        tabla.appendChild(cabecera);
        materiales.forEach((material) => {
            const fila = document.createElement("tr");
            const nombre = document.createElement("td");
            nombre.textContent = material.nombre;
            if (material.notas) {
                nombre.appendChild(this.parrafo(material.notas, "nota-material"));
            }
            const total = document.createElement("td");
            total.textContent = this.total(material) + " " + material.unidad;
            const donde = document.createElement("td");
            const lista = document.createElement("ul");
            lista.className = "lista-existencias";
            material.existencias.filter((existencia) => existencia.cantidad > 0).forEach((existencia) => {
                const elemento = document.createElement("li");
                elemento.textContent = this.ruta(existencia.ubicacion) + ": " + existencia.cantidad;
                lista.appendChild(elemento);
            });
            donde.appendChild(lista);
            const acciones = document.createElement("td");
            acciones.className = "acciones";
            acciones.append(
                this.boton("Editar", "boton-editar", () => this.formularioMaterial(material)),
                this.boton("Borrar", "boton-borrar", () => {
                    if (confirm("¿Borrar «" + material.nombre + "» del inventario?")) {
                        this.enviar("borrar_material", {id: material.id}).then(() => this.render())
                            .catch((error) => alert(error.message));
                    }
                })
            );
            fila.append(nombre, total, donde, acciones);
            tabla.appendChild(fila);
        });
        return tabla;
    }

    formularioUbicacion(ubicacion, padre) {
        const {formulario, cerrar} = this.abrirModal(ubicacion ? "Editar ubicación" : "Nueva ubicación");
        const nombre = this.campoTexto(formulario, "Nombre", ubicacion?.nombre || "", true);
        const selector = this.campoSelector(formulario, "Dentro de", [
            {valor: "", texto: "(ninguna: ubicación principal)"},
            ...this.ubicacionesOrdenadas()
                .filter((opcion) => !ubicacion || !this.descendientes(ubicacion.id).includes(opcion.id))
                .map((opcion) => ({valor: opcion.id, texto: opcion.ruta}))
        ], padre || "");
        this.accionesFormulario(formulario, cerrar, () => this.enviar("guardar_ubicacion", {
            id: ubicacion?.id,
            nombre: nombre.value,
            padre: selector.value
        }).then(() => {
            cerrar();
            this.render();
        }));
        nombre.focus();
    }

    borrarUbicacion(ubicacion) {
        if (!confirm("¿Borrar la ubicación «" + this.ruta(ubicacion.id) + "»?")) {
            return;
        }
        this.enviar("borrar_ubicacion", {id: ubicacion.id})
            .then(() => {
                if (this.filtroUbicacion === ubicacion.id) {
                    this.filtroUbicacion = null;
                }
                this.render();
            })
            .catch((error) => alert(error.message));
    }

    formularioMaterial(material) {
        const {formulario, cerrar} = this.abrirModal(material ? "Editar material" : "Nuevo material");
        const nombre = this.campoTexto(formulario, "Nombre", material?.nombre || "", true);
        const unidad = this.campoTexto(formulario, "Unidad (uds, cajas, metros…)", material?.unidad || "uds", false);
        const notas = this.campoTexto(formulario, "Notas", material?.notas || "", false);

        const grupo = document.createElement("fieldset");
        grupo.className = "campo campo-existencias";
        const leyenda = document.createElement("legend");
        leyenda.textContent = "Dónde está y cuánto hay";
        const filas = document.createElement("div");
        filas.className = "filas-existencias";
        const opciones = this.ubicacionesOrdenadas().map((opcion) => ({valor: opcion.id, texto: opcion.ruta}));
        const anadirFila = (ubicacion = "", cantidad = 0) => {
            const fila = document.createElement("div");
            fila.className = "fila-existencia";
            const selector = document.createElement("select");
            selector.setAttribute("aria-label", "Ubicación");
            opciones.forEach((opcion) => {
                const elemento = document.createElement("option");
                elemento.value = opcion.valor;
                elemento.textContent = opcion.texto;
                selector.appendChild(elemento);
            });
            selector.value = ubicacion || opciones[0]?.valor || "";
            const numero = document.createElement("input");
            numero.type = "number";
            numero.min = 0;
            numero.step = 1;
            numero.value = cantidad;
            numero.setAttribute("aria-label", "Cantidad");
            fila.append(selector, numero, this.boton("×", "boton-mini boton-mini-borrar", () => fila.remove(), "Quitar esta ubicación"));
            filas.appendChild(fila);
        };
        (material?.existencias || []).forEach((existencia) => anadirFila(existencia.ubicacion, existencia.cantidad));
        if (!material && opciones.length > 0) {
            anadirFila(this.filtroUbicacion || "", 1);
        }
        grupo.append(leyenda, filas);
        if (opciones.length > 0) {
            grupo.appendChild(this.boton("+ Otra ubicación", "boton-cancelar", () => anadirFila()));
        } else {
            grupo.appendChild(this.parrafo("Crea antes alguna ubicación.", "sin-datos"));
        }
        formulario.appendChild(grupo);

        this.accionesFormulario(formulario, cerrar, () => this.enviar("guardar_material", {
            id: material?.id,
            nombre: nombre.value,
            unidad: unidad.value,
            notas: notas.value,
            existencias: Array.from(filas.children).map((fila) => ({
                ubicacion: fila.querySelector("select").value,
                cantidad: fila.querySelector("input").value
            }))
        }).then(() => {
            cerrar();
            this.render();
        }));
        nombre.focus();
    }

    // ---------- Pestaña Listas ----------

    renderListas() {
        const rejilla = document.createElement("div");
        rejilla.className = "rejilla-inventario rejilla-listas";
        [[false, "Listas", "Cada salida tiene su lista: qué llevamos, de dónde se coge y qué hay que comprar."],
            [true, "Plantillas", "Listas que se reutilizan cada año. Se pueden modificar y guardar como versión nueva."]]
            .forEach(([esPlantilla, titulo, ayuda]) => {
                const panel = document.createElement("section");
                panel.className = "panel-inventario vista-superficie";
                const encabezado = document.createElement("h3");
                encabezado.textContent = titulo;
                panel.append(encabezado, this.parrafo(ayuda, "ayuda-inventario"));
                if (!esPlantilla) {
                    panel.appendChild(this.boton("+ Nueva lista", "boton-crear", () => this.abrirLista({
                        id: null, nombre: "", es_plantilla: false, origen: null, lineas: [], compras: []
                    })));
                }
                const elementos = this.listas.filter((lista) => Boolean(lista.es_plantilla) === esPlantilla)
                    .sort((a, b) => String(b.actualizada).localeCompare(String(a.actualizada)));
                if (elementos.length === 0) {
                    panel.appendChild(this.parrafo(esPlantilla
                        ? "Todavía no hay plantillas. Abre una lista y pulsa «Guardar como plantilla»."
                        : "Todavía no hay listas.", "sin-datos"));
                }
                elementos.forEach((lista) => panel.appendChild(this.crearTarjetaLista(lista)));
                rejilla.appendChild(panel);
            });
        return rejilla;
    }

    crearTarjetaLista(lista) {
        const tarjeta = document.createElement("article");
        tarjeta.className = "tarjeta-lista";
        const nombre = document.createElement("strong");
        nombre.textContent = lista.nombre;
        const detalle = document.createElement("small");
        const origen = this.listas.find((otra) => otra.id === lista.origen);
        detalle.textContent = lista.lineas.length + " materiales · " + this.formatearFecha(lista.actualizada) +
            (lista.actualizada_por ? " · " + lista.actualizada_por : "") +
            (origen ? " · basada en «" + origen.nombre + "»" : "");
        const acciones = document.createElement("div");
        acciones.className = "acciones-lista";
        acciones.appendChild(this.boton("Abrir", "boton-editar", () => this.abrirLista(structuredClone(lista))));
        if (lista.es_plantilla) {
            acciones.appendChild(this.boton("Usar para una salida", "boton-crear", () => this.copiar(lista, false)));
        }
        acciones.appendChild(this.boton("Borrar", "boton-borrar", () => {
            if (confirm("¿Borrar «" + lista.nombre + "»?")) {
                this.enviar("borrar_lista", {id: lista.id}).then(() => this.render())
                    .catch((error) => alert(error.message));
            }
        }));
        tarjeta.append(nombre, detalle, acciones);
        return tarjeta;
    }

    copiar(lista, esPlantilla) {
        const nombre = prompt(
            esPlantilla ? "Nombre de la plantilla:" : "Nombre de la lista (por ejemplo «Acampada de verano 2027»):",
            esPlantilla ? lista.nombre : lista.nombre + " " + new Date().getFullYear()
        );
        if (!nombre) {
            return Promise.resolve();
        }
        return this.enviar("copiar_lista", {id: lista.id, nombre, es_plantilla: esPlantilla})
            .then((respuesta) => {
                const nueva = this.listas.find((elemento) => elemento.id === respuesta.id);
                this.abrirLista(structuredClone(nueva));
            })
            .catch((error) => alert(error.message));
    }

    abrirLista(lista) {
        this.lista = lista;
        this.cambiosSinGuardar = false;
        this.render();
    }

    lineaDe(materialId) {
        return this.lista.lineas.find((linea) => linea.material === materialId);
    }

    cogido(linea) {
        return linea.tomas.reduce((suma, toma) => suma + Number(toma.cantidad || 0), 0);
    }

    aComprar(linea) {
        return Math.max(0, Number(linea.necesarios || 0) - this.cogido(linea));
    }

    // Lo que la lista ya coge de un material en una ubicación.
    yaCogido(materialId, ubicacionId) {
        return Number(this.lineaDe(materialId)?.tomas.find((toma) => toma.ubicacion === ubicacionId)?.cantidad || 0);
    }

    // Pasa material de una ubicación a la lista (arrastrando o con «→»).
    mover(materialId, ubicacionId) {
        const material = this.material(materialId);
        const existencia = material?.existencias.find((elemento) => elemento.ubicacion === ubicacionId);
        if (!material || !existencia) {
            return;
        }
        const disponible = existencia.cantidad - this.yaCogido(materialId, ubicacionId);
        if (disponible <= 0) {
            alert("Ya te llevas todo lo que hay de «" + material.nombre + "» en " + this.ruta(ubicacionId) + ".");
            return;
        }
        this.pedirCantidad("Llevar material",
            "¿Cuántas de «" + material.nombre + "» coges de " + this.ruta(ubicacionId) + "? Quedan " + disponible + ".",
            disponible)
            .then((respuesta) => respuesta && this.anadirALista(material, ubicacionId, respuesta.cantidad));
    }

    anadirALista(material, ubicacionId, cantidad) {
        const materialId = material.id;
        let linea = this.lineaDe(materialId);
        if (!linea) {
            linea = {material: materialId, nombre: material.nombre, necesarios: 0, tomas: []};
            this.lista.lineas.push(linea);
        }
        const toma = linea.tomas.find((elemento) => elemento.ubicacion === ubicacionId);
        if (toma) {
            toma.cantidad = Number(toma.cantidad) + cantidad;
        } else {
            linea.tomas.push({ubicacion: ubicacionId, cantidad});
        }
        linea.necesarios = Math.max(Number(linea.necesarios || 0), this.cogido(linea));
        this.cambiosSinGuardar = true;
        this.render();
    }

    // Arrastrar una toma de vuelta al plano la quita de la lista (lo necesario se queda).
    quitarToma(materialId, ubicacionId) {
        const linea = this.lineaDe(materialId);
        if (!linea) {
            return;
        }
        linea.tomas = linea.tomas.filter((toma) => toma.ubicacion !== ubicacionId);
        this.cambiosSinGuardar = true;
        this.render();
    }

    renderEditorLista() {
        const contenedor = document.createElement("div");
        contenedor.className = "editor-lista";

        const barra = document.createElement("div");
        barra.className = "filtros-tabla barra-lista";
        const nombre = document.createElement("input");
        nombre.type = "text";
        nombre.className = "nombre-lista";
        nombre.placeholder = "Nombre de la lista";
        nombre.value = this.lista.nombre;
        nombre.setAttribute("aria-label", "Nombre de la lista");
        nombre.oninput = () => {
            this.lista.nombre = nombre.value;
            this.cambiosSinGuardar = true;
        };
        barra.appendChild(nombre);
        if (this.lista.es_plantilla) {
            barra.appendChild(this.parrafo("Plantilla", "etiqueta-plantilla"));
        }
        barra.append(
            this.boton("Guardar", "boton-crear", () => this.guardarLista()),
            this.boton(this.lista.es_plantilla ? "Guardar como versión nueva" : "Guardar como plantilla",
                "boton-editar", () => this.guardarLista().then((guardada) => guardada && this.copiar(guardada, true))),
            this.boton("Documento para imprimir", "boton-descargar", () => this.imprimir()),
            this.boton("Volver", "boton-cancelar", () => {
                if (this.cambiosSinGuardar && !confirm("Hay cambios sin guardar. ¿Volver igualmente?")) {
                    return;
                }
                this.lista = null;
                this.cambiosSinGuardar = false;
                this.render();
            })
        );
        if (this.lista.es_plantilla && this.lista.id) {
            barra.appendChild(this.boton("Usar para una salida", "boton-crear", () => this.copiar(this.lista, false)));
        }
        contenedor.appendChild(barra);

        const rejilla = document.createElement("div");
        rejilla.className = "rejilla-inventario rejilla-editor";
        rejilla.append(this.renderDisponible(), this.renderLlevamos());
        contenedor.appendChild(rejilla);
        return contenedor;
    }

    // Panel izquierdo: lo que hay y dónde, como plano. Las fichas muestran lo que queda
    // después de lo que ya coge la lista.
    renderDisponible() {
        const panel = document.createElement("section");
        panel.className = "panel-inventario vista-superficie";
        this.zonaSoltar(panel, (arrastre) => arrastre.tipo === "toma",
            (arrastre) => this.quitarToma(arrastre.material, arrastre.ubicacion));
        const titulo = document.createElement("h3");
        titulo.textContent = "Lo que hay";
        const buscador = this.buscadorPlano();
        const plano = this.crearPlano("lista");
        buscador.oninput = () => {
            this.busqueda = buscador.value;
            this.marcarBusqueda(plano);
        };
        panel.append(titulo,
            this.parrafo("Arrastra una ficha a la derecha o pulsa «→». Para quitar algo de la lista, " +
                "arrástralo de vuelta aquí.", "ayuda-inventario"),
            buscador, plano);
        return panel;
    }

    // Panel derecho: lo que nos llevamos y lo que hay que comprar.
    renderLlevamos() {
        const panel = document.createElement("section");
        panel.className = "panel-inventario vista-superficie zona-llevamos";
        this.zonaSoltar(panel, (arrastre) => arrastre.tipo === "material",
            (arrastre) => this.mover(arrastre.material, arrastre.ubicacion));
        const titulo = document.createElement("h3");
        titulo.textContent = "Lo que nos llevamos";
        panel.appendChild(titulo);

        if (this.lista.lineas.length === 0) {
            panel.appendChild(this.parrafo("Todavía no hay nada. Arrastra material aquí.", "sin-datos"));
        }
        this.lista.lineas.forEach((linea, indice) => panel.appendChild(this.crearLinea(linea, indice)));

        const tituloCompras = document.createElement("h3");
        tituloCompras.textContent = "Otras compras (lo que no tenemos en el inventario)";
        panel.appendChild(tituloCompras);
        this.lista.compras.forEach((compra, indice) => {
            const fila = document.createElement("div");
            fila.className = "fila-compra";
            const nombre = document.createElement("input");
            nombre.type = "text";
            nombre.value = compra.nombre;
            nombre.placeholder = "Qué hay que comprar";
            nombre.setAttribute("aria-label", "Qué hay que comprar");
            nombre.oninput = () => {
                compra.nombre = nombre.value;
                this.cambiosSinGuardar = true;
            };
            const cantidad = this.campoNumero(compra.cantidad, "Cantidad a comprar", (valor) => {
                compra.cantidad = valor;
            });
            fila.append(nombre, cantidad, this.boton("×", "boton-mini boton-mini-borrar", () => {
                this.lista.compras.splice(indice, 1);
                this.cambiosSinGuardar = true;
                this.render();
            }, "Quitar esta compra"));
            panel.appendChild(fila);
        });
        panel.appendChild(this.boton("+ Añadir compra", "boton-cancelar", () => {
            this.lista.compras.push({nombre: "", cantidad: 1});
            this.cambiosSinGuardar = true;
            this.render();
        }));
        return panel;
    }

    crearLinea(linea, indice) {
        const material = this.material(linea.material);
        const bloque = document.createElement("div");
        bloque.className = "linea-lista";
        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-linea";
        const nombre = document.createElement("strong");
        nombre.textContent = linea.nombre + (material ? "" : " (ya no está en el inventario)");
        const necesarios = this.campoNumero(linea.necesarios, "Necesarios de " + linea.nombre, (valor) => {
            linea.necesarios = valor;
            resumen.textContent = this.textoResumen(linea);
        });
        const etiqueta = document.createElement("label");
        etiqueta.className = "etiqueta-necesarios";
        etiqueta.append("Necesarios ", necesarios);
        cabecera.append(nombre, etiqueta, this.boton("×", "boton-mini boton-mini-borrar", () => {
            this.lista.lineas.splice(indice, 1);
            this.cambiosSinGuardar = true;
            this.render();
        }, "Quitar " + linea.nombre + " de la lista"));
        bloque.appendChild(cabecera);

        linea.tomas.forEach((toma, posicion) => {
            const existencia = material?.existencias.find((elemento) => elemento.ubicacion === toma.ubicacion);
            const fila = document.createElement("div");
            fila.className = "fila-toma";
            const cantidad = this.campoNumero(toma.cantidad, "Cantidad de " + this.ruta(toma.ubicacion), (valor) => {
                toma.cantidad = valor;
                resumen.textContent = this.textoResumen(linea);
            });
            cantidad.max = existencia?.cantidad ?? 0;
            const texto = document.createElement("span");
            texto.textContent = "de " + this.ruta(toma.ubicacion) + " (hay " + (existencia?.cantidad ?? 0) + ")";
            if (Number(toma.cantidad) > (existencia?.cantidad ?? 0)) {
                texto.classList.add("aviso-stock");
                texto.textContent += " · ya no hay tanto";
            }
            // Asa para arrastrar esta toma de vuelta al plano.
            const asa = document.createElement("span");
            asa.className = "asa-toma";
            asa.textContent = "⠿";
            asa.title = "Arrastra al plano para quitarlo de la lista";
            this.hacerArrastrable(asa, {tipo: "toma", material: linea.material, ubicacion: toma.ubicacion});
            fila.append(asa, "Coger ", cantidad, texto, this.boton("×", "boton-mini boton-mini-borrar", () => {
                linea.tomas.splice(posicion, 1);
                this.cambiosSinGuardar = true;
                this.render();
            }, "Quitar esta ubicación"));
            bloque.appendChild(fila);
        });
        const resumen = this.parrafo(this.textoResumen(linea), "resumen-linea");
        bloque.appendChild(resumen);
        return bloque;
    }

    textoResumen(linea) {
        const comprar = this.aComprar(linea);
        return "Se cogen " + this.cogido(linea) + (comprar > 0 ? " · hay que comprar " + comprar : " · no hay que comprar");
    }

    guardarLista() {
        if (!this.lista.nombre.trim()) {
            alert("Pon un nombre a la lista.");
            return Promise.resolve(null);
        }
        return this.enviar("guardar_lista", this.lista)
            .then((respuesta) => {
                const guardada = this.listas.find((lista) => lista.id === respuesta.id);
                this.lista = structuredClone(guardada);
                this.cambiosSinGuardar = false;
                this.render();
                return guardada;
            })
            .catch((error) => {
                alert(error.message);
                return null;
            });
    }

    // Documento para imprimir o guardar en PDF: qué coger agrupado por ubicación y qué comprar.
    imprimir() {
        const ventana = window.open("", "_blank");
        if (!ventana) {
            alert("El navegador ha bloqueado la ventana del documento.");
            return;
        }
        const documento = ventana.document;
        documento.title = this.lista.nombre || "Lista de material";
        const estilo = documento.createElement("style");
        estilo.textContent = "body{font-family:sans-serif;margin:24px;color:#222}" +
            "h1{margin:0 0 4px}h2{margin:24px 0 8px;font-size:18px}" +
            "table{border-collapse:collapse;width:100%;margin-bottom:8px}" +
            "th,td{border:1px solid #bbb;padding:6px 8px;text-align:left}" +
            "td.numero{text-align:right;width:90px}.casilla{width:30px}" +
            "@media print{button{display:none}}";
        documento.head.appendChild(estilo);
        const crear = (etiqueta, texto, padre) => {
            const elemento = documento.createElement(etiqueta);
            if (texto !== undefined) {
                elemento.textContent = texto;
            }
            padre.appendChild(elemento);
            return elemento;
        };
        const cuerpo = documento.body;
        crear("h1", this.lista.nombre || "Lista de material", cuerpo);
        crear("p", "Generada el " + new Date().toLocaleDateString("es-ES") +
            ". La lista no descuenta del inventario.", cuerpo);
        const boton = crear("button", "Imprimir o guardar como PDF", cuerpo);
        boton.onclick = () => ventana.print();

        // Qué coger, agrupado por ubicación para recogerlo de una vez.
        const porUbicacion = {};
        this.lista.lineas.forEach((linea) => linea.tomas.forEach((toma) => {
            if (Number(toma.cantidad) > 0) {
                (porUbicacion[this.ruta(toma.ubicacion)] ??= []).push([linea.nombre, toma.cantidad]);
            }
        }));
        crear("h2", "Material que hay que coger", cuerpo);
        if (Object.keys(porUbicacion).length === 0) {
            crear("p", "Nada.", cuerpo);
        }
        Object.keys(porUbicacion).sort((a, b) => a.localeCompare(b, "es")).forEach((ruta) => {
            crear("h3", ruta, cuerpo);
            const tabla = crear("table", undefined, cuerpo);
            const cabecera = crear("tr", undefined, tabla);
            ["✓", "Material", "Cantidad"].forEach((texto) => crear("th", texto, cabecera));
            porUbicacion[ruta].forEach(([nombre, cantidad]) => {
                const fila = crear("tr", undefined, tabla);
                crear("td", "☐", fila).className = "casilla";
                crear("td", nombre, fila);
                crear("td", String(cantidad), fila).className = "numero";
            });
        });

        const compras = [
            ...this.lista.lineas.filter((linea) => this.aComprar(linea) > 0)
                .map((linea) => [linea.nombre, this.aComprar(linea)]),
            ...this.lista.compras.filter((compra) => compra.nombre.trim())
                .map((compra) => [compra.nombre, compra.cantidad])
        ];
        crear("h2", "Hay que comprar", cuerpo);
        if (compras.length === 0) {
            crear("p", "Nada.", cuerpo);
        } else {
            const tabla = crear("table", undefined, cuerpo);
            const cabecera = crear("tr", undefined, tabla);
            ["✓", "Qué", "Cantidad"].forEach((texto) => crear("th", texto, cabecera));
            compras.forEach(([nombre, cantidad]) => {
                const fila = crear("tr", undefined, tabla);
                crear("td", "☐", fila).className = "casilla";
                crear("td", nombre, fila);
                crear("td", String(cantidad), fila).className = "numero";
            });
        }
    }

    // ---------- Piezas de interfaz ----------

    boton(texto, clase, accion, etiqueta) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "boton " + clase;
        boton.textContent = texto;
        if (etiqueta) {
            boton.setAttribute("aria-label", etiqueta);
            boton.title = etiqueta;
        }
        boton.onclick = accion;
        return boton;
    }

    parrafo(texto, clase) {
        const parrafo = document.createElement("p");
        parrafo.className = clase;
        parrafo.textContent = texto;
        return parrafo;
    }

    campoNumero(valor, etiqueta, alCambiar) {
        const campo = document.createElement("input");
        campo.type = "number";
        campo.min = 0;
        campo.step = 1;
        campo.className = "campo-cantidad";
        campo.value = valor;
        campo.setAttribute("aria-label", etiqueta);
        campo.oninput = () => {
            alCambiar(Math.max(0, parseInt(campo.value, 10) || 0));
            this.cambiosSinGuardar = true;
        };
        return campo;
    }

    abrirModal(texto) {
        const modal = document.querySelector("#modal-formulario");
        const contenedor = document.querySelector("#contenedor-formulario");
        contenedor.replaceChildren();
        const titulo = document.createElement("h2");
        titulo.id = "modal-formulario-titulo";
        titulo.textContent = texto;
        const formulario = document.createElement("form");
        formulario.className = "formulario vista-superficie vista-formulario";
        contenedor.append(titulo, formulario);
        modal.hidden = false;
        document.body.classList.add("modal-abierto");
        const cerrar = () => {
            modal.hidden = true;
            contenedor.replaceChildren();
            document.body.classList.remove("modal-abierto");
        };
        return {formulario, cerrar};
    }

    campoTexto(formulario, texto, valor, obligatorio) {
        const grupo = document.createElement("div");
        grupo.className = "campo";
        const etiqueta = document.createElement("label");
        etiqueta.textContent = texto;
        const control = document.createElement("input");
        control.type = "text";
        control.value = valor;
        control.required = obligatorio;
        etiqueta.appendChild(control);
        grupo.appendChild(etiqueta);
        formulario.appendChild(grupo);
        return control;
    }

    campoSelector(formulario, texto, opciones, valor) {
        const grupo = document.createElement("div");
        grupo.className = "campo";
        const etiqueta = document.createElement("label");
        etiqueta.textContent = texto;
        const control = document.createElement("select");
        opciones.forEach((opcion) => {
            const elemento = document.createElement("option");
            elemento.value = opcion.valor;
            elemento.textContent = opcion.texto;
            control.appendChild(elemento);
        });
        control.value = valor;
        etiqueta.appendChild(control);
        grupo.appendChild(etiqueta);
        formulario.appendChild(grupo);
        return control;
    }

    accionesFormulario(formulario, cerrar, alGuardar, textoGuardar = "Guardar") {
        const acciones = document.createElement("div");
        acciones.className = "acciones-formulario";
        const guardar = document.createElement("button");
        guardar.type = "submit";
        guardar.className = "boton boton-crear";
        guardar.textContent = textoGuardar;
        acciones.append(guardar, this.boton("Cancelar", "boton-cancelar", cerrar));
        formulario.appendChild(acciones);
        formulario.onsubmit = (envio) => {
            envio.preventDefault();
            alGuardar().catch((error) => alert(error.message));
        };
    }

    formatearFecha(fecha) {
        const [ano, mes, dia] = String(fecha || "").substring(0, 10).split("-");
        return dia ? dia + "/" + mes + "/" + ano : "";
    }
}
