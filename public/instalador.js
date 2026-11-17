// Instalador interactivo (E-11).
// Pasos: 1 base de datos y modo · 2 tablas (asistente) o archivo schema.sql ·
// 3 datos iniciales y primer administrador · 4 revisar el SQL e instalar.
// El servidor (api/instalar.php) valida el modelo y genera el SQL; aquí solo se edita.
const TIPOS = ["VARCHAR", "CHAR", "INT", "SMALLINT", "TINYINT", "DECIMAL", "DATE", "DATETIME",
    "BOOLEAN", "TEXT", "ENUM"];
const ACCIONES_FK = ["RESTRICT", "CASCADE", "SET NULL", "NO ACTION"];

const instalador = {
    estado: null,
    paso: 1,
    modo: "asistente",
    token: "",
    modelo: {tablas: []},
    archivoSql: null,
    semillas: [],
    administrador: {nombre: "", apellidos: "", email: "", telefono: "", contrasena: ""},
    sql: ""
};

function crear(etiqueta, propiedades = {}, ...hijos) {
    const elemento = document.createElement(etiqueta);
    Object.entries(propiedades).forEach(([clave, valor]) => {
        if (clave === "dataset") {
            Object.assign(elemento.dataset, valor);
        } else if (clave in elemento) {
            elemento[clave] = valor;
        } else {
            elemento.setAttribute(clave, valor);
        }
    });
    hijos.flat().forEach((hijo) => elemento.append(hijo));
    return elemento;
}

function boton(texto, clase, accion) {
    return crear("button", {type: "button", className: "boton " + clase, textContent: texto, onclick: accion});
}

function peticion(accion, opciones = {}) {
    return fetch("api/instalar.php?accion=" + accion, opciones).then((respuesta) =>
        respuesta.json().then((datos) => {
            if (!respuesta.ok || datos.error) {
                throw new Error(datos.error || "Error " + respuesta.status);
            }
            return datos;
        })
    );
}

function enviarJson(accion, datos) {
    return peticion(accion, {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({...datos, token: instalador.token})
    });
}

function descargar(nombre, contenido, tipo) {
    const enlace = crear("a", {href: URL.createObjectURL(new Blob([contenido], {type: tipo})), download: nombre});
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
}

// ---------- Pasos ----------

const PASOS = [
    [1, "Base de datos"],
    [2, "Tablas"],
    [3, "Datos iniciales"],
    [4, "Revisar e instalar"]
];

function pintar() {
    const lista = document.querySelector("#pasos-instalador");
    lista.replaceChildren(...PASOS.map(([numero, texto]) => crear("li", {
        className: numero === instalador.paso ? "activo" : (numero < instalador.paso ? "hecho" : ""),
        textContent: texto,
        "aria-current": numero === instalador.paso ? "step" : "false"
    })));
    const contenedor = document.querySelector("#paso-instalador");
    contenedor.replaceChildren(
        [null, pasoBase, instalador.modo === "asistente" ? pasoTablas : pasoArchivo, pasoDatos, pasoRevisar][instalador.paso]()
    );
}

function irA(paso) {
    instalador.paso = paso;
    pintar();
    window.scrollTo(0, 0);
}

function navegacion(anterior, siguiente, textoSiguiente = "Siguiente") {
    return crear("div", {className: "navegacion-instalador"},
        anterior ? boton("← Anterior", "boton-cancelar", anterior) : "",
        siguiente ? boton(textoSiguiente + " →", "boton-crear", siguiente) : "");
}

function pasoBase() {
    const caja = crear("div");
    caja.append(
        crear("h2", {textContent: "1. Base de datos"}),
        crear("p", {textContent: "Se instalará en la base «" + instalador.estado.base +
            "». El usuario y la contraseña de MySQL se configuran en el servidor (variables " +
            "de entorno de Apache), no aquí."})
    );
    if (instalador.estado.token_requerido) {
        const token = crear("input", {type: "password", value: instalador.token, autocomplete: "off"});
        token.oninput = () => {
            instalador.token = token.value;
        };
        caja.append(crear("label", {className: "campo"}, "Token de instalación (INGERIAR_TOKEN_INSTALACION)", token));
    }
    const opciones = crear("fieldset", {className: "campo"}, crear("legend", {textContent: "¿Cómo quieres crear las tablas?"}));
    [["asistente", "Con el asistente: elegir o modificar las tablas (recomendado)"],
        ["archivo", "Subiendo un archivo schema.sql"]].forEach(([valor, texto]) => {
        const radio = crear("input", {type: "radio", name: "modo", value: valor, checked: instalador.modo === valor});
        radio.onchange = () => {
            instalador.modo = valor;
        };
        opciones.append(crear("label", {className: "opcion-instalador"}, radio, " " + texto));
    });
    caja.append(opciones, navegacion(null, () => {
        if (instalador.modo === "asistente" && instalador.modelo.tablas.length === 0) {
            cargarModeloIngeriar().then(() => irA(2));
        } else {
            irA(2);
        }
    }));
    return caja;
}

