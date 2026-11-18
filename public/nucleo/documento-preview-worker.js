// Web Worker de miniaturas (galería, facturas y circulares). Se ejecuta en un hilo aparte
// para que descargar y reducir las imágenes no pare la página. Lo usa un grupo fijo de
// trabajadores (IngeriarUtilidades): cada mensaje lleva un id y la respuesta lo devuelve,
// así un mismo trabajador atiende muchas miniaturas, una detrás de otra.
self.onmessage = async (evento) => {
    const {id, url} = evento.data;
    try {
        const respuesta = await fetch(url, {credentials: "include"});
        if (!respuesta.ok) {
            throw new Error("No se pudo cargar el documento");
        }
        const blob = await respuesta.blob();
        if (!blob.type.startsWith("image/") || !self.createImageBitmap) {
            self.postMessage({id, kind: "document"});
            return;
        }

        const imagen = await self.createImageBitmap(blob);
        const escala = Math.min(1, 320 / imagen.width, 240 / imagen.height);
        const canvas = new OffscreenCanvas(
            Math.max(1, Math.round(imagen.width * escala)),
            Math.max(1, Math.round(imagen.height * escala))
        );
        canvas.getContext("2d").drawImage(
            imagen, 0, 0, canvas.width, canvas.height
        );
        imagen.close();
        const vista = await canvas.convertToBlob({type: "image/jpeg", quality: 0.82});
        self.postMessage({id, kind: "image", blob: vista});
    } catch (error) {
        self.postMessage({id, kind: "error", message: error.message});
    }
};
