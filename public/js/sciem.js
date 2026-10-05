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

    d.showModal();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog').forEach(d => {
        const f = d.querySelector('form');

        if (f) {
            f.addEventListener('submit', () => {
                limpiarEstadoFormulario(f);
            });
        }

        d.addEventListener('click', e => {
            if (e.target === d) {
                guardarValoresFormulario(f);
                d.close();
            }
        });

        d.addEventListener('close', () => {
            if (d.dataset.closeMode === 'cancel') {
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
