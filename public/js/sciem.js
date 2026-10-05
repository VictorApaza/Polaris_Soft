/**
 * Modales <dialog> reutilizables para crear / ver / editar.
 *   abrirForm('modal-id', { url, method, values, title, ro, keep })
 *   - values: {campo: valor} para rellenar el formulario (si falta, se limpia)
 *   - ro: modo solo lectura (oculta el botón de guardar)
 *   - keep: conserva lo que ya hay en los campos (para reabrir tras un error de validación)
 */
function limpiarEstadoFormulario(form) {
    if (!form) return;

    const dialogId = form.closest('dialog')?.id || form.id;
    if (dialogId) {
        sessionStorage.removeItem('dialog-state:' + dialogId);
    }
}

function guardarValoresFormulario(form) {
    if (!form) return;

    const snapshot = {};
    [...form.elements].forEach(el => {
        if (!el.name || el.name[0] === '_') return;
        if (el.type === 'radio' || el.type === 'checkbox') {
            snapshot[el.name] = el.checked ? el.value : '';
            return;
        }
        if (el.type !== 'submit' && el.type !== 'button' && el.type !== 'reset') {
            snapshot[el.name] = el.value ?? '';
        }
    });

    const dialogId = form.closest('dialog')?.id || form.id;
    if (dialogId) {
        sessionStorage.setItem('dialog-state:' + dialogId, JSON.stringify(snapshot));
    }
}

function restaurarValoresFormulario(form) {
    if (!form) return false;

    const dialogId = form.closest('dialog')?.id || form.id;
    const raw = dialogId ? sessionStorage.getItem('dialog-state:' + dialogId) : null;
    if (!raw) return false;

    let snapshot;
    try {
        snapshot = JSON.parse(raw);
    } catch {
        return false;
    }

    let restaurado = false;
    [...form.elements].forEach(el => {
        if (!el.name || el.name[0] === '_') return;
        if (!(el.name in snapshot)) return;

        if (el.type === 'radio') {
            el.checked = String(snapshot[el.name]) === String(el.value);
            restaurado = true;
            return;
        }

        if (el.type === 'checkbox') {
            el.checked = !!snapshot[el.name];
            restaurado = true;
            return;
        }

        if (el.type !== 'submit' && el.type !== 'button' && el.type !== 'reset') {
            el.value = snapshot[el.name] ?? '';
            restaurado = true;
        }
    });

    return restaurado;
}

function abrirForm(id, o = {}) {
    const d = document.getElementById(id);
    const f = d.querySelector('form');
    const values = o.values || {};
    const debeRestaurar = !o.keep && Object.keys(values).length === 0 && restaurarValoresFormulario(f);

    [...f.elements].forEach(el => {
        if (!el.name || el.name[0] === '_' ) return;
        if (!o.keep && !debeRestaurar) {
            if (el.type === 'radio') {
                el.checked = (el.name in values) ? String(values[el.name]) === el.value : el.defaultChecked;
            } else if (el.type !== 'submit' && el.type !== 'button') {
                el.value = (el.name in values) ? (values[el.name] ?? '') : (el.dataset.default ?? '');
            }
        }
        el.disabled = !!o.ro;
    });

    const ed = f.querySelector('[name=_editing]');
    if (ed && !o.keep) ed.value = o.editing || '';

    if (o.url) f.action = o.url;
    const m = f.querySelector('input[name=_method]');
    if (m) { m.value = o.method || 'POST'; m.disabled = (o.method || 'POST') === 'POST'; }

    const t = d.querySelector('[data-title]');
    if (t && o.title) t.textContent = o.title;
    const s = f.querySelector('[data-submit]');
    if (s) s.hidden = !!o.ro;
    f.querySelectorAll('.err').forEach(e => { if (!o.keep) e.remove(); });

    d.dataset.modo = (o.ro || o.editing) ? 'edit' : 'nuevo';
    const btnGuardar = f.querySelector('[data-submit]');
    if (btnGuardar) btnGuardar.disabled = false;

    d.showModal();
    // Se toma la "foto" inicial después de rellenar el formulario (los scripts de cada página
    // ajustan algunos campos justo después de abrirForm, por eso se difiere un instante).
    setTimeout(() => { d._valoresIniciales = valoresFormulario(f); }, 0);
}

