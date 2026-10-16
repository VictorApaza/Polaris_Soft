const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

const codigo = readFileSync(join(__dirname, '../../public/js/asignacion.js'), 'utf8');

function pendiente() {
    let resolve, reject;
    const promise = new Promise((ok, error) => { resolve = ok; reject = error; });
    return { promise, resolve, reject };
}

async function formulario(cambios = {}) {
    const elementos = {};
    for (const id of ['materia', 'grupo', 'docente', 'materia-help', 'grupo-help',
        'docente-help', 'asignacion-aviso', 'asignacion-form', 'boton']) {
        elementos[id] = {
            value: '', disabled: true, textContent: '', options: [], eventos: {},
            addEventListener(nombre, listener) { this.eventos[nombre] = listener; },
            replaceChildren(...options) { this.options = options; this.value = ''; },
            add(option) { this.options.push(option); },
        };
    }
    elementos['asignacion-form'].querySelector = () => elementos.boton;
    runInNewContext(codigo, {
        document: { getElementById: id => elementos[id] },
        Option: class { constructor(text, value) { this.text = text; this.value = String(value); } },
        window: { asignacionFuente: {
            materias: async () => [{ id: 1, nombre: 'Materia 1' }, { id: 2, nombre: 'Materia 2' }],
            grupos: async id => [{ id: id + '0', nombre: 'Grupo ' + id }],
            docentes: async id => [{ id: id + '0', nombre: 'Docente ' + id }],
            guardar: async () => {},
            ...cambios,
        } },
    });
    await new Promise(setImmediate);
    return elementos;
}

function elegir(ui, campo, value) {
    ui[campo].value = value;
    return ui[campo].eventos.change();
}

async function completar(ui) {
    await elegir(ui, 'materia', '1');
    await elegir(ui, 'grupo', '10');
    await elegir(ui, 'docente', '100');
}

function guardar(ui) {
    return ui['asignacion-form'].eventos.submit({ preventDefault() {} });
}

test('al cambiar la materia se vacían los descendientes y se bloquea el guardado inmediatamente', async () => {
    const carga = pendiente();
    const ui = await formulario({ grupos: id => id === '1'
        ? Promise.resolve([{ id: 10, nombre: 'Grupo 1' }]) : carga.promise });
    await completar(ui);
    assert.equal(ui.boton.disabled, false);
    const cambio = elegir(ui, 'materia', '2');
    assert.equal(ui.boton.disabled, true);
    assert.equal(ui.grupo.value, '');
    assert.equal(ui.docente.value, '');
    assert.equal(ui.docente.disabled, true);
    carga.resolve([]);
    await cambio;
    assert.equal(ui.grupo.disabled, true);
});

test('una respuesta de grupos anterior no reemplaza la selección más reciente', async () => {
    const primera = pendiente(), segunda = pendiente();
    const ui = await formulario({ grupos: id => id === '1' ? primera.promise : segunda.promise });
    const cambio1 = elegir(ui, 'materia', '1');
    const cambio2 = elegir(ui, 'materia', '2');
    segunda.resolve([{ id: 20, nombre: 'Grupo vigente' }]);
    await cambio2;
    primera.resolve([{ id: 10, nombre: 'Grupo anterior' }]);
    await cambio1;
    assert.deepEqual(ui.grupo.options.map(o => o.value), ['', '20']);
});

test('una carga pendiente de docentes se descarta al cambiar de materia', async () => {
    const carga = pendiente();
    const ui = await formulario({ docentes: () => carga.promise });
    await elegir(ui, 'materia', '1');
    const grupo = elegir(ui, 'grupo', '10');
    await elegir(ui, 'materia', '2');
    carga.resolve([{ id: 100, nombre: 'Docente anterior' }]);
    await grupo;
    assert.equal(ui.docente.disabled, true);
    assert.deepEqual(ui.docente.options.map(o => o.value), ['']);
});

test('un error de una solicitud anterior no aparece sobre una lista actualizada', async () => {
    const anterior = pendiente();
    const ui = await formulario({ grupos: id => id === '1' ? anterior.promise
        : Promise.resolve([{ id: 20, nombre: 'Grupo vigente' }]) });
    const cambio = elegir(ui, 'materia', '1');
    await elegir(ui, 'materia', '2');
    anterior.reject(new Error('Error anterior'));
    await cambio;
    assert.equal(ui['asignacion-aviso'].textContent, '');
    assert.equal(ui.grupo.disabled, false);
});

test('el guardado bloquea los campos y evita solicitudes repetidas', async () => {
    const envio = pendiente();
    let llamadas = 0;
    const ui = await formulario({ guardar: datos => {
        llamadas++;
        assert.deepEqual(JSON.parse(JSON.stringify(datos)), { materia_id: '1', grupo_id: '10', docente_id: '100' });
        return envio.promise;
    } });
    await completar(ui);
    const solicitud = guardar(ui);
    assert.equal(ui.boton.disabled, true);
    for (const campo of ['materia', 'grupo', 'docente']) assert.equal(ui[campo].disabled, true);
    await guardar(ui);
    assert.equal(llamadas, 1);
    envio.resolve();
    await solicitud;
    assert.equal(ui.boton.disabled, false);
    assert.equal(ui['asignacion-aviso'].textContent, 'Asignación registrada correctamente.');
});

test('tras un fallo al guardar se conservan las selecciones y se permite reintentar', async () => {
    const ui = await formulario({ guardar: async () => { throw new Error('No se pudo conectar'); } });
    await completar(ui);
    await guardar(ui);
    assert.equal(ui.docente.value, '100');
    assert.equal(ui.docente.disabled, false);
    assert.equal(ui.boton.disabled, false);
    assert.equal(ui['asignacion-aviso'].textContent, 'No se pudo conectar');
});

test('sin API se conserva el envío normal del formulario y sus campos habilitados', async () => {
    const ui = await formulario({ guardar: undefined });
    await completar(ui);
    let cancelado = false;
    await ui['asignacion-form'].eventos.submit({ preventDefault() { cancelado = true; } });
    assert.equal(cancelado, false);
    assert.equal(ui.materia.disabled, false);
    assert.equal(ui.grupo.disabled, false);
    assert.equal(ui.docente.disabled, false);
});
