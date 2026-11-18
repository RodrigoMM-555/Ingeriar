let tablaActual = "";
let vistaActual = "tabla";
let usuarioActual = null;

const entidadesOcultas = ["usuarios_educandos", "educandos_asistencias", "eventos"];

const formulario = new IngeriarFormulario({
    utilidades,
    alGuardar: function () {
        return vistaActual === "calendario"
            ? calendario.cargar("eventos")
            : tabla.cargar(tablaActual);
    }
});
const exportacion = new IngeriarExportacion({contenedor: "section"});
const documentos = new IngeriarDocumentos({utilidades});
const tabla = new IngeriarTabla({
    utilidades,
    formulario,
    exportacion,
    documentos
});
const configuracion = new IngeriarConfiguracion({
    utilidades,
    cabecera: "#acciones-cabecera"
});
const graficas = DarkorangeGraficas;
const dashboard = new IngeriarDashboard({
    utilidades,
    graficas,
    exportacion
});
const calendario = new IngeriarCalendario({
    utilidades,
    formulario,
    contenedor: "section"
});
const galeria = new IngeriarGaleria({
    utilidades,
    contenedor: "main > section"
});
const facturas = new IngeriarFacturas({
    utilidades,
    contenedor: "main > section"
});
const inventario = new IngeriarInventario({
    utilidades,
    contenedor: "main > section"
});

// Salir de Configuración con cambios sin guardar pide confirmación (E-18). Se escucha en
// la fase de captura para poder cancelar el clic antes de que cambie la vista.
document.addEventListener("click", (evento) => {
    const enlace = evento.target.closest("#entidades a, #modulos a");
    if (enlace && configuracion.hayCambiosSinGuardar() &&
        !confirm("Hay cambios sin guardar en Configuración. ¿Salir igualmente?")) {
        evento.preventDefault();
        evento.stopImmediatePropagation();
    }
}, true);
window.addEventListener("beforeunload", (evento) => {
    if (configuracion.hayCambiosSinGuardar()) {
        evento.preventDefault();
    }
});

window.addEventListener("configuracion-actualizada", () => {
    ordenarMenu(configuracion.configuracionActual?.orden_menu);
    if (vistaActual === "tabla" && tablaActual) {
        tabla.cargar(tablaActual);
    } else if (vistaActual === "calendario") {
        calendario.cargar("eventos");
    }
});

function marcarEnlaceActivo(enlace) {
    document.querySelectorAll("#entidades a")
        .forEach(function (elemento) {
            elemento.classList.remove("activo");
        });
    enlace.classList.add("activo");
}

function crearEnlaceDashboard() {
    let enlace = document.createElement("a");
    enlace.href = "#";
    enlace.textContent = "Dashboard";
    enlace.dataset.clave = "dashboard";
    enlace.onclick = function (evento) {
        evento.preventDefault();
        marcarEnlaceActivo(enlace);
        vistaActual = "dashboard";
        dashboard.render();
    };
    document.querySelector("#entidades").appendChild(enlace);
    return enlace;
}

function crearEnlaceCalendario() {
    let enlace = document.createElement("a");
    enlace.href = "#";
    enlace.textContent = "Calendario";
    enlace.dataset.clave = "calendario";
    enlace.onclick = function (evento) {
        evento.preventDefault();
        marcarEnlaceActivo(enlace);
        vistaActual = "calendario";
        calendario.cargar("eventos").catch((error) => {
            document.querySelector("section").textContent = error.message;
        });
    };
    document.querySelector("#entidades").appendChild(enlace);
}

function crearEnlaceGaleria() {
    let enlace = document.createElement("a");
    enlace.href = "#";
    enlace.textContent = "Galería";
    enlace.dataset.clave = "galeria";
    enlace.onclick = function (evento) {
        evento.preventDefault();
        marcarEnlaceActivo(enlace);
        vistaActual = "galeria";
        galeria.cargar().catch((error) => {
            document.querySelector("main > section").textContent = error.message;
        });
    };
    document.querySelector("#entidades").appendChild(enlace);
}

// Vista de facturas en tablero (E-05), para quien puede subir o ver facturas.
function crearEnlaceFacturas() {
    if (!utilidades.tienePermiso("facturas.subir_propias") &&
        !utilidades.tienePermiso("facturas.ver_todas")) {
        return;
    }
    let enlace = document.createElement("a");
    enlace.href = "#";
    enlace.textContent = "Facturas";
    enlace.dataset.clave = "facturas";
    enlace.onclick = function (evento) {
        evento.preventDefault();
        marcarEnlaceActivo(enlace);
        vistaActual = "facturas";
        facturas.cargar().catch((error) => {
            document.querySelector("main > section").textContent = error.message;
        });
    };
    document.querySelector("#entidades").appendChild(enlace);
}

// Inventario y listas de material (E-04), para administradores y monitores.
function crearEnlaceInventario() {
    if (!utilidades.tienePermiso("inventario.gestionar")) {
        return;
    }
    let enlace = document.createElement("a");
    enlace.href = "#";
    enlace.textContent = "Inventario";
    enlace.dataset.clave = "inventario";
    enlace.onclick = function (evento) {
        evento.preventDefault();
        marcarEnlaceActivo(enlace);
        vistaActual = "inventario";
        inventario.cargar().catch((error) => {
            document.querySelector("main > section").textContent = error.message;
        });
    };
    document.querySelector("#entidades").appendChild(enlace);
}

