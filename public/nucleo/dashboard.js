class IngeriarDashboard {
    constructor({contenedor = "section", utilidades, graficas, exportacion}) {
        this.contenedor = typeof contenedor === "string"
            ? document.querySelector(contenedor)
            : contenedor;
        this.utilidades = utilidades;
        this.graficas = graficas;
        this.exportacion = exportacion;
    }

    nombreSeccion(codigo) {
        try {
            const secciones = JSON.parse(
                document.documentElement.dataset.secciones || "[]"
            );
            return secciones.find((seccion) => seccion.codigo === codigo)
                ?.nombre || codigo;
        } catch (error) {
            return codigo;
        }
    }

    async render() {
        this.contenedor.innerHTML =
            '<p class="vacio">Cargando dashboard...</p>';

        try {
            const datos = await this.cargarDatos();
            this.contenedor.className = "vista vista-dashboard";
            this.contenedor.innerHTML = "";
            this.renderCabecera(datos.rol);
            this.contenedor.appendChild(this.renderResumen(datos.resumen));
            // Los bloques dependen de los permisos (E-13): aparecen si el servidor los envía.
            if (datos.asistencias_sabado) {
                this.contenedor.appendChild(
                    this.renderAsistenciasSabado(datos.asistencias_sabado)
                );
            }
            // Tutor: pendientes y calendario en dos bloques separados.
            if (datos.tutor) {
                this.contenedor.appendChild(this.renderPendientesTutor(datos.tutor.pendientes || []));
                this.contenedor.appendChild(this.renderCalendarioTutor(datos.mini_calendario || []));
            }
            if (datos.tesoreria) {
                this.contenedor.appendChild(this.renderFacturasTesoreria(datos.tesoreria));
            }
        } catch (error) {
            this.contenedor.innerHTML = "";

            const mensaje = document.createElement("p");
            mensaje.className = "sin-datos";
            mensaje.textContent =
                "No se pudo cargar el dashboard: " + error.message;
            this.contenedor.appendChild(mensaje);
        }
    }

    cargarDatos() {
        return this.utilidades.obtenerJson("api/superapi.php?ruta=dashboard");
    }

    renderCabecera(rol) {
        const cabecera = document.createElement("div");
        cabecera.className = "cabecera-dashboard";

        const exportacion = document.createElement("div");
        exportacion.className = "controles-exportacion";
        const formato = document.createElement("select");
        formato.className = "selector-exportacion";
        formato.setAttribute("aria-label", "Formato de exportación");
        [
            ["", "Exportar como..."],
            ["imprimir", "Imprimir"],
            ["html", "HTML"],
            ["json", "JSON"],
            ["csv", "CSV"]
        ].forEach(([valor, texto]) => {
            const opcion = document.createElement("option");
            opcion.value = valor;
            opcion.textContent = texto;
            formato.appendChild(opcion);
        });
        const botonExportar = document.createElement("button");
        botonExportar.textContent = "Exportar";
        botonExportar.className = "boton boton-exportar";
        exportacion.append(formato, botonExportar);
        this.exportacion.conectarControles(formato, botonExportar);

        const titulo = document.createElement("h2");
        titulo.textContent = "Dashboard";

        const descripcion = document.createElement("p");
        descripcion.textContent = {
            administrador: "Visión general de la gestión y los recursos.",
            scouter: "Resumen de tus actividades y gastos registrados.",
            tutor: "Información importante sobre tus educandos y actividades."
        }[rol] || "Resumen de la actividad.";

        const textos = document.createElement("div");
        textos.append(titulo, descripcion);
        cabecera.append(textos, exportacion);
        this.contenedor.appendChild(cabecera);
    }

    renderResumen(resumen = {}) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-resumen";
        const titulo = document.createElement("h3");
        titulo.textContent = "Próximas actividades";
        zona.appendChild(titulo);
        // Próximo sábado, próxima acampada (con cuánto falta), próximo evento de otro
        // tipo y cumpleaños de la semana, de domingo a sábado.
        const evento = (dato, vacio, detalle) => dato
            ? [dato.nombre, detalle(dato)]
            : [vacio, ""];
        const fecha = (dato) => this.formatearFecha(dato.fecha_inicio);
        const cumpleanos = resumen.cumpleanos || [];
        const tarjetas = [
            ["Próximo sábado", ...evento(resumen.proximo_sabado, "No hay sábados", fecha)],
            ["Próxima acampada", ...evento(resumen.proxima_acampada, "No hay acampadas",
                (dato) => this.tiempoHasta(dato.fecha_inicio))],
            ["Próximo evento", ...evento(resumen.proximo_otro, "No hay otros eventos", fecha)],
            // Los tutores no reciben cumpleaños (null): su tarjeta no aparece.
            resumen.cumpleanos === null ? null : [
                "Cumpleaños (" + this.formatearDiaMes(resumen.semana_desde) + " – " +
                    this.formatearDiaMes(resumen.semana_hasta) + ")",
                cumpleanos.length,
                cumpleanos.map((cumple) =>
                    `${cumple.nombre}, ${cumple.edad} (${this.formatearDiaMes(cumple.fecha)})`
                ).join(" · ") || "Ninguno esta semana"
            ]
        ];
        tarjetas.filter(Boolean).forEach(([nombre, valor, detalle]) => {
            const tarjeta = document.createElement("article");
            tarjeta.className = "dashboard-info-tarjeta";
            tarjeta.innerHTML = `<strong></strong><b></b><span></span>`;
            tarjeta.querySelector("strong").textContent = nombre;
            tarjeta.querySelector("b").textContent = valor;
            tarjeta.querySelector("span").textContent = detalle;
            zona.appendChild(tarjeta);
        });
        return zona;
    }

    // Asistencias del próximo sábado (2026-11-17): un anillo doble con las secciones por
    // dentro y, por fuera, sus tramos con los tonos de su color, de claro a oscuro: asiste y
    // ha entregado la circular, asiste sin entregarla, no ha respondido y no asiste.
    renderAsistenciasSabado(datos = {}) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-asistencias";
        const titulo = document.createElement("h3");
        titulo.textContent = datos.evento
            ? `Asistencias del sábado: ${datos.evento.nombre} (${this.formatearFecha(datos.evento.fecha_inicio)})`
            : "Asistencias del próximo sábado";
        zona.appendChild(titulo);
        if (!datos.evento) {
            const mensaje = document.createElement("p");
            mensaje.textContent = "No hay un sábado próximo en el calendario.";
            zona.appendChild(mensaje);
            return zona;
        }
        const conCircular = Boolean(datos.evento.circular);
        const etiquetas = conCircular
            ? ["Asiste y entregó", "Asiste sin circular", "Sin responder", "No asiste"]
            : ["Asiste", "Sin responder", "No asiste"];
        // Un solo anillo doble: secciones dentro y, por fuera, sus tramos con los tonos de su color.
        const codigos = this.ordenarSecciones(Object.keys(datos.secciones || {}));
        const grupos = codigos.map((seccion) => {
            const tramos = datos.secciones[seccion];
            const tonos = this.tonosSeccion(seccion);
            return {
                nombre: this.nombreSeccion(seccion),
                color: tonos[2],
                tonos: conCircular ? tonos : [tonos[0], tonos[2], tonos[3]],
                valores: conCircular
                    ? [tramos.asiste_entregada, tramos.asiste_sin_circular, tramos.pendiente, tramos.no]
                    : [tramos.asiste_entregada, tramos.pendiente, tramos.no]
            };
        });
        const tarjeta = document.createElement("article");
        tarjeta.className = "grafica-card vista-superficie";
        // Al pulsar una sección (en el anillo o en la tabla) se ve ampliada; «Todas» vuelve.
        const mostrarTodas = () => {
            tarjeta.replaceChildren();
            const ayuda = document.createElement("p");
            ayuda.className = "ayuda-grafica";
            ayuda.textContent = "Pulsa una sección para ver su detalle.";
            tarjeta.appendChild(ayuda);
            const grafica = document.createElement("div");
            tarjeta.appendChild(grafica);
            this.graficas.crearAnilloAgrupado(grafica, grupos, etiquetas, "educandos",
                (indice) => mostrarSeccion(indice));
        };
        const claves = conCircular
            ? ["asiste_entregada", "asiste_sin_circular", "pendiente", "no"]
            : ["asiste_entregada", "pendiente", "no"];
        const mostrarSeccion = (indice) => {
            const grupo = grupos[indice];
            const codigo = codigos[indice];
            const nombres = (datos.detalle || {})[codigo] || {};
            tarjeta.replaceChildren();
            const cabecera = document.createElement("div");
            cabecera.className = "cabecera-detalle-seccion";
            const volver = document.createElement("button");
            volver.type = "button";
            volver.className = "boton boton-cancelar";
            volver.textContent = "← Todas las secciones";
            volver.onclick = mostrarTodas;
            const titulo = document.createElement("h4");
            const muestra = document.createElement("span");
            muestra.className = "muestra-color";
            muestra.style.background = grupo.color;
            titulo.append(muestra, grupo.nombre);
            cabecera.append(volver, titulo);
            const partes = etiquetas.map((etiqueta, posicion) => ({
                nombre: etiqueta,
                color: grupo.tonos[posicion],
                valor: grupo.valores[posicion],
                personas: nombres[claves[posicion]] || []
            }));
            const cuerpo = document.createElement("div");
            cuerpo.className = "detalle-seccion-sabado";
            cuerpo.appendChild(this.graficas.crearAnilloSimple(partes, "educandos"));
            // Quién está en cada tramo (para saber a quién falta que responda o que entregue).
            const listas = document.createElement("div");
            listas.className = "tramos-seccion-sabado";
            partes.forEach((parte) => {
                const tramo = document.createElement("section");
                tramo.className = "tramo-seccion-sabado";
                const encabezado = document.createElement("h5");
                const tono = document.createElement("span");
                tono.className = "muestra-color";
                tono.style.background = parte.color;
                encabezado.append(tono, parte.nombre + " (" + parte.valor + ")");
                const lista = document.createElement("ul");
                parte.personas.forEach((persona) => {
                    const elemento = document.createElement("li");
                    elemento.textContent = persona;
                    lista.appendChild(elemento);
                });
                if (!parte.personas.length) {
                    const nadie = document.createElement("li");
                    nadie.className = "sin-personas";
                    nadie.textContent = "Nadie";
                    lista.appendChild(nadie);
                }
                tramo.append(encabezado, lista);
                listas.appendChild(tramo);
            });
            cuerpo.appendChild(listas);
            tarjeta.append(cabecera, cuerpo);
        };
        mostrarTodas();
        zona.appendChild(tarjeta);
        return zona;
    }

    // Secciones en el orden del catálogo.
    ordenarSecciones(codigos) {
        let orden = [];
        try {
            orden = JSON.parse(document.documentElement.dataset.secciones || "[]")
                .map((seccion) => seccion.codigo);
        } catch (error) {
            orden = [];
        }
        const posicion = (codigo) => orden.includes(codigo) ? orden.indexOf(codigo) : orden.length;
        return codigos.sort((a, b) => posicion(a) - posicion(b));
    }

    // Color de cada sección: el de su catálogo (Configuración, E-18). Sin color, el del grupo.
    tonosSeccion(seccion) {
        let catalogo = [];
        try {
            catalogo = JSON.parse(document.documentElement.dataset.secciones || "[]");
        } catch (error) {
            catalogo = [];
        }
        const base = catalogo.find((elemento) => elemento.codigo === seccion)?.color ||
            getComputedStyle(document.documentElement).getPropertyValue("--color_corporativo").trim() ||
            "#8b0000";
        // De claro a oscuro: mezcla con blanco y después con negro.
        return [
            this.mezclarColor(base, "#ffffff", 0.6),
            this.mezclarColor(base, "#ffffff", 0.25),
            base,
            this.mezclarColor(base, "#000000", 0.45)
        ];
    }

    mezclarColor(color, otro, proporcion) {
        const rgb = (hex) => {
            const valor = hex.replace("#", "");
            const completo = valor.length === 3 ? valor.split("").map((c) => c + c).join("") : valor;
            return [0, 2, 4].map((posicion) => parseInt(completo.substring(posicion, posicion + 2), 16));
        };
        const [a, b] = [rgb(color), rgb(otro)];
        if (a.some(Number.isNaN)) {
            return color;
        }
        return "#" + a.map((componente, indice) => Math.round(componente + (b[indice] - componente) * proporcion)
            .toString(16).padStart(2, "0")).join("");
    }

    // Pendientes del tutor (2026-11-17): eventos de este mes de la sección de cada
    // educando en los que falta confirmar la asistencia o entregar la circular. Cada
    // ficha lleva el color de la sección del educando.
    renderPendientesTutor(pendientes) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-tutor";
        const titulo = document.createElement("h3");
        titulo.textContent = "Pendientes de este mes";
        zona.appendChild(titulo);
        if (!pendientes.length) {
            const vacio = document.createElement("p");
            vacio.className = "sin-datos";
            vacio.textContent = "No tienes nada pendiente este mes.";
            zona.appendChild(vacio);
            return zona;
        }
        // Una tarjeta por evento con una ficha por educando dentro (el servidor ya las envía
        // ordenadas por fecha, así que los eventos salen en orden).
        const eventos = new Map();
        pendientes.forEach((pendiente) => {
            if (!eventos.has(pendiente.evento_id)) {
                eventos.set(pendiente.evento_id, []);
            }
            eventos.get(pendiente.evento_id).push(pendiente);
        });
        const lista = document.createElement("div");
        lista.className = "lista-pendientes-tutor";
        const etiqueta = (texto, clase) => {
            const elemento = document.createElement("span");
            elemento.className = "etiqueta-pendiente " + clase;
            elemento.textContent = texto;
            return elemento;
        };
        eventos.forEach((delEvento) => {
            const primero = delEvento[0];
            const tarjeta = document.createElement("article");
            // Con tres educandos o más, la tarjeta ocupa dos columnas para que quepan en una fila.
            tarjeta.className = "evento-pendiente vista-superficie" + (delEvento.length >= 3 ? " evento-pendiente-ancho" : "");
            const cabecera = document.createElement("div");
            cabecera.className = "cabecera-evento-pendiente";
            const nombre = document.createElement("strong");
            nombre.textContent = primero.evento;
            const fecha = document.createElement("span");
            fecha.className = "fecha-evento-pendiente";
            fecha.textContent = this.formatearDiaMes(primero.fecha);
            cabecera.append(nombre, fecha);
            // Hasta cuándo se puede responder (plazo del evento o de su tipo, E-18).
            if (primero.cierra) {
                const cierra = document.createElement("span");
                cierra.className = "fecha-evento-pendiente";
                cierra.textContent = "· responde antes del " + this.formatearDiaMes(primero.cierra) +
                    " a las " + primero.cierra.substring(11, 16);
                cabecera.appendChild(cierra);
            }
            if (primero.circular) {
                cabecera.appendChild(etiqueta("Necesita circular", "etiqueta-circular"));
            }
            const hijos = document.createElement("div");
            hijos.className = "educandos-evento-pendiente";
            delEvento.forEach((pendiente) => {
                const ficha = document.createElement("div");
                // Mismo color de fondo que las filas de la sección en la tabla de educandos.
                ficha.className = "pendiente-tutor fila-seccion-" + pendiente.seccion;
                // Dentro de una familia basta el nombre; el completo, al pasar el ratón.
                const educando = document.createElement("strong");
                educando.textContent = pendiente.nombre || pendiente.educando;
                ficha.title = pendiente.educando;
                ficha.append(educando, pendiente.motivo === "confirmar"
                    ? etiqueta("Confirmar asistencia", "etiqueta-confirmar")
                    : etiqueta("Falta la circular", "etiqueta-falta-documentacion"));
                hijos.appendChild(ficha);
            });
            tarjeta.append(cabecera, hijos);
            lista.appendChild(tarjeta);
        });
        zona.appendChild(lista);
        return zona;
    }

    // Tesorería: dos columnas con las facturas por revisar y por pagar (mismos colores que
    // el tablero de facturas).
    renderFacturasTesoreria(tesoreria) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-tesoreria";
        const titulo = document.createElement("h3");
        titulo.textContent = "Facturas";
        zona.appendChild(titulo);
        const columnas = document.createElement("div");
        columnas.className = "columnas-tesoreria";
        [
            ["Pendientes de revisar", tesoreria.por_revisar || [], "pendiente"],
            ["Pendientes de pagar", tesoreria.por_pagar || [], "revisada"],
        ].forEach(([texto, facturas, estado]) => {
            const columna = document.createElement("section");
            columna.className = "columna-facturas columna-" + estado;
            const cabecera = document.createElement("h3");
            const total = facturas.reduce((suma, factura) => suma + Number(factura.precio), 0);
            cabecera.textContent = texto + " (" + facturas.length + ") · " + this.formatearImporte(total);
            columna.appendChild(cabecera);
            // Se muestran las más antiguas; el resto se consulta en Facturas.
            const maximo = 8;
            facturas.slice(0, maximo).forEach((factura) => {
                const ficha = document.createElement("article");
                ficha.className = "tarjeta-factura factura-dashboard";
                const concepto = document.createElement("strong");
                concepto.textContent = factura.concepto;
                const importe = document.createElement("span");
                importe.className = "importe-factura-dashboard";
                importe.textContent = this.formatearImporte(factura.precio);
                const detalle = document.createElement("small");
                detalle.textContent = factura.responsable + " · " + this.formatearFecha(factura.fecha_compra);
                ficha.append(concepto, importe, detalle);
                columna.appendChild(ficha);
            });
            if (facturas.length > maximo) {
                const resto = document.createElement("p");
                resto.className = "resto-facturas";
                resto.textContent = "Y " + (facturas.length - maximo) + " más en el apartado Facturas.";
                columna.appendChild(resto);
            }
            if (!facturas.length) {
                const vacio = document.createElement("p");
                vacio.className = "sin-datos";
                vacio.textContent = "Ninguna.";
                columna.appendChild(vacio);
            }
            columnas.appendChild(columna);
        });
        zona.appendChild(columnas);
        return zona;
    }

    formatearImporte(valor) {
        return Number(valor).toLocaleString("es-ES", {style: "currency", currency: "EUR"});
    }

    // Calendario del tutor: próximos eventos del trimestre de las secciones de sus educandos.
    renderCalendarioTutor(eventos) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-tutor";
        const titulo = document.createElement("h3");
        titulo.textContent = "Calendario del trimestre";
        zona.appendChild(titulo);
        const calendario = document.createElement("div");
        calendario.className = "mini-calendario";
        eventos.forEach((evento) => {
            const dia = document.createElement("div");
            dia.innerHTML = "<b></b><span></span>";
            dia.querySelector("b").textContent = this.formatearFecha(evento.fecha);
            dia.querySelector("span").textContent = evento.nombre;
            calendario.appendChild(dia);
        });
        if (!eventos.length) {
            calendario.textContent = "No hay más eventos este trimestre.";
        }
        zona.appendChild(calendario);
        return zona;
    }

    formatearFecha(valor) {
        return new Date(String(valor).replace(" ", "T"))
            .toLocaleDateString("es-ES", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric"
            });
    }

    // «dom 15/11»
    formatearDiaMes(valor) {
        if (!valor) {
            return "";
        }
        return new Date(String(valor).substring(0, 10) + "T00:00:00")
            .toLocaleDateString("es-ES", {weekday: "short", day: "2-digit", month: "2-digit"});
    }

    tiempoHasta(valor) {
        const diferencia = new Date(String(valor).replace(" ", "T")) - new Date();
        if (diferencia <= 0) {
            return "Ya ha comenzado";
        }
        const dias = Math.ceil(diferencia / 86400000);
        return `Faltan ${dias} día${dias === 1 ? "" : "s"} (${this.formatearFecha(valor)})`;
    }
}
