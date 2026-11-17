// Dashboard (revisado con el usuario el 2026-11-17). Los bloques dependen de lo que
// envía api/dashboard.php según los permisos:
//   todos: próximas actividades (sábados, acampadas y otros eventos)
//   monitores: respuestas del próximo sábado y cumpleaños de la semana
//   tesorería: facturas pendientes de revisar y de pagar
//   tutores: asistencias y circulares pendientes y calendario por trimestres
class IngeriarDashboard {
    constructor({contenedor = "section", utilidades, graficas}) {
        this.contenedor = typeof contenedor === "string"
            ? document.querySelector(contenedor)
            : contenedor;
        this.utilidades = utilidades;
        this.graficas = graficas;
        this.trimestre = null;
    }

    nombreSeccion(codigo) {
        if (codigo === "sin_seccion") {
            return "Sin sección";
        }
        try {
            const secciones = JSON.parse(document.documentElement.dataset.secciones || "[]");
            return secciones.find((seccion) => seccion.codigo === codigo)?.nombre || codigo;
        } catch (error) {
            return codigo;
        }
    }

    async render() {
        this.contenedor.innerHTML = '<p class="vacio">Cargando dashboard...</p>';
        try {
            const datos = await this.utilidades.obtenerJson("api/dashboard.php");
            this.datos = datos;
            this.contenedor.className = "vista vista-dashboard";
            this.contenedor.replaceChildren(this.renderCabecera(datos.rol), this.renderProximas(datos.proximas));
            if (datos.tutor) {
                this.contenedor.appendChild(this.renderTutor(datos.tutor));
            }
            if (datos.sabado) {
                this.contenedor.appendChild(this.renderSabado(datos.sabado));
            }
            if (datos.cumpleanos) {
                this.contenedor.appendChild(this.renderCumpleanos(datos.cumpleanos));
            }
            if (datos.facturas) {
                this.contenedor.appendChild(this.renderFacturas(datos.facturas));
            }
            if (datos.graficas?.length) {
                this.contenedor.appendChild(this.renderGraficas(datos.graficas));
            }
        } catch (error) {
            this.contenedor.replaceChildren(this.crear("p", "sin-datos", "No se pudo cargar el dashboard: " + error.message));
        }
    }

    // ---------- Piezas comunes ----------

    crear(etiqueta, clase, texto) {
        const elemento = document.createElement(etiqueta);
        if (clase) {
            elemento.className = clase;
        }
        if (texto !== undefined) {
            elemento.textContent = texto;
        }
        return elemento;
    }

    bloque(titulo, clase = "") {
        const zona = this.crear("section", "dashboard-bloque " + clase);
        zona.appendChild(this.crear("h3", "", titulo));
        return zona;
    }

    // Lista con una línea por elemento; si está vacía, muestra el texto indicado.
    lista(elementos, textoVacio, pintar) {
        if (!elementos.length) {
            return this.crear("p", "sin-datos", textoVacio);
        }
        const lista = this.crear("ul", "lista-dashboard");
        elementos.forEach((elemento) => {
            const linea = this.crear("li");
            pintar(elemento, linea);
            lista.appendChild(linea);
        });
        return lista;
    }

    renderCabecera(rol) {
        const cabecera = this.crear("div", "cabecera-dashboard");
        const textos = this.crear("div");
        textos.append(
            this.crear("h2", "", "Dashboard"),
            this.crear("p", "", {
                administrador: "Lo próximo y lo que hay que revisar en el grupo.",
                scouter: "Lo próximo, el sábado y los cumpleaños de la semana.",
                tutor: "Lo próximo y lo que tienes pendiente de tus educandos."
            }[rol] || "Resumen de la actividad.")
        );
        cabecera.appendChild(textos);
        return cabecera;
    }

    // ---------- Todos: próximas actividades ----------

    renderProximas(proximas = {}) {
        const zona = this.bloque("Próximas actividades", "dashboard-resumen");
        [
            ["sabados", "Sábados", "No hay sábados próximos."],
            ["acampadas", "Acampadas", "No hay acampadas próximas."],
            ["otros", "Otros eventos", "No hay otros eventos próximos."]
        ].forEach(([clave, titulo, vacio]) => {
            const tarjeta = this.crear("article", "dashboard-info-tarjeta");
            tarjeta.appendChild(this.crear("strong", "", titulo));
            tarjeta.appendChild(this.lista(proximas[clave] || [], vacio, (evento, linea) => {
                linea.append(this.crear("b", "", evento.nombre), " ");
                // En las acampadas importa cuánto falta (hay que preparar la documentación).
                linea.append(this.crear("span", "", clave === "acampadas"
                    ? this.tiempoHasta(evento.fecha_inicio)
                    : this.formatearFecha(evento.fecha_inicio)));
                if (evento.circular) {
                    linea.append(this.crear("span", "etiqueta-circular", "circular"));
                }
            }));
            zona.appendChild(tarjeta);
        });
        return zona;
    }