function cargarModulos() {
    utilidades.obtenerJson("api/superapi.php?ruta=modulos")
        .then(function (datos) {
            let menu = document.querySelector("#modulos");

            datos.forEach(function (dato) {
                let enlace = document.createElement("a");
                let icono = document.createElement("span")
                icono.classList.add("icono-modulos")
                let texto = document.createElement("span")
                texto.textContent = utilidades.capitalizar(dato);
                icono.textContent = utilidades.capitalizar(dato).charAt(0)
                enlace.href = "#";
                enlace.onclick = function (evento) {
                    evento.preventDefault();
                    document.querySelectorAll("#modulos a")
                        .forEach(function (elemento) {
                            elemento.classList.remove("activo");
                        });
                    enlace.classList.add("activo");
                };
                enlace.appendChild(icono);
                enlace.appendChild(texto)
                menu.appendChild(enlace);
            });
        });
}

// Ordena el menú lateral según la lista guardada en Configuración («Menú de navegación»)
// o, si no hay, según IngeriarConfiguracion.ORDEN_MENU_PREDETERMINADO. Lo que no esté en la
// lista (una pantalla o tabla nueva) va al final, en el orden en que se creó.
function ordenarMenu(orden) {
    const lista = Array.isArray(orden) && orden.length
        ? orden
        : IngeriarConfiguracion.ORDEN_MENU_PREDETERMINADO;
    const menu = document.querySelector("#entidades");
    const enlaces = Array.from(menu.querySelectorAll(":scope > a"));
    const posicion = (enlace) => {
        const indice = lista.indexOf(enlace.dataset.clave);
        return indice === -1 ? lista.length + enlaces.indexOf(enlace) : indice;
    };
    enlaces.sort((a, b) => posicion(a) - posicion(b))
        .forEach((enlace) => menu.appendChild(enlace));
}

function cargarEntidades(promesaConfiguracion) {
    Promise.all([
        utilidades.obtenerJson("api/superapi.php?ruta=entidades"),
        promesaConfiguracion.catch(() => null)
    ])
        .then(function ([datos, datosConfiguracion]) {
            let menu = document.querySelector("#entidades");

            datos.forEach(function (dato) {
                if (!entidadesOcultas.includes(dato)) {
                    let enlace = document.createElement("a");
                    enlace.textContent = utilidades.capitalizar(dato);
                    enlace.dataset.clave = dato;
                    enlace.href = "#";
                    enlace.onclick = function (evento) {
                        evento.preventDefault();
                        tablaActual = dato;
                        marcarEnlaceActivo(enlace);
                        vistaActual = "tabla";
                        tabla.cargar(tablaActual).catch((error) => {
                            const seccion = document.querySelector("main > section");
                            seccion.className = "vista vista-tabla";
                            seccion.innerHTML = "";
                            const mensaje = document.createElement("p");
                            mensaje.className = "aviso-error";
                            mensaje.textContent =
                                "No se pudo cargar " + dato + ": " + error.message;
                            seccion.appendChild(mensaje);
                        });
                    };
                    menu.appendChild(enlace);
                }
            });

            if (datos.length > 0) {
                const enlaceDashboard = crearEnlaceDashboard();
                crearEnlaceCalendario();
                crearEnlaceGaleria();
                crearEnlaceInventario();
                crearEnlaceFacturas();
                ordenarMenu(datosConfiguracion?.orden_menu);
                tablaActual = datos[0];
                enlaceDashboard.classList.add("activo");
                vistaActual = "dashboard";
                dashboard.render();
            }
        });
}

function crearRedimensionable(elemento) {
    const separador = document.createElement("div");
    separador.classList.add("separador-redimensionable");

    elemento.after(separador);

    separador.addEventListener("mousedown", function (evento) {
        evento.preventDefault();

        const inicioX = evento.clientX;
        const anchoInicial = elemento.offsetWidth;

        function mover(evento) {
            const diferencia = evento.clientX - inicioX;
            const nuevoAncho = anchoInicial + diferencia;

            elemento.style.width = `${Math.max(100, nuevoAncho)}px`;
        }

        function soltar() {
            document.removeEventListener("mousemove", mover);
            document.removeEventListener("mouseup", soltar);
        }

        document.addEventListener("mousemove", mover);
        document.addEventListener("mouseup", soltar);
    });
}

function iniciarAplicacion() {
    utilidades.obtenerJson("api/superapi.php?ruta=usuario_actual")
        .then((datos) => {
            usuarioActual = datos.usuario;
            calendario.establecerUsuario(usuarioActual);
            const nombreCompleto = [
                usuarioActual.nombre,
                usuarioActual.apellidos
            ].filter(Boolean).join(" ");
            document.querySelector("#usuario-actual").textContent =
                nombreCompleto;
            document.querySelector("#rol-actual").textContent =
                utilidades.nombreCargos(usuarioActual);
            document.querySelector("#avatar-usuario").textContent =
                (usuarioActual.nombre || "?").charAt(0).toUpperCase();
            const promesaConfiguracion = configuracion.cargar();
            cargarModulos();
            cargarEntidades(promesaConfiguracion);
            crearRedimensionable(document.querySelector("#modulos"));
            crearRedimensionable(document.querySelector("#entidades"));
            if (utilidades.tienePermiso("configuracion.gestionar")) {
                configuracion.crearBoton();
            }
        })
        .catch(() => {
            window.location.href = "login.html";
        });
}

iniciarAplicacion();