/** Valores actuales de los campos (sin los ocultos que empiezan con "_"). */
function valoresFormulario(form) {
    const v = {};
    [...form.elements].forEach(el => {
        if (!el.name || el.name[0] === '_' || ['submit', 'button', 'reset'].includes(el.type)) return;
        v[el.name] = (el.type === 'radio' || el.type === 'checkbox') ? (el.checked ? el.value : '') : (el.value ?? '');
    });
    return JSON.stringify(v);
}

/** true si el usuario cambió algo respecto a lo que tenía al abrir el modal. */
function formularioModificado(form) {
    const d = form?.closest('dialog');
    if (!form || !d || d._valoresIniciales === undefined) return false;
    return valoresFormulario(form) !== d._valoresIniciales;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog').forEach(d => {
        const f = d.querySelector('form');

        if (f) {
            f.addEventListener('submit', () => {
                limpiarEstadoFormulario(f);
                // Evita el doble clic en "Guardar": se bloquea el botón al enviar.
                const btn = f.querySelector('[data-submit]');
                if (btn) setTimeout(() => { btn.disabled = true; }, 0);
            });
        }

        d.addEventListener('click', e => {
            if (e.target !== d) return;
            // Clic en el fondo oscuro: si hay datos escritos NO se cierra (así no se pierde nada).
            if (formularioModificado(f)) return;
            d.close();
        });

        d.addEventListener('close', () => {
            // Solo el formulario de "nuevo" conserva lo escrito; editar/ver nunca se guardan.
            if (d.dataset.closeMode === 'cancel' || d.dataset.modo !== 'nuevo') {
                limpiarEstadoFormulario(f);
            } else {
                guardarValoresFormulario(f);
            }
            delete d.dataset.closeMode;
        });
    });
});

/** Borra, mientras se escribe, cualquier carácter que no sea letra o espacio. Uso: oninput="soloLetras(this)". */
function soloLetras(el) {
    const cursor = el.selectionStart;
    const limpio = el.value.replace(/[^\p{L}\s]/gu, '');
    if (limpio !== el.value) {
        const borrados = el.value.length - limpio.length;
        el.value = limpio;
        el.setSelectionRange(cursor - borrados, cursor - borrados);
    }
}

/** Borra, mientras se escribe, cualquier carácter que no sea dígito. Uso: oninput="soloNumeros(this)". */
function soloNumeros(el) {
    const cursor = el.selectionStart;
    const limpio = el.value.replace(/[^0-9]/g, '');
    if (limpio !== el.value) {
        const borrados = el.value.length - limpio.length;
        el.value = limpio;
        el.setSelectionRange(cursor - borrados, cursor - borrados);
    }
}

/**
 * Modal pequeño de confirmación (reemplaza al confirm() nativo del navegador).
 * Requiere en la página un <dialog id="modal-confirmar" class="dialog-sm"> con:
 *   - un elemento [data-confirmar-mensaje] para el texto
 *   - un botón [data-confirmar-ok] que confirma la acción
 * Uso: en vez de <form onsubmit="return confirm('...')">, usar en el botón de eliminar:
 *   <button type="button" onclick="confirmarEliminar(this.closest('form'), '¿Eliminar...?')">
 */
function confirmarEliminar(form, mensaje) {
    const d = document.getElementById('modal-confirmar');
    if (!d) { if (confirm(mensaje)) form.submit(); return; } // respaldo si la página no tiene el modal
    d.querySelector('[data-confirmar-mensaje]').textContent = mensaje;
    d.querySelector('[data-confirmar-ok]').onclick = () => { d.close(); form.submit(); };
    d.showModal();
}
