// Fuente de datos del formulario de asignación conectada a la API REST.
// Contrato esperado (respuestas JSON, como arreglo o envueltas en { data: [...] }):
//   GET  /api/materias                        -> [{ id, nombre }]
//   GET  /api/materias/{materia}/grupos       -> [{ id, nombre }]
//   GET  /api/grupos/{grupo}/docentes         -> [{ id, nombre }]
//   POST /api/estudiantes/{estudiante}/asignaciones { materia_id, grupo_id, docente_id }
(function () {
    const form = document.getElementById('asignacion-form');
    const api = form.dataset.apiUrl.replace(/\/$/, '');
    const estudianteId = form.dataset.estudianteId;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function pedir(ruta, opciones = {}) {
        let respuesta;
        try {
            respuesta = await fetch(api + ruta, {
                ...opciones,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    ...opciones.headers,
                },
            });
        } catch (error) {
            throw new Error('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.');
        }

        const cuerpo = await respuesta.json().catch(() => null);
        if (!respuesta.ok) {
            throw new Error(mensajeDeError(respuesta.status, cuerpo));
        }
        return cuerpo && Object.prototype.hasOwnProperty.call(cuerpo, 'data') ? cuerpo.data : cuerpo;
    }

    function mensajeDeError(estado, cuerpo) {
        if (estado === 422 && cuerpo && cuerpo.errors) {
            return Object.values(cuerpo.errors).flat().join(' ');
        }
        if (cuerpo && cuerpo.message) {
            return cuerpo.message;
        }
        if (estado === 404) return 'El recurso solicitado no existe.';
        if (estado === 401 || estado === 403) return 'No tienes permiso para realizar esta acción.';
        return 'Ocurrió un error inesperado (' + estado + ').';
    }

    window.asignacionFuente = {
        materias: () => pedir('/materias'),
        grupos: (materiaId) => pedir('/materias/' + encodeURIComponent(materiaId) + '/grupos'),
        docentes: (grupoId) => pedir('/grupos/' + encodeURIComponent(grupoId) + '/docentes'),
        guardar: (datos) => pedir('/estudiantes/' + encodeURIComponent(estudianteId) + '/asignaciones', {
            method: 'POST',
            body: JSON.stringify(datos),
        }),
    };
})();