function cargarModeloIngeriar() {
    return peticion("modelo&token=" + encodeURIComponent(instalador.token))
        .then((modelo) => {
            instalador.modelo = modelo;
        })
        .catch((error) => alert(error.message));
}

// ---------- Paso 2: asistente de tablas ----------

function pasoTablas() {
    const caja = crear("div");
    caja.append(
        crear("h2", {textContent: "2. Tablas"}),
        crear("p", {textContent: "Este es el modelo de Ingeriar. Puedes modificarlo, añadir tablas o " +
            "guardarlo en JSON para reutilizarlo. Las claves foráneas se crean al final."})
    );
    const archivoJson = crear("input", {type: "file", accept: "application/json,.json", hidden: true});
    archivoJson.onchange = () => {
        const lector = new FileReader();
        lector.onload = () => {
            try {
                const modelo = JSON.parse(lector.result);
                if (!Array.isArray(modelo.tablas)) {
                    throw new Error("El archivo no tiene «tablas»");
                }
                instalador.modelo = modelo;
                pintar();
            } catch (error) {
                alert("No es un modelo válido: " + error.message);
            }
        };
        lector.readAsText(archivoJson.files[0]);
    };
    caja.append(crear("div", {className: "herramientas-instalador"},
        boton("Cargar modelo de Ingeriar", "boton-editar", () => {
            if (confirm("Se sustituirán las tablas actuales por el modelo de Ingeriar. ¿Seguir?")) {
                cargarModeloIngeriar().then(pintar);
            }
        }),
        boton("Descargar modelo (JSON)", "boton-descargar", () =>
            descargar("modelo-ingeriar.json", JSON.stringify(instalador.modelo, null, 2), "application/json")),
        boton("Cargar modelo (JSON)", "boton-cancelar", () => archivoJson.click()),
        archivoJson,
        boton("+ Nueva tabla", "boton-crear", () => {
            const nombre = prompt("Nombre de la tabla (minúsculas y guiones bajos):");
            if (nombre) {
                instalador.modelo.tablas.push({
                    nombre, clave_primaria: ["id"], unicos: [], indices: [], foraneas: [],
                    columnas: [{nombre: "id", tipo: "INT", sin_signo: true, nulo: false, auto: true, defecto: null}]
                });
                pintar();
            }
        })
    ));
    instalador.modelo.tablas.forEach((tabla, indice) => caja.append(editorTabla(tabla, indice)));
    caja.append(navegacion(() => irA(1), () => irA(3)));
    return caja;
}