    // ---------- Monitores: próximo sábado ----------

    renderSabado(datos) {
        const zona = this.bloque(datos.evento
            ? "Próximo sábado: " + datos.evento.nombre + " (" + this.formatearFecha(datos.evento.fecha_inicio) + ")"
            : "Próximo sábado");
        if (!datos.evento) {
            zona.appendChild(this.crear("p", "sin-datos", "No hay un sábado próximo en el calendario."));
            return zona;
        }
        const totales = datos.totales;
        zona.appendChild(this.crear("p", "resumen-sabado",
            totales.si + " sí · " + totales.no + " no · " + totales.pendiente + " pendientes" +
            (datos.evento.circular ? " · lleva circular" : "")));
        const tabla = this.crear("table", "tabla-dashboard tabla-sabado");
        const cabecera = this.crear("tr");
        ["Sección", "Sí", "No", "Pendientes"].forEach((texto) => cabecera.appendChild(this.crear("th", "", texto)));
        tabla.appendChild(this.crear("thead")).appendChild(cabecera);
        const cuerpo = tabla.appendChild(this.crear("tbody"));
        Object.entries(datos.secciones).forEach(([seccion, estados]) => {
            const fila = this.crear("tr");
            fila.appendChild(this.crear("th", "", this.nombreSeccion(seccion)));
            ["si", "no", "pendiente"].forEach((estado) => {
                const celda = this.crear("td");
                const personas = estados[estado] || [];
                // El número y, al desplegar, los nombres (con la circular si hace falta).
                const detalles = this.crear("details");
                detalles.appendChild(this.crear("summary", "", String(personas.length)));
                detalles.appendChild(this.lista(personas, "Nadie", (persona, linea) => {
                    linea.textContent = persona.nombre;
                    if (persona.circular) {
                        linea.append(" ", this.crear("span", persona.circular === "entregada"
                            ? "etiqueta-entregada" : "etiqueta-falta",
                        persona.circular === "entregada" ? "circular entregada" : "falta circular"));
                    }
                }));
                celda.appendChild(personas.length ? detalles : this.crear("span", "", "0"));
                fila.appendChild(celda);
            });
            cuerpo.appendChild(fila);
        });
        zona.appendChild(tabla);
        return zona;
    }

    // ---------- Monitores: cumpleaños de la semana ----------

    renderCumpleanos(datos) {
        const zona = this.bloque("Cumpleaños de esta semana (" + this.formatearFecha(datos.desde) +
            " – " + this.formatearFecha(datos.hasta) + ")", "dashboard-cumpleanos");
        const hoy = new Date().toISOString().substring(0, 10);
        const pintar = (persona, linea) => {
            linea.append(this.crear("b", "", persona.nombre), " cumple " + persona.edad + " el " +
                this.formatearFecha(persona.fecha));
            if (persona.seccion) {
                linea.append(" · " + this.nombreSeccion(persona.seccion));
            }
            if (persona.fecha === hoy) {
                linea.append(" ", this.crear("span", "etiqueta-circular", "¡hoy!"));
            } else if (persona.fecha < hoy) {
                linea.classList.add("pasado");
            }
        };
        [["Educandos", datos.educandos], ["Monitores", datos.monitores]].forEach(([titulo, personas]) => {
            const tarjeta = this.crear("article", "dashboard-info-tarjeta");
            tarjeta.append(this.crear("strong", "", titulo), this.lista(personas, "Nadie esta semana.", pintar));
            zona.appendChild(tarjeta);
        });
        return zona;
    }

    // ---------- Tesorería: facturas ----------

