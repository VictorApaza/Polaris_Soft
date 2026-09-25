// Selectores en cascada del formulario de asignación: materia → grupo → docente.
// Los datos llegan desde window.asignacionFuente, que expone materias(), grupos(materiaId),
// docentes(grupoId) y guardar(datos), todos asíncronos.
(function () {
    const fuente = window.asignacionFuente;
    const form = document.getElementById('asignacion-form');
    const aviso = document.getElementById('asignacion-aviso');
    const boton = form.querySelector('button[type="submit"]');

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
        campo.select.replaceChildren(new Option(campo.placeholder, ''));
        campo.select.disabled = true;
        campo.help.textContent = mensaje;
    }

    async function cargar(campo, obtener, vacio) {
        reiniciar(campo, 'Cargando…');
        try {
            const items = await obtener();
            items.forEach(item => campo.select.add(new Option(item.nombre, item.id)));
            campo.select.disabled = items.length === 0;
            campo.help.textContent = items.length === 0 ? vacio : '';
        } catch (error) {
            campo.help.textContent = 'No se pudo cargar la lista.';
            mostrarAviso('error', error.message);
        }
    }

    function actualizarBoton() {
        boton.disabled = !Object.values(campos).every(campo => campo.select.value !== '');
    }

    campos.materia.select.addEventListener('change', async () => {
        ocultarAviso();
        reiniciar(campos.docente, campos.docente.pendiente);
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
        event.preventDefault();
        if (boton.disabled) return;

        boton.disabled = true;
        mostrarAviso('info', 'Guardando asignación…');
        try {
            await fuente.guardar({
                materia_id: campos.materia.select.value,
                grupo_id: campos.grupo.select.value,
                docente_id: campos.docente.select.value,
            });
            mostrarAviso('ok', 'Asignación registrada correctamente.');
        } catch (error) {
            mostrarAviso('error', error.message);
        } finally {
            actualizarBoton();
        }
    });

    cargar(campos.materia, fuente.materias, 'No hay materias disponibles.');
})();