function editorTabla(tabla, indice) {
    const detalles = crear("details", {className: "tabla-instalador"});
    const resumen = crear("summary", {textContent: tabla.nombre + " · " + tabla.columnas.length + " columnas" +
        (tabla.foraneas.length ? " · " + tabla.foraneas.length + " claves foráneas" : "")});
    detalles.append(resumen);

    const nombre = crear("input", {value: tabla.nombre, "aria-label": "Nombre de la tabla"});
    nombre.onchange = () => {
        tabla.nombre = nombre.value.trim();
        pintar();
    };
    detalles.append(crear("div", {className: "fila-instalador"},
        crear("label", {}, "Nombre ", nombre),
        boton("Borrar tabla", "boton-borrar", () => {
            if (confirm("¿Quitar la tabla «" + tabla.nombre + "» del modelo?")) {
                instalador.modelo.tablas.splice(indice, 1);
                pintar();
            }
        })));

    // Columnas
    const cuerpo = crear("tbody");
    tabla.columnas.forEach((columna, posicion) => cuerpo.append(filaColumna(tabla, columna, posicion)));
    detalles.append(crear("div", {className: "contenedor-tabla-roles"}, crear("table", {className: "tabla-roles tabla-columnas"},
        crear("thead", {}, crear("tr", {}, ...["Columna", "Tipo", "Longitud / valores", "Nulo", "Por defecto",
            "Clave", "Auto", "Único", "Sin signo", ""].map((texto) => crear("th", {textContent: texto, scope: "col"})))),
        cuerpo)));
    detalles.append(boton("+ Columna", "boton-cancelar", () => {
        tabla.columnas.push({nombre: "columna_" + (tabla.columnas.length + 1), tipo: "VARCHAR", longitud: 100,
            nulo: true, auto: false, sin_signo: false, defecto: null});
        pintar();
    }));

    // Índices únicos compuestos
    const unicos = crear("input", {
        value: tabla.unicos.filter((u) => u.length > 1).map((u) => u.join(",")).join("; "),
        placeholder: "id_usuario,id_educando; otra,combinacion",
        "aria-label": "Índices únicos de varias columnas"
    });
    unicos.onchange = () => {
        const simples = tabla.unicos.filter((u) => u.length === 1);
        const compuestos = unicos.value.split(";").map((u) => u.split(",").map((c) => c.trim()).filter(Boolean))
            .filter((u) => u.length > 1);
        tabla.unicos = [...simples, ...compuestos];
    };
    detalles.append(crear("label", {className: "campo"}, "Únicos de varias columnas (separados por «;»)", unicos));

    // Claves foráneas
    detalles.append(crear("h4", {textContent: "Claves foráneas"}));
    tabla.foraneas.forEach((foranea, posicion) => detalles.append(filaForanea(tabla, foranea, posicion)));
    detalles.append(boton("+ Clave foránea", "boton-cancelar", () => {
        tabla.foraneas.push({columna: tabla.columnas[0]?.nombre || "", tabla: instalador.modelo.tablas[0]?.nombre || "",
            referencia: "id", al_borrar: "RESTRICT", al_actualizar: "CASCADE", nombre: null});
        pintar();
        detalles.open = true;
    }));
    return detalles;
}

function filaColumna(tabla, columna, posicion) {
    const fila = crear("tr");
    const celda = (...hijos) => fila.append(crear("td", {}, ...hijos));
    const texto = (valor, alCambiar, etiqueta) => {
        const campo = crear("input", {value: valor ?? "", "aria-label": etiqueta, size: 12});
        campo.onchange = () => alCambiar(campo.value);
        return campo;
    };
    const casilla = (marcada, alCambiar, etiqueta) => {
        const campo = crear("input", {type: "checkbox", checked: Boolean(marcada), "aria-label": etiqueta});
        campo.onchange = () => alCambiar(campo.checked);
        return campo;
    };
    celda(texto(columna.nombre, (valor) => {
        const anterior = columna.nombre;
        columna.nombre = valor.trim();
        tabla.clave_primaria = tabla.clave_primaria.map((c) => c === anterior ? columna.nombre : c);
    }, "Nombre de la columna"));
    const tipo = crear("select", {"aria-label": "Tipo de " + columna.nombre},
        ...TIPOS.map((t) => crear("option", {value: t, textContent: t})));
    tipo.value = columna.tipo;
    tipo.onchange = () => {
        columna.tipo = tipo.value;
        pintar();
    };
    celda(tipo);
    if (["VARCHAR", "CHAR"].includes(columna.tipo)) {
        celda(texto(columna.longitud ?? 100, (v) => {
            columna.longitud = parseInt(v, 10) || 1;
        }, "Longitud"));
    } else if (columna.tipo === "DECIMAL") {
        celda(texto((columna.precision ?? 10) + "," + (columna.escala ?? 2), (v) => {
            const [precision, escala] = v.split(",").map((n) => parseInt(n, 10));
            columna.precision = precision || 10;
            columna.escala = escala || 0;
        }, "Precisión y escala"));
    } else if (columna.tipo === "ENUM") {
        celda(texto((columna.valores || []).join(","), (v) => {
            columna.valores = v.split(",").map((valor) => valor.trim()).filter(Boolean);
        }, "Valores separados por comas"));
    } else {
        celda("");
    }
    celda(casilla(columna.nulo, (v) => {
        columna.nulo = v;
    }, "Admite nulo"));
    celda(texto(columna.defecto === null || columna.defecto === undefined ? "" : String(columna.defecto), (v) => {
        columna.defecto = v === "" ? null : v;
    }, "Valor por defecto"));
    celda(casilla(tabla.clave_primaria.includes(columna.nombre), (v) => {
        tabla.clave_primaria = v
            ? [...tabla.clave_primaria, columna.nombre]
            : tabla.clave_primaria.filter((c) => c !== columna.nombre);
    }, "Clave primaria"));
    celda(casilla(columna.auto, (v) => {
        columna.auto = v;
    }, "AUTO_INCREMENT"));
    const esUnico = tabla.unicos.some((u) => u.length === 1 && u[0] === columna.nombre);
    celda(casilla(esUnico, (v) => {
        tabla.unicos = v
            ? [...tabla.unicos, [columna.nombre]]
            : tabla.unicos.filter((u) => !(u.length === 1 && u[0] === columna.nombre));
    }, "Único"));
    celda(["INT", "SMALLINT", "TINYINT"].includes(columna.tipo)
        ? casilla(columna.sin_signo, (v) => {
            columna.sin_signo = v;
        }, "Sin signo")
        : "");
    celda(boton("×", "boton-mini boton-mini-borrar", () => {
        tabla.columnas.splice(posicion, 1);
        tabla.clave_primaria = tabla.clave_primaria.filter((c) => c !== columna.nombre);
        pintar();
    }));
    return fila;
}