    renderFacturas(datos) {
        const zona = this.bloque("Facturas", "dashboard-facturas");
        [["Pendientes de revisar", datos.por_revisar], ["Pendientes de pagar", datos.por_pagar]].forEach(([titulo, grupo]) => {
            const tarjeta = this.crear("article", "dashboard-info-tarjeta");
            tarjeta.append(
                this.crear("strong", "", titulo),
                this.crear("b", "", grupo.cantidad + " · " + this.formatearImporte(grupo.total)),
                this.lista(grupo.antiguas, "Ninguna.", (factura, linea) => {
                    linea.append(this.crear("b", "", factura.concepto), " " + this.formatearImporte(factura.precio) +
                        " · " + factura.responsable + " · " + this.formatearFecha(factura.fecha));
                })
            );
            if (grupo.cantidad > grupo.antiguas.length) {
                tarjeta.appendChild(this.crear("span", "", "Y " + (grupo.cantidad - grupo.antiguas.length) +
                    " más en la vista Facturas."));
            }
            zona.appendChild(tarjeta);
        });
        return zona;
    }

    // ---------- Tutores ----------

    renderTutor(datos) {
        const zona = this.bloque("Mis educandos", "dashboard-tutor");
        const pendientes = this.crear("div", "dashboard-pendientes");
        // Se muestran los más próximos; el resto se resume en «y N más».
        const MAXIMO = 8;
        const tarjeta = (titulo, elementos, vacio, pintar) => {
            const articulo = this.crear("article", "dashboard-info-tarjeta");
            articulo.append(this.crear("strong", "", titulo + " (" + elementos.length + ")"),
                this.lista(elementos.slice(0, MAXIMO), vacio, pintar));
            if (elementos.length > MAXIMO) {
                articulo.appendChild(this.crear("span", "", "Y " + (elementos.length - MAXIMO) + " más."));
            }
            pendientes.appendChild(articulo);
        };
        const eventoDe = (entrada, linea) => linea.append(this.crear("b", "", entrada.educando), " · " +
            entrada.evento + " · " + this.formatearFecha(entrada.fecha));
        tarjeta("Asistencias por responder", datos.asistencias_pendientes, "Todo respondido.", eventoDe);
        tarjeta("Circulares de actividades por entregar", datos.circulares_actividades, "Ninguna pendiente.", eventoDe);
        // Fichas básicas agrupadas por educando: una línea por hijo con lo que le falta.
        const porEducando = {};
        datos.fichas_basicas.forEach((ficha) => (porEducando[ficha.educando] ??= []).push(ficha));
        const fichas = Object.entries(porEducando).map(([educando, lista]) => ({educando, lista}));
        tarjeta("Fichas básicas por entregar", fichas, "Todas entregadas.", (grupo, linea) => {
            linea.append(this.crear("b", "", grupo.educando + " (" + grupo.lista.length + "): "),
                grupo.lista.map((ficha) => ficha.documento + (ficha.estado === "corregir" ? " (a corregir)" : "")).join(", "));
        });
        zona.append(pendientes, this.renderCalendarioTrimestral(datos.calendario, datos.inicio_ronda));
        return zona;
    }

    // Trimestres escolares de la ronda: sep-dic, ene-mar, abr-jun y verano (jul-ago).
    trimestres(inicioRonda) {
        const ano = Number(inicioRonda.substring(0, 4));
        return [
            {nombre: "1.er trimestre", meses: [[ano, 8], [ano, 9], [ano, 10], [ano, 11]]},
            {nombre: "2.º trimestre", meses: [[ano + 1, 0], [ano + 1, 1], [ano + 1, 2]]},
            {nombre: "3.er trimestre", meses: [[ano + 1, 3], [ano + 1, 4], [ano + 1, 5]]},
            {nombre: "Verano", meses: [[ano + 1, 6], [ano + 1, 7]]}
        ];
    }

