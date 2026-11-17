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
            if (datos.tutor) {
                this.contenedor.appendChild(this.renderPanelTutor(
                    datos.tutor,
                    datos.mini_calendario
                ));
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
            [
                "Cumpleaños (" + this.formatearDiaMes(resumen.semana_desde) + " – " +
                    this.formatearDiaMes(resumen.semana_hasta) + ")",
                cumpleanos.length,
                cumpleanos.map((cumple) =>
                    `${cumple.nombre}, ${cumple.edad} (${this.formatearDiaMes(cumple.fecha)})`
                ).join(" · ") || "Ninguno esta semana"
            ]
        ];
        tarjetas.forEach(([nombre, valor, detalle]) => {
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

    // Asistencias del próximo sábado (2026-11-17): una gráfica de anillo por sección con
    // los tonos de su color, de claro a oscuro: asiste y ha entregado la circular, asiste
    // sin entregarla, no ha respondido y no asiste.
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
            ? ["Asiste y entregó", "Asiste, sin circ.", "Sin responder", "No asiste"]
            : ["Asiste", "Sin responder", "No asiste"];
        const rejilla = document.createElement("div");
        rejilla.className = "graficas-secciones";
        this.ordenarSecciones(Object.keys(datos.secciones || {})).forEach((seccion) => {
            const grupos = datos.secciones[seccion];
            const valores = conCircular
                ? [grupos.asiste_entregada, grupos.asiste_sin_circular, grupos.pendiente, grupos.no]
                : [grupos.asiste_entregada, grupos.pendiente, grupos.no];
            const tonos = this.tonosSeccion(seccion);
            const tarjeta = document.createElement("article");
            tarjeta.className = "grafica-card vista-superficie";
            rejilla.appendChild(tarjeta);
            this.graficas.crear(
                tarjeta,
                "anillo",
                etiquetas,
                valores,
                this.nombreSeccion(seccion),
                conCircular ? tonos : [tonos[0], tonos[2], tonos[3]]
            );
        });
        zona.appendChild(rejilla);
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

    // Color de cada sección: el mismo matiz que su fondo en las tablas (estilo.css),
    // más intenso para que se distingan los tonos. Las secciones nuevas usan el del grupo.
    tonosSeccion(seccion) {
        const base = {
            colonia: "#ef6c00",
            manada: "#f2b705",
            tropa: "#1e63c6",
            posta: "#d32f2f",
            rutas: "#2e7d32"
        }[seccion] || getComputedStyle(document.documentElement)
            .getPropertyValue("--color_corporativo").trim() || "#8b0000";
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

    renderPanelTutor(datos = {}, eventos = []) {
        const zona = document.createElement("section");
        zona.className = "dashboard-bloque dashboard-tutor";
        const titulo = document.createElement("h3");
        titulo.textContent = "Pendientes y calendario";
        zona.appendChild(titulo);
        const pendientes = document.createElement("div");
        pendientes.className = "dashboard-pendientes";
        [
            ["Circulares sin rellenar", datos.circulares_pendientes || 0],
            ["Asistencias sin confirmar", datos.asistencias_pendientes || 0]
        ].forEach(([nombre, valor]) => {
            const elemento = document.createElement("article");
            elemento.innerHTML = "<strong></strong><b></b>";
            elemento.querySelector("strong").textContent = nombre;
            elemento.querySelector("b").textContent = valor;
            pendientes.appendChild(elemento);
        });
        zona.appendChild(pendientes);
        const calendario = document.createElement("div");
        calendario.className = "mini-calendario";
        eventos.forEach((evento) => {
            const dia = document.createElement("div");
            dia.innerHTML = "<b></b><span></span>";
            dia.querySelector("b").textContent =
                this.formatearFecha(evento.fecha);
            dia.querySelector("span").textContent = evento.nombre;
            calendario.appendChild(dia);
        });
        if (!eventos.length) {
            calendario.textContent = "No hay eventos próximos.";
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