function filaForanea(tabla, foranea, posicion) {
    const selector = (opciones, valor, alCambiar, etiqueta) => {
        const campo = crear("select", {"aria-label": etiqueta}, ...opciones.map((o) => crear("option", {value: o, textContent: o})));
        campo.value = valor;
        campo.onchange = () => alCambiar(campo.value);
        return campo;
    };
    const destino = instalador.modelo.tablas.find((t) => t.nombre === foranea.tabla);
    return crear("div", {className: "fila-instalador fila-foranea"},
        selector(tabla.columnas.map((c) => c.nombre), foranea.columna, (v) => {
            foranea.columna = v;
        }, "Columna"),
        " → ",
        selector(instalador.modelo.tablas.map((t) => t.nombre), foranea.tabla, (v) => {
            foranea.tabla = v;
            pintar();
        }, "Tabla destino"),
        " . ",
        selector((destino?.columnas || []).map((c) => c.nombre), foranea.referencia, (v) => {
            foranea.referencia = v;
        }, "Columna destino"),
        " ON DELETE ",
        selector(ACCIONES_FK, foranea.al_borrar, (v) => {
            foranea.al_borrar = v;
        }, "Al borrar"),
        " ON UPDATE ",
        selector(ACCIONES_FK, foranea.al_actualizar, (v) => {
            foranea.al_actualizar = v;
        }, "Al actualizar"),
        boton("×", "boton-mini boton-mini-borrar", () => {
            tabla.foraneas.splice(posicion, 1);
            pintar();
        }));
}

// ---------- Paso 2 (alternativo): subir schema.sql ----------

function pasoArchivo() {
    const archivo = crear("input", {type: "file", accept: ".sql,text/plain"});
    archivo.onchange = () => {
        instalador.archivoSql = archivo.files[0] || null;
    };
    return crear("div", {},
        crear("h2", {textContent: "2. Archivo schema.sql"}),
        crear("p", {textContent: "Se admiten CREATE TABLE, INSERT y ALTER TABLE … ADD. Se rechazan DROP, " +
            "GRANT, LOAD, INTO OUTFILE y similares. CREATE DATABASE y USE se ignoran: se usa la base " +
            "configurada. Máximo 1 MB."}),
        crear("label", {className: "campo"}, "Archivo", archivo),
        instalador.archivoSql ? crear("p", {textContent: "Elegido: " + instalador.archivoSql.name}) : "",
        navegacion(() => irA(1), () => {
            if (!instalador.archivoSql) {
                alert("Elige el archivo schema.sql.");
                return;
            }
            irA(3);
        }));
}

// ---------- Paso 3: datos iniciales y administrador ----------