    renderCalendarioTrimestral(eventos, inicioRonda) {
        const contenedor = this.crear("div", "calendario-trimestral");
        const trimestres = this.trimestres(inicioRonda);
        if (this.trimestre === null) {
            const hoy = new Date();
            const actual = trimestres.findIndex((trimestre) => trimestre.meses.some(
                ([ano, mes]) => ano === hoy.getFullYear() && mes === hoy.getMonth()));
            this.trimestre = actual === -1 ? 0 : actual;
        }
        const pintar = () => {
            contenedor.replaceChildren();
            const trimestre = trimestres[this.trimestre];
            const navegacion = this.crear("div", "navegacion-trimestre");
            const anterior = this.crear("button", "boton boton-cancelar", "‹");
            anterior.type = "button";
            anterior.setAttribute("aria-label", "Trimestre anterior");
            anterior.disabled = this.trimestre === 0;
            anterior.onclick = () => {
                this.trimestre--;
                pintar();
            };
            const siguiente = this.crear("button", "boton boton-cancelar", "›");
            siguiente.type = "button";
            siguiente.setAttribute("aria-label", "Trimestre siguiente");
            siguiente.disabled = this.trimestre === trimestres.length - 1;
            siguiente.onclick = () => {
                this.trimestre++;
                pintar();
            };
            navegacion.append(anterior, this.crear("strong", "", "Calendario · " + trimestre.nombre), siguiente);
            contenedor.appendChild(navegacion);

            // Días con eventos: fecha → nombres (un evento de varios días marca todos sus días).
            const porDia = {};
            eventos.forEach((evento) => {
                const dia = new Date(evento.fecha + "T00:00:00");
                const fin = new Date(evento.fin + "T00:00:00");
                while (dia <= fin) {
                    const clave = dia.toISOString().substring(0, 10);
                    (porDia[clave] ??= []).push(evento.nombre);
                    dia.setDate(dia.getDate() + 1);
                }
            });
            const meses = this.crear("div", "meses-trimestre");
            trimestre.meses.forEach(([ano, mes]) => meses.appendChild(this.crearMes(ano, mes, porDia)));
            contenedor.appendChild(meses);
        };
        pintar();
        return contenedor;
    }

    crearMes(ano, mes, porDia) {
        const caja = this.crear("div", "mes-mini");
        caja.appendChild(this.crear("b", "", new Date(ano, mes, 1).toLocaleDateString("es-ES", {month: "long", year: "numeric"})));
        const rejilla = this.crear("div", "rejilla-mes");
        ["L", "M", "X", "J", "V", "S", "D"].forEach((dia) => rejilla.appendChild(this.crear("span", "cabecera-dia", dia)));
        const primero = (new Date(ano, mes, 1).getDay() + 6) % 7;
        for (let i = 0; i < primero; i++) {
            rejilla.appendChild(this.crear("span"));
        }
        const hoy = new Date().toISOString().substring(0, 10);
        const dias = new Date(ano, mes + 1, 0).getDate();
        for (let dia = 1; dia <= dias; dia++) {
            const clave = ano + "-" + String(mes + 1).padStart(2, "0") + "-" + String(dia).padStart(2, "0");
            const nombres = porDia[clave];
            const celda = this.crear("span", "dia-mini" + (nombres ? " con-evento" : "") + (clave === hoy ? " hoy" : ""), String(dia));
            if (nombres) {
                celda.title = nombres.join("\n");
                celda.setAttribute("aria-label", dia + ": " + nombres.join(", "));
                celda.tabIndex = 0;
            }
            rejilla.appendChild(celda);
        }
        caja.appendChild(rejilla);
        // Solo los días con eventos, escritos debajo.
        const lista = Object.keys(porDia).filter((clave) => clave.startsWith(ano + "-" + String(mes + 1).padStart(2, "0"))).sort();
        if (lista.length) {
            const eventos = this.crear("ul", "lista-dashboard eventos-mes");
            lista.forEach((clave) => {
                const linea = this.crear("li");
                linea.append(this.crear("b", "", clave.substring(8) + ":"), " " + [...new Set(porDia[clave])].join(", "));
                eventos.appendChild(linea);
            });
            caja.appendChild(eventos);
        }
        return caja;
    }

    // ---------- Gráficas ----------

    renderGraficas(graficas) {
        const zona = this.crear("div", "dashboard-graficas");
        graficas.forEach((grafica) => {
            const tarjeta = this.crear("article", "grafica-card vista-superficie");
            zona.appendChild(tarjeta);
            this.graficas.crear(tarjeta, grafica.tipo, grafica.etiquetas, grafica.valores, grafica.titulo);
        });
        return zona;
    }

    formatearFecha(valor) {
        return new Date(String(valor).replace(" ", "T").substring(0, 10) + "T00:00:00")
            .toLocaleDateString("es-ES", {day: "2-digit", month: "2-digit", year: "numeric"});
    }

    formatearImporte(valor) {
        return Number(valor).toLocaleString("es-ES", {style: "currency", currency: "EUR"});
    }

    tiempoHasta(valor) {
        const diferencia = new Date(String(valor).replace(" ", "T")) - new Date();
        if (diferencia <= 0) {
            return "ya ha comenzado";
        }
        const dias = Math.ceil(diferencia / 86400000);
        return "faltan " + dias + " día" + (dias === 1 ? "" : "s") + " (" + this.formatearFecha(valor) + ")";
    }
}
