@extends('layouts.app')
@section('title', 'Control de ingreso')

@push('styles')
<style>
    .verification-hero {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
    }

    .verification-logo {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(30, 58, 138, 0.12), rgba(12, 74, 110, 0.08));
        border: 1px solid rgba(30, 58, 138, 0.14);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--navy);
        box-shadow: 0 8px 18px rgba(15, 29, 77, 0.08);
    }

    .verification-logo .i {
        width: 26px;
        height: 26px;
    }

    .verification-result {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .verification-context {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(220px, 1fr);
        gap: 18px;
        align-items: end;
    }

    .context-summary {
        grid-column: 1 / -1;
        min-height: 22px;
        color: var(--muted);
        font-size: 12px;
    }

    .result-heading {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--line);
    }

    .result-heading .status-pill {
        font-size: 13px;
        padding: 8px 14px;
    }

    .result-heading .result-caption {
        color: var(--muted);
        font-size: 12px;
    }

    .student-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 10px 24px rgba(15, 29, 77, 0.04);
    }

    .student-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }

    .student-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--navy);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .status-pill.success {
        background: rgba(21, 128, 61, 0.12);
        color: var(--ok);
    }

    .status-pill.danger {
        background: rgba(220, 38, 38, 0.12);
        color: var(--danger);
    }

    .status-pill.warn {
        background: rgba(180, 83, 9, 0.12);
        color: var(--warn);
    }

    .student-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 18px;
    }

    .student-grid .field {
        margin: 0;
    }

    .student-grid .field label,
    .student-grid .field .value {
        font-size: 12px;
    }

    .student-grid .value {
        padding-top: 6px;
        color: var(--text);
        font-weight: 600;
        line-height: 1.5;
    }

    .result-actions {
        margin-top: 12px;
    }

    .banner {
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.45;
        border: 1px solid transparent;
    }

    .banner.danger {
        background: rgba(220, 38, 38, 0.08);
        color: var(--danger);
        border-color: rgba(220, 38, 38, 0.14);
    }

    .banner.warn {
        background: rgba(180, 83, 9, 0.08);
        color: var(--warn);
        border-color: rgba(180, 83, 9, 0.16);
    }

    .banner.ok {
        background: rgba(21, 128, 61, 0.08);
        color: var(--ok);
        border-color: rgba(21, 128, 61, 0.14);
    }

    @media (max-width: 768px) {
        .verification-context {
            grid-template-columns: 1fr;
        }

        .context-summary {
            grid-column: auto;
        }

        .student-grid {
            grid-template-columns: 1fr;
        }

        .verification-hero {
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Control de verificacion</b></nav>

    <div class="verification-hero">
        <div class="verification-logo">
            <svg class="i"><use href="#i-id"/></svg>
        </div>
        <div>
            <h1>Verificación de identidad</h1>
            <p>Ingresa o escanea el código universitario del estudiante para validar su ingreso.</p>
        </div>
    </div>

    <section class="card">
        <div class="verification-context">
            <div class="field">
                <label for="id_examen">Examen</label>
                <select id="id_examen">
                    <option value="">Selecciona un examen…</option>
                    @foreach ($examenes as $ex)
                        <option value="{{ $ex->id }}" data-ambiente="{{ $ex->ambiente }}" data-capacidad="{{ $ex->capacidad }}">
                            {{ $ex->materia->nombre ?? '—' }} ({{ $ex->carrera }}) — {{ $ex->fecha->format('d/m/Y') }} {{ $ex->hora }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div id="info-examen" class="context-summary" aria-live="polite">Selecciona un examen para comenzar.</div>
        </div>
    </section>

    <section class="card">
        <form id="form-verificacion">
            <div class="field">
                <label for="codigo">Código universitario</label>
                <div style="display:flex;gap:12px;align-items:center;">
                    <input type="text" id="codigo" placeholder="Escanea o digita el código…" disabled autocomplete="off" autofocus style="flex:1;">
                    <button type="submit" class="btn" id="btn-verificar" disabled>Verificar</button>
                </div>
                <small class="sub" style="display:block;margin-top:8px">Presiona Enter o Verificar para mostrar los datos del estudiante.</small>
            </div>
        </form>
    </section>

    <section class="card" id="panel-resultado" style="display:none;">
        <div class="field">
            <label>Resultado</label>
            <div id="contenido-resultado" class="verification-result"></div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const selExamen    = document.getElementById('id_examen');
    const formVerificacion = document.getElementById('form-verificacion');
    const infoExamen   = document.getElementById('info-examen');
    const inputCodigo  = document.getElementById('codigo');
    const btnVerificar = document.getElementById('btn-verificar');
    const panel        = document.getElementById('panel-resultado');
    const contenido    = document.getElementById('contenido-resultado');

    let ultimaVerificacion = null;

    function actualizarContexto() {
        const opcionExamen = selExamen.selectedOptions[0];
        const examenSeleccionado = !!selExamen.value;
        const listo = examenSeleccionado;

        inputCodigo.disabled = !listo;
        btnVerificar.disabled = !listo;

        if (!examenSeleccionado) {
            infoExamen.textContent = 'Selecciona un examen para comenzar.';
        } else {
            infoExamen.textContent = `${opcionExamen.textContent.trim()} · Ambiente: ${opcionExamen.dataset.ambiente} · Capacidad: ${opcionExamen.dataset.capacidad}`;
        }

        return listo;
    }

    selExamen.addEventListener('change', () => {
        const listo = actualizarContexto();
        panel.style.display = 'none';
        ultimaVerificacion = null;
        if (listo) inputCodigo.focus();
    });

    function banner(tipo, mensaje) {
        return `<div class="banner ${tipo}">${mensaje}</div>`;
    }

    function ficha(e) {
        return `
            <div class="student-card">
                <div class="student-header">
                    <div class="student-title">Datos del estudiante</div>
                    <span class="status-pill ${e.estado && e.estado.toUpperCase() === 'ACTIVO' ? 'success' : 'warn'}">${e.estado || 'ACTIVO'}</span>
                </div>
                <div class="student-grid">
                    <div class="field">
                        <label>Código</label>
                        <div class="value">${e.codigo}</div>
                    </div>
                    <div class="field">
                        <label>Foto</label>
                        <div class="value">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:#e0e7ff;color:#1e3a8a;font-weight:700;">${(e.nombres || 'E').charAt(0).toUpperCase()}${(e.apellidos || 'S').charAt(0).toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="field">
                        <label>Nombre completo</label>
                        <div class="value">${e.apellidos}, ${e.nombres}</div>
                    </div>
                    <div class="field">
                        <label>Carnet</label>
                        <div class="value">${e.carnet}</div>
                    </div>
                    <div class="field">
                        <label>Carrera</label>
                        <div class="value">${e.carrera}</div>
                    </div>
                    <div class="field">
                        <label>Documento</label>
                        <div class="value">${e.documento_identidad || '—'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function estadoResultado(estado, detalle = '') {
        const clase = estado === 'HABILITADO' ? 'success' : (estado === 'NO HABILITADO' ? 'danger' : 'warn');
        return `<div class="result-heading"><span class="status-pill ${clase}">${estado}</span>${detalle ? `<span class="result-caption">${detalle}</span>` : ''}</div>`;
    }

    function mostrar(html) {
        contenido.innerHTML = html;
        panel.style.display = 'block';
    }

    function limpiarYEnfocar() {
        inputCodigo.value = '';
        inputCodigo.focus();
    }

    formVerificacion.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!actualizarContexto()) {
            mostrar(banner('warn', 'Selecciona un examen antes de verificar.'));
            return;
        }
        const codigo = inputCodigo.value.trim();
        if (!codigo) return;
        btnVerificar.disabled = true;

        try {
            const res = await fetch("{{ route('verificacion.verificar') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ id_examen: selExamen.value, codigo_universitario: codigo })
            });
            const data = await res.json();

            if (data.tipo === 'codigo_no_registrado') {
                mostrar(estadoResultado('NO REGISTRADO') + banner('danger', `⚠ ${data.mensaje}`));
                ultimaVerificacion = null;
            } else if (data.tipo === 'documento_no_coincide') {
                mostrar(estadoResultado('IDENTIDAD NO COINCIDE') + ficha(data.estudiante) + banner('danger', `⚠ ${data.mensaje}`));
                ultimaVerificacion = null;
            } else if (data.tipo === 'inactivo') {
                mostrar(estadoResultado('INACTIVO') + ficha(data.estudiante) + banner('danger', `⚠ ${data.mensaje}`));
                ultimaVerificacion = null;
            } else if (data.tipo === 'ya_ingreso') {
                const fh = data.fecha_hora ? new Date(data.fecha_hora.replace(' ', 'T')).toLocaleString('es-BO') : 'anteriormente';
                mostrar(estadoResultado('YA INGRESÓ', `Ingreso registrado: ${fh}`) + ficha(data.estudiante));
                ultimaVerificacion = null;
            } else if (data.tipo === 'no_habilitado') {
                mostrar(estadoResultado('NO HABILITADO') + ficha(data.estudiante) + banner('danger', `Motivo: ${data.motivo_inhabilitacion || data.mensaje}`));
                ultimaVerificacion = null;
            } else if (data.tipo === 'habilitado') {
                ultimaVerificacion = {
                    id_estudiante: data.estudiante.id,
                    id_examen: selExamen.value,
                    id_habilitacion: data.id_habilitacion,
                    codigo_universitario: data.codigo,
                };
                mostrar(
                    estadoResultado('HABILITADO', 'Identidad lista para confirmar') +
                    ficha(data.estudiante) +
                    `<div class="result-actions"><button class="btn" id="btn-ingreso" type="button">Registrar ingreso</button></div>`
                );
                document.getElementById('btn-ingreso').addEventListener('click', registrarIngreso);
            } else {
                ultimaVerificacion = null;
                const mensaje = data.mensaje || data.message || 'No se pudo completar la verificación.';
                mostrar((data.estudiante ? ficha(data.estudiante) : '') + banner('danger', mensaje));
            }
        } catch (error) {
            mostrar(banner('danger', 'Error de conexión. Intenta nuevamente.'));
        } finally {
            btnVerificar.disabled = false;
            limpiarYEnfocar();
        }
    });

    async function registrarIngreso() {
        if (!ultimaVerificacion) return;
        const btn = document.getElementById('btn-ingreso');
        btn.disabled = true;
        btn.textContent = 'Registrando…';

        try {
            const res = await fetch("{{ route('verificacion.ingreso') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(ultimaVerificacion)
            });
            const data = await res.json();
            mostrar(res.ok ? banner('ok', `✓ ${data.mensaje}`) : banner('warn', data.mensaje));
        } catch (error) {
            mostrar(banner('danger', 'Error de conexión al registrar el ingreso.'));
        } finally {
            ultimaVerificacion = null;
            limpiarYEnfocar();
        }
    }
})();
</script>
@endpush