// Selectores en cascada del formulario de asignación: materia → grupo → docente.
// Los datos llegan desde window.asignacionFuente, que expone materias(), grupos(materiaId),
// docentes(grupoId), todos asíncronos. guardar(datos) es opcional;
// si falta, el formulario se envía a su action mediante el flujo web.
(function () {
    const fuente = window.asignacionFuente;
    const form = document.getElementById('asignacion-form');
    const aviso = document.getElementById('asignacion-aviso');
    const boton = form.querySelector('button[type="submit"]');
    let guardando = false;

    const campos = {
        materia: { select: document.getElementById('materia'), help: document.getElementById('materia-help'),
                   placeholder: 'Selecciona una materia' },
        grupo: { select: document.getElementById('grupo'), help: document.getElementById('grupo-help'),
                 placeholder: 'Selecciona un grupo', pendiente: 'Elige primero una materia.' },
        docente: { select: document.getElementById('docente'), help: document.getElementById('docente-help'),
                   placeholder: 'Selecciona un docente', pendiente: 'Elige primero un grupo.' },
    };

    function mostrarAviso(tipo, mensaje) {
        aviso.className = 'aviso visible ' + tipo;
        aviso.textContent = mensaje;
    }

    function ocultarAviso() {
        aviso.className = 'aviso';
        aviso.textContent = '';
    }

    function reiniciar(campo, mensaje) {
        campo.version = (campo.version || 0) + 1;
        campo.select.replaceChildren(new Option(campo.placeholder, ''));
        campo.select.disabled = true;
        campo.help.textContent = mensaje;
    }

    async function cargar(campo, obtener, vacio) {
        reiniciar(campo, 'Cargando…');
        actualizarBoton();
        const version = campo.version;
        try {
            const items = await obtener();
            if (campo.version !== version) return;
            items.forEach(item => campo.select.add(new Option(item.nombre, item.id)));
            campo.select.disabled = items.length === 0;
            campo.help.textContent = items.length === 0 ? vacio : '';
        } catch (error) {
            if (campo.version !== version) return;
            campo.help.textContent = 'No se pudo cargar la lista.';
            mostrarAviso('error', error.message);
        }
    }

    function actualizarBoton() {
        boton.disabled = guardando || !Object.values(campos).every(campo =>
            !campo.select.disabled && campo.select.value !== '');
    }

    campos.materia.select.addEventListener('change', async () => {
        ocultarAviso();
        reiniciar(campos.docente, campos.docente.pendiente);
        actualizarBoton();
        const materiaId = campos.materia.select.value;
        if (materiaId === '') {
            reiniciar(campos.grupo, campos.grupo.pendiente);
        } else {
            await cargar(campos.grupo, () => fuente.grupos(materiaId), 'La materia no tiene grupos habilitados.');
        }
        actualizarBoton();
    });

    campos.grupo.select.addEventListener('change', async () => {
        ocultarAviso();
        const grupoId = campos.grupo.select.value;
        if (grupoId === '') {
            reiniciar(campos.docente, campos.docente.pendiente);
        } else {
            await cargar(campos.docente, () => fuente.docentes(grupoId), 'El grupo no tiene docentes asignados.');
        }
        actualizarBoton();
    });

    campos.docente.select.addEventListener('change', actualizarBoton);

    form.addEventListener('submit', async (event) => {
        if (typeof fuente.guardar !== 'function') return;
        event.preventDefault();
        if (boton.disabled) return;

        const datos = {
            materia_id: campos.materia.select.value,
            grupo_id: campos.grupo.select.value,
            docente_id: campos.docente.select.value,
        };
        guardando = true;
        Object.values(campos).forEach(campo => { campo.select.disabled = true; });
        actualizarBoton();
        mostrarAviso('info', 'Guardando asignación…');
        try {
            await fuente.guardar(datos);
            mostrarAviso('ok', 'Asignación registrada correctamente.');
        } catch (error) {
            mostrarAviso('error', error.message);
        } finally {
            guardando = false;
            Object.values(campos).forEach(campo => { campo.select.disabled = false; });
            actualizarBoton();
        }
    });

    cargar(campos.materia, fuente.materias, 'No hay materias disponibles.');
})();
