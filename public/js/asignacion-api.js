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

    function agregarAsignacion(asignacion, nombres) {
        const filas = document.getElementById('asignaciones-filas');
        const fila = document.createElement('tr');
        fila.dataset.asignacionId = asignacion.id;
        nombres.forEach((nombre, indice) => {
            const celda = document.createElement('td');
            celda.textContent = nombre;
            if (indice === 0) celda.className = 'strong';
            fila.append(celda);
        });

        const accion = document.createElement('td');
        const acciones = document.createElement('div');
        acciones.className = 'actions';
        const quitar = document.createElement('form');
        quitar.method = 'POST';
        quitar.action = form.dataset.deleteUrlTemplate.replace('__ASIGNACION__', encodeURIComponent(asignacion.id));
        [['_token', csrf], ['_method', 'DELETE']].forEach(([nombre, valor]) => {
            const campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = nombre;
            campo.value = valor;
            quitar.append(campo);
        });
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'icon-btn danger';
        boton.title = 'Quitar';
        boton.setAttribute('aria-label', 'Quitar ' + nombres[0]);
        boton.addEventListener('click', () => confirmarEliminar(quitar, '¿Quitar esta asignación?'));
        const icono = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icono.setAttribute('class', 'i');
        icono.setAttribute('aria-hidden', 'true');
        const uso = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        uso.setAttribute('href', '#i-trash');
        icono.append(uso);
        boton.append(icono);
        quitar.append(boton);
        acciones.append(quitar);
        accion.append(acciones);
        fila.append(accion);

        document.getElementById('asignaciones-vacio')?.remove();
        filas.append(fila);
        document.getElementById('asignaciones-total').textContent =
            filas.querySelectorAll('tr[data-asignacion-id]').length + ' materias asignadas';
    }

    window.asignacionFuente = {
        materias: () => pedir('/materias'),
        grupos: (materiaId) => pedir('/materias/' + encodeURIComponent(materiaId) + '/grupos'),
        docentes: (grupoId) => pedir('/grupos/' + encodeURIComponent(grupoId) + '/docentes'),
        guardar: async (datos) => {
            const nombres = ['materia', 'grupo', 'docente'].map(id =>
                document.getElementById(id).selectedOptions[0].textContent);
            const asignacion = await pedir('/estudiantes/' + encodeURIComponent(estudianteId) + '/asignaciones', {
                method: 'POST',
                body: JSON.stringify(datos),
            });
            agregarAsignacion(asignacion, nombres);
            return asignacion;
        },
    };
})();