function pasoDatos() {
    const caja = crear("div");
    caja.append(crear("h2", {textContent: "3. Datos iniciales y primer administrador"}));
    const semillas = crear("fieldset", {className: "campo"}, crear("legend", {textContent: "Datos iniciales"}));
    instalador.estado.semillas.forEach((semilla) => {
        const casilla = crear("input", {type: "checkbox", checked: instalador.semillas.includes(semilla.codigo)});
        casilla.onchange = () => {
            instalador.semillas = casilla.checked
                ? [...instalador.semillas, semilla.codigo]
                : instalador.semillas.filter((codigo) => codigo !== semilla.codigo);
        };
        semillas.append(crear("label", {className: "opcion-instalador"}, casilla, " " + semilla.nombre));
    });
    if (instalador.modo === "archivo") {
        semillas.append(crear("small", {textContent: "Si tu schema.sql ya incluye estos datos, no hace falta marcarlos."}));
    }
    caja.append(semillas);

    const administrador = crear("fieldset", {className: "campo"},
        crear("legend", {textContent: "Primer administrador (solo si todavía no hay usuarios)"}));
    [["nombre", "Nombre", "text"], ["apellidos", "Apellidos", "text"], ["email", "Email", "email"],
        ["telefono", "Teléfono", "tel"], ["contrasena", "Contraseña (8 caracteres, con un número o símbolo)", "password"]]
        .forEach(([clave, texto, tipo]) => {
            const campo = crear("input", {type: tipo, value: instalador.administrador[clave],
                autocomplete: tipo === "password" ? "new-password" : "off"});
            campo.oninput = () => {
                instalador.administrador[clave] = campo.value;
            };
            administrador.append(crear("label", {className: "campo"}, texto, campo));
        });
    caja.append(administrador, navegacion(() => irA(2), () => irA(4)));
    return caja;
}

// ---------- Paso 4: revisar e instalar ----------

function pasoRevisar() {
    const caja = crear("div");
    caja.append(crear("h2", {textContent: "4. Revisar e instalar"}));
    const resultado = crear("div", {className: "resultado-instalador"});
    if (instalador.modo === "asistente") {
        const vista = crear("pre", {className: "sql-instalador", textContent: "Generando el SQL…"});
        enviarJson("vista_previa", {modelo: instalador.modelo})
            .then((datos) => {
                instalador.sql = datos.sql;
                vista.textContent = datos.sql;
            })
            .catch((error) => {
                vista.textContent = "";
                resultado.replaceChildren(crear("p", {className: "aviso-error", textContent: error.message}));
            });
        caja.append(
            crear("p", {textContent: "Este es el SQL que se ejecutará. Revísalo antes de instalar."}),
            vista,
            boton("Descargar schema.sql", "boton-descargar", () => descargar("schema.sql", instalador.sql, "text/plain"))
        );
    } else {
        caja.append(crear("p", {textContent: "Se validará y ejecutará «" + instalador.archivoSql.name + "»."}));
    }
    caja.append(crear("p", {textContent: "Datos iniciales: " + (instalador.semillas.length ? instalador.semillas.join(", ") : "ninguno") +
        ". Administrador: " + (instalador.administrador.email || "no se crea") + "."}));
    const instalar = boton("Instalar", "boton-crear", () => {
        if (!confirm("Se crearán las tablas en «" + instalador.estado.base + "». ¿Instalar?")) {
            return;
        }
        instalar.disabled = true;
        let promesa;
        if (instalador.modo === "asistente") {
            promesa = enviarJson("instalar", {modelo: instalador.modelo, semillas: instalador.semillas,
                administrador: instalador.administrador});
        } else {
            const datos = new FormData();
            datos.append("archivo", instalador.archivoSql);
            datos.append("token", instalador.token);
            instalador.semillas.forEach((codigo) => datos.append("semillas[]", codigo));
            Object.entries(instalador.administrador).forEach(([clave, valor]) => datos.append("administrador[" + clave + "]", valor));
            promesa = peticion("instalar_sql", {method: "POST", body: datos});
        }
        promesa.then((datos) => {
            resultado.replaceChildren(crear("h3", {textContent: "Instalación completada"}),
                crear("ul", {}, ...datos.mensajes.map((mensaje) => crear("li", {textContent: mensaje}))),
                crear("a", {className: "boton boton-crear", href: "login.html", textContent: "Ir a iniciar sesión"}));
        }).catch((error) => {
            resultado.replaceChildren(crear("p", {className: "aviso-error", textContent: error.message}));
            instalar.disabled = false;
        });
    });
    caja.append(navegacion(() => irA(3), null), instalar, resultado);
    return caja;
}

// ---------- Inicio ----------

peticion("estado").then((estado) => {
    instalador.estado = estado;
    instalador.semillas = estado.semillas.map((semilla) => semilla.codigo);
    const texto = document.querySelector("#estado-instalador");
    if (!estado.puede_instalar) {
        texto.textContent = "La aplicación ya está instalada. Para volver a ejecutar el instalador, " +
            "inicia sesión como administrador.";
        texto.className = "aviso-error";
        return;
    }
    texto.textContent = estado.en_uso
        ? "La aplicación ya está en uso: estás reinstalando como administrador (las tablas existentes no se borran)."
        : "Instalación nueva.";
    pintar();
}).catch((error) => {
    document.querySelector("#estado-instalador").textContent = error.message;
});
