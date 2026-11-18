class DarkorangeGraficas {
    static colores = [
        "#66ff00",
        "#e72222",
        "#155fe8",
        "#ebe710",
        "#f00ed2",
        "#7d07df",
        "#f87917"
    ];

    // colores (opcional): paleta propia, por ejemplo los tonos de una sección.
    static crear(contenedor, tipo, etiquetas, valores, titulo = "", colores = null) {
        const elemento = typeof contenedor === "string"
            ? document.querySelector(contenedor)
            : contenedor;

        if (!elemento) {
            return;
        }

        elemento.innerHTML = "";

        if (titulo) {
            const encabezado = document.createElement("h3");
            encabezado.textContent = titulo;
            elemento.appendChild(encabezado);
        }

        const datos = valores.map((valor, indice) => ({
            label: String(etiquetas[indice] ?? `Elemento ${indice + 1}`),
            valor: Math.max(0, Number(valor) || 0)
        }));

        if (!datos.length || !datos.some((dato) => dato.valor)) {
            elemento.insertAdjacentHTML(
                "beforeend",
                '<p class="vacio">Sin datos para representar</p>'
            );
            return;
        }

        const svg = this.crearSvg(tipo, titulo);
        const tooltip = this.obtenerTooltip();
        const total = datos.reduce(
            (suma, dato) => suma + dato.valor,
            0
        );

        elemento.appendChild(svg);

        const paleta = colores?.length ? colores : this.colores;
        if (tipo === "anillo" || tipo === "tarta") {
            this.crearGraficaCircular(svg, datos, total, tipo, tooltip, paleta);
        } else {
            this.crearGraficaBarras(svg, datos, total, tooltip, paleta);
        }
    }

    // Anillo doble por grupos (asistencias del sábado por sección):
    //   - anillo interior: una porción por grupo, de tamaño proporcional a su total;
    //   - anillo exterior: encima de cada grupo, sus partes con los tonos de su color;
    //   - al lado, una tabla con todas las cifras.
    // grupos: [{nombre, color, tonos: [...], valores: [...]}]; etiquetas: nombre de cada parte.
    // alElegir(índice del grupo): si se indica, cada grupo se puede pulsar (sus sectores y
    // su fila de la tabla) para ver su detalle.
    static crearAnilloAgrupado(contenedor, grupos, etiquetas, textoCentro = "", alElegir = null) {
        const elemento = typeof contenedor === "string"
            ? document.querySelector(contenedor)
            : contenedor;
        if (!elemento) {
            return;
        }
        elemento.innerHTML = "";
        const total = grupos.reduce((suma, grupo) =>
            suma + grupo.valores.reduce((parcial, valor) => parcial + valor, 0), 0);
        if (!total) {
            elemento.insertAdjacentHTML("beforeend", '<p class="vacio">Sin datos para representar</p>');
            return;
        }

        const svg = this.crearNodo("svg", {viewBox: "0 0 320 320", role: "img", "aria-label": textoCentro || "Gráfica"});
        svg.classList.add("grafica-svg", "grafica-agrupada");
        const tooltip = this.obtenerTooltip();
        const centro = 160;
        const punto = (distancia, angulo) => [
            centro + distancia * Math.cos(angulo),
            centro + distancia * Math.sin(angulo)
        ];
        const sector = (radio, radioInterior, inicio, amplitud, color, dato, totalDato, indiceGrupo) => {
            if (!amplitud) {
                return;
            }
            const figura = this.crearNodo("path", {
                d: this.crearRutaCircular(centro, centro, radio, radioInterior, inicio,
                    inicio + amplitud, amplitud, punto),
                fill: color,
                stroke: "#fff",
                "stroke-width": 1.5,
                "fill-rule": "evenodd"
            });
            svg.appendChild(figura);
            this.activarTooltip(figura, dato, totalDato, tooltip);
            if (alElegir) {
                figura.classList.add("sector-seleccionable");
                figura.addEventListener("click", () => {
                    tooltip.style.display = "none";
                    alElegir(indiceGrupo);
                });
            }
        };

        let angulo = -Math.PI / 2;
        grupos.forEach((grupo, indiceGrupo) => {
            const totalGrupo = grupo.valores.reduce((suma, valor) => suma + valor, 0);
            const amplitudGrupo = totalGrupo / total * Math.PI * 2;
            sector(100, 60, angulo, amplitudGrupo, grupo.color,
                {label: grupo.nombre, valor: totalGrupo}, total, indiceGrupo);
            let anguloParte = angulo;
            grupo.valores.forEach((valor, indice) => {
                const amplitud = valor / total * Math.PI * 2;
                sector(152, 104, anguloParte, amplitud, grupo.tonos[indice],
                    {label: grupo.nombre + " · " + etiquetas[indice], valor}, totalGrupo, indiceGrupo);
                anguloParte += amplitud;
            });
            angulo += amplitudGrupo;
        });
        const numero = this.crearNodo("text", {x: centro, y: centro + 4, "text-anchor": "middle",
            "font-size": 30, "font-weight": 700, fill: "currentColor"});
        numero.textContent = String(total);
        const leyenda = this.crearNodo("text", {x: centro, y: centro + 24, "text-anchor": "middle",
            "font-size": 12, fill: "currentColor"});
        leyenda.textContent = textoCentro;
        svg.append(numero, leyenda);

        // Tabla con las cifras: una fila por grupo y una columna por parte.
        const tabla = document.createElement("table");
        tabla.className = "tabla-grafica-agrupada";
        const cabecera = document.createElement("tr");
        ["", ...etiquetas, "Total"].forEach((texto) => {
            const celda = document.createElement("th");
            celda.textContent = texto;
            cabecera.appendChild(celda);
        });
        tabla.appendChild(document.createElement("thead")).appendChild(cabecera);
        const cuerpo = tabla.appendChild(document.createElement("tbody"));
        grupos.forEach((grupo, indiceGrupo) => {
            const fila = document.createElement("tr");
            const nombre = document.createElement("th");
            nombre.scope = "row";
            const muestra = document.createElement("span");
            muestra.className = "muestra-color";
            muestra.style.background = grupo.color;
            if (alElegir) {
                // También con el teclado: el nombre del grupo es un botón.
                const boton = document.createElement("button");
                boton.type = "button";
                boton.className = "boton-grupo-grafica";
                boton.title = "Ver el detalle de " + grupo.nombre;
                boton.append(muestra, grupo.nombre);
                boton.onclick = () => alElegir(indiceGrupo);
                nombre.appendChild(boton);
            } else {
                nombre.append(muestra, grupo.nombre);
            }
            fila.appendChild(nombre);
            grupo.valores.forEach((valor, indice) => {
                const celda = document.createElement("td");
                const tono = document.createElement("span");
                tono.className = "muestra-color";
                tono.style.background = grupo.tonos[indice];
                celda.append(tono, String(valor));
                fila.appendChild(celda);
            });
            const totalCelda = document.createElement("td");
            totalCelda.textContent = String(grupo.valores.reduce((suma, valor) => suma + valor, 0));
            fila.appendChild(totalCelda);
            cuerpo.appendChild(fila);
        });

        const envoltorio = document.createElement("div");
        envoltorio.className = "anillo-agrupado";
        envoltorio.append(svg, tabla);
        elemento.appendChild(envoltorio);
    }

    // Anillo simple y grande: una porción por parte ({nombre, color, valor}) y el total en el
    // centro. Devuelve el SVG (lo coloca quien lo llama).
    static crearAnilloSimple(partes, textoCentro = "") {
        const total = partes.reduce((suma, parte) => suma + parte.valor, 0);
        const svg = this.crearNodo("svg", {viewBox: "0 0 320 320", role: "img", "aria-label": textoCentro || "Gráfica"});
        svg.classList.add("grafica-svg", "grafica-agrupada");
        const tooltip = this.obtenerTooltip();
        const centro = 160;
        const punto = (distancia, angulo) => [
            centro + distancia * Math.cos(angulo),
            centro + distancia * Math.sin(angulo)
        ];
        let angulo = -Math.PI / 2;
        partes.forEach((parte) => {
            if (!parte.valor) {
                return;
            }
            const amplitud = parte.valor / total * Math.PI * 2;
            const figura = this.crearNodo("path", {
                d: this.crearRutaCircular(centro, centro, 152, 88, angulo, angulo + amplitud, amplitud, punto),
                fill: parte.color,
                stroke: "#fff",
                "stroke-width": 2,
                "fill-rule": "evenodd"
            });
            svg.appendChild(figura);
            this.activarTooltip(figura, {label: parte.nombre, valor: parte.valor}, total, tooltip);
            angulo += amplitud;
        });
        const numero = this.crearNodo("text", {x: centro, y: centro + 6, "text-anchor": "middle",
            "font-size": 40, "font-weight": 700, fill: "currentColor"});
        numero.textContent = String(total);
        const leyenda = this.crearNodo("text", {x: centro, y: centro + 30, "text-anchor": "middle",
            "font-size": 13, fill: "currentColor"});
        leyenda.textContent = textoCentro;
        svg.append(numero, leyenda);
        return svg;
    }

    static crearSvg(tipo, titulo) {
        const svg = this.crearNodo("svg", {
            viewBox: "0 0 440 260",
            role: "img",
            "aria-label": titulo || tipo
        });

        svg.classList.add("grafica-svg");
        return svg;
    }

    static crearNodo(etiqueta, atributos = {}) {
        const nodo = document.createElementNS(
            "http://www.w3.org/2000/svg",
            etiqueta
        );

        Object.entries(atributos).forEach(([nombre, valor]) => {
            nodo.setAttribute(nombre, valor);
        });

        return nodo;
    }

    static obtenerTooltip() {
        let tooltip = document.querySelector("#grafica-tooltip");

        if (!tooltip) {
            tooltip = document.createElement("div");
            tooltip.id = "grafica-tooltip";
            tooltip.className = "grafica-tooltip";
            document.body.appendChild(tooltip);
        }

        return tooltip;
    }

    static activarTooltip(figura, dato, total, tooltip) {
        figura.addEventListener("mousemove", (evento) => {
            const numero = new Intl.NumberFormat("es-ES", {
                maximumFractionDigits: 2
            }).format(dato.valor);
            const porcentaje = (dato.valor / total * 100).toFixed(1);

            tooltip.textContent =
                `${dato.label}: ${numero} (${porcentaje}%)`;
            tooltip.style.display = "block";
            tooltip.style.left = Math.min(
                evento.clientX + 12,
                innerWidth - tooltip.offsetWidth - 8
            ) + "px";
            tooltip.style.top = Math.min(
                evento.clientY + 12,
                innerHeight - tooltip.offsetHeight - 8
            ) + "px";
        });

        figura.addEventListener("mouseleave", () => {
            tooltip.style.display = "none";
        });
    }

    static crearGraficaCircular(svg, datos, total, tipo, tooltip, paleta = this.colores) {
        let angulo = -Math.PI / 2;
        const centroX = 145;
        const centroY = 128;
        const radio = 105;
        const radioInterior = tipo === "anillo" ? 62 : 0;

        const punto = (distancia, anguloActual) => [
            centroX + distancia * Math.cos(anguloActual),
            centroY + distancia * Math.sin(anguloActual)
        ];

        datos.forEach((dato, indice) => {
            if (!dato.valor) {
                return;
            }

            const amplitud = dato.valor / total * Math.PI * 2;
            const final = angulo + amplitud;
            const ruta = this.crearRutaCircular(
                centroX,
                centroY,
                radio,
                radioInterior,
                angulo,
                final,
                amplitud,
                punto
            );

            const figura = this.crearNodo("path", {
                d: ruta,
                fill: paleta[indice % paleta.length],
                stroke: "#fff",
                "stroke-width": 2,
                "fill-rule": "evenodd"
            });

            svg.appendChild(figura);
            this.activarTooltip(figura, dato, total, tooltip);
            angulo = final;
        });

        if (radioInterior) {
            const texto = this.crearNodo("text", {
                x: centroX,
                y: centroY,
                "text-anchor": "middle",
                "font-size": 24,
                "font-weight": 700,
                fill: "#333"
            });

            texto.textContent = new Intl.NumberFormat("es-ES", {
                notation: total > 9999 ? "compact" : "standard"
            }).format(total);
            svg.appendChild(texto);
        }

        datos.slice(0, 7).forEach((dato, indice) => {
            const y = 38 + indice * 29;
            const color = paleta[indice % paleta.length];
            const indicador = this.crearNodo("rect", {
                x: 275,
                y: y - 11,
                width: 12,
                height: 12,
                rx: 3,
                fill: color
            });
            const texto = this.crearNodo("text", {
                x: 293,
                y,
                fill: "#444",
                "font-size": 12
            });

            texto.textContent = `${dato.label.slice(0, 17)} · ${dato.valor}`;
            svg.append(indicador, texto);
        });
    }

    static crearRutaCircular(
        centroX,
        centroY,
        radio,
        radioInterior,
        inicio,
        final,
        amplitud,
        punto
    ) {
        if (amplitud >= Math.PI * 2 - 1e-8) {
            const circuloExterior =
                `M ${centroX - radio} ${centroY} ` +
                `a ${radio} ${radio} 0 1 0 ${2 * radio} 0 ` +
                `a ${radio} ${radio} 0 1 0 ${-2 * radio} 0`;

            if (!radioInterior) {
                return `${circuloExterior} Z`;
            }

            const circuloInterior =
                `M ${centroX - radioInterior} ${centroY} ` +
                `a ${radioInterior} ${radioInterior} 0 1 1 ` +
                `${2 * radioInterior} 0 ` +
                `a ${radioInterior} ${radioInterior} 0 1 1 ` +
                `${-2 * radioInterior} 0 Z`;

            return `${circuloExterior} ${circuloInterior}`;
        }

        const [x1, y1] = punto(radio, inicio);
        const [x2, y2] = punto(radio, final);
        const arcoGrande = amplitud > Math.PI ? 1 : 0;

        if (!radioInterior) {
            return (
                `M ${centroX} ${centroY} ` +
                `L ${x1} ${y1} ` +
                `A ${radio} ${radio} 0 ${arcoGrande} 1 ${x2} ${y2} Z`
            );
        }

        const [x3, y3] = punto(radioInterior, final);
        const [x4, y4] = punto(radioInterior, inicio);

        return (
            `M ${x1} ${y1} ` +
            `A ${radio} ${radio} 0 ${arcoGrande} 1 ${x2} ${y2} ` +
            `L ${x3} ${y3} ` +
            `A ${radioInterior} ${radioInterior} 0 ${arcoGrande} 0 ` +
            `${x4} ${y4} Z`
        );
    }

    static crearGraficaBarras(svg, datos, total, tooltip, paleta = this.colores) {
        const maximo = Math.max(...datos.map((dato) => dato.valor), 1);
        const cantidad = Math.min(datos.length, 12);
        const separacion = 9;
        const ancho = 395 / cantidad - separacion;

        datos.slice(0, cantidad).forEach((dato, indice) => {
            const alto = dato.valor / maximo * 190;
            const x = 25 + indice * (ancho + separacion);
            const y = 216 - alto;
            const barra = this.crearNodo("rect", {
                x,
                y,
                width: ancho,
                height: alto,
                rx: 4,
                fill: paleta[indice % paleta.length]
            });

            const etiqueta = this.crearNodo("text", {
                x: x + ancho / 2,
                y: 235,
                "text-anchor": "middle",
                "font-size": 10,
                fill: "#555"
            });

            etiqueta.textContent = dato.label.slice(
                0,
                Math.max(3, Math.floor(ancho / 6))
            );
            svg.append(barra, etiqueta);
            this.activarTooltip(barra, dato, total, tooltip);
        });
    }

}
