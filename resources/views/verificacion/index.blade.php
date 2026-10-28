@extends('layouts.app')
@section('title', 'Verificación de identidad')

@section('content')
    @php
        $sugerido = $examenes->first(fn ($x) => $x->fin->isFuture());
    @endphp

    <style>
        .v-wrap { max-width: 1100px; }
        .v-exam { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 14px; align-items: end; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; margin-top: 18px; }
        .v-exam label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .v-exam select { width: 100%; height: 52px; font-size: 16px; padding: 0 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
        .v-aula { background: var(--blue-50); border: 1px solid var(--blue-100); border-radius: 12px; padding: 8px 16px; text-align: center; min-height: 52px; display: flex; flex-direction: column; justify-content: center; }
        .v-aula small { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
        .v-aula b { font-size: 20px; color: var(--blue-700); line-height: 1.1; }

        .v-tabs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 18px; }
        .v-tab { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-radius: 14px; border: 2px solid var(--line); background: #fff; cursor: pointer; text-align: left; transition: .12s; min-height: 84px; }
        .v-tab:hover { border-color: var(--blue-600); }
        .v-tab .ic { width: 46px; height: 46px; border-radius: 12px; background: var(--soft); color: var(--navy-900); display: grid; place-items: center; flex: none; }
        .v-tab .ic svg { width: 26px; height: 26px; }
        .v-tab b { display: block; font-size: 17px; color: var(--navy-900); line-height: 1.2; }
        .v-tab small { color: var(--muted); font-size: 12px; }
        .v-tab[aria-selected="true"] { border-color: var(--navy-900); background: var(--navy-900); }
        .v-tab[aria-selected="true"] b { color: #fff; }
        .v-tab[aria-selected="true"] small { color: rgba(230, 235, 247, .75); }
        .v-tab[aria-selected="true"] .ic { background: rgba(255, 255, 255, .16); color: #fff; }

        .v-panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-top: 14px; }
        .v-panel label.big { display: block; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
        .v-fila { display: flex; gap: 12px; }
        .v-fila input { flex: 1; min-width: 0; height: 68px; font-size: 28px; font-weight: 600; letter-spacing: .06em; padding: 0 18px; border: 2px solid var(--line); border-radius: 12px; background: #fff; }
        .v-fila input:focus { border-color: var(--blue-600); outline: none; }
        .v-fila input:disabled { background: var(--soft); }
        .v-btn { height: 68px; padding: 0 30px; font-size: 18px; font-weight: 700; border-radius: 12px; border: 0; cursor: pointer; background: var(--navy-900); color: #fff; display: inline-flex; align-items: center; justify-content: center; gap: 10px; }
        .v-btn:hover { background: var(--navy-800); }
        .v-btn:disabled { opacity: .5; cursor: not-allowed; }
        .v-btn.ok { background: var(--ok); }
        .v-btn.ghost { background: var(--soft); color: var(--text); }
        .v-ayuda { margin-top: 10px; color: var(--muted); font-size: 13px; }
        #lector-qr { max-width: 420px; margin-top: 14px; }

        .v-result { margin-top: 18px; }
        .v-vacio { background: #fff; border: 2px dashed var(--line); border-radius: 14px; padding: 36px 16px; text-align: center; color: var(--muted); font-size: 16px; }
        .v-banner { border-radius: 14px 14px 0 0; padding: 18px 22px; color: #fff; display: flex; align-items: center; gap: 14px; }
        .v-banner .ico { font-size: 38px; line-height: 1; }
        .v-banner b { display: block; font-size: 26px; letter-spacing: .01em; line-height: 1.1; }
        .v-banner span { font-size: 14px; opacity: .95; }
        .v-banner.ok { background: #15803d; }
        .v-banner.no { background: #c62828; }
        .v-banner.warn { background: #b45309; }
        .v-banner.alerta { background: #991b1b; animation: parpadeo 1s ease-in-out 3; }
        @keyframes parpadeo { 50% { background: #dc2626; } }
        .v-card { background: #fff; border: 1px solid var(--line); border-top: 0; border-radius: 0 0 14px 14px; padding: 20px; }
        .v-card.solo { border-top: 1px solid var(--line); border-radius: 14px; }
        .v-est { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 22px; }
        .v-est .foto-vacia.lg, .v-est img.foto.lg { width: 220px; }
        .v-datos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 22px; align-content: start; }
        .v-datos div small { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
        .v-datos div b { font-size: 18px; color: var(--navy-900); word-break: break-word; }
        .v-datos .ancho { grid-column: 1 / -1; }
        .v-datos .nombre b { font-size: 24px; }
        .v-aviso { margin-top: 16px; padding: 12px 16px; border-radius: 10px; font-size: 14px; }
        .v-aviso.warn { background: var(--warn-bg); color: var(--warn); }
        .v-aviso.err { background: var(--danger-bg); color: var(--danger); }
        .v-aviso.info { background: var(--blue-50); color: var(--blue-700); }
        .v-aviso .btn-mini { height: 38px; padding: 0 18px; margin-left: 12px; font-size: 14px; font-weight: 700; border-radius: 10px; border: 1px solid #f3c2bf; background: #fff; color: var(--danger); cursor: pointer; }
        .v-aviso .btn-mini:hover { background: var(--soft); }
        .v-acciones { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; align-items: center; }
        .v-acciones .btn-mini { height: 46px; padding: 0 16px; font-size: 14px; font-weight: 600; border-radius: 10px; border: 1px solid var(--line); background: #fff; cursor: pointer; }
        .v-acciones .btn-mini:hover { background: var(--soft); }
        .v-acciones .btn-mini.rojo { color: var(--danger); border-color: #f3c2bf; }

        .v-hist { margin-top: 22px; }
        .v-hist h3 { font-size: 15px; color: var(--navy-900); margin-bottom: 8px; }
        .v-hist ul { list-style: none; background: #fff; border: 1px solid var(--line); border-radius: 12px; }
        .v-hist li { display: flex; gap: 12px; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 14px; }
        .v-hist li:last-child { border-bottom: 0; }
        .v-hist .h { color: var(--muted); font-variant-numeric: tabular-nums; width: 76px; flex: none; }
        .v-hist .p { margin-left: auto; font-weight: 700; font-size: 12px; padding: 2px 10px; border-radius: 999px; white-space: nowrap; }
        .v-hist .p.ok { background: var(--ok-bg); color: var(--ok); }
        .v-hist .p.no { background: var(--danger-bg); color: var(--danger); }
        .v-hist .p.warn { background: var(--warn-bg); color: var(--warn); }

        @media (max-width: 800px) {
            .v-exam { grid-template-columns: 1fr; }
            .v-tabs { grid-template-columns: 1fr; }
            .v-est { grid-template-columns: 1fr; }
            .v-est .foto-vacia.lg, .v-est img.foto.lg { width: 180px; }
            .v-datos { grid-template-columns: 1fr; }
            .v-fila { flex-direction: column; }
            .v-btn { width: 100%; }
        }
    </style>

    <div class="v-wrap">
        <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Verificación de identidad</b></nav>

        <div class="head">
            <div>
                <h1>Verificación de identidad</h1>
                <p>Elige el examen, el método de verificación y valida al estudiante antes de dejarlo entrar.</p>
            </div>
        </div>

        {{-- ================= EXAMEN ================= --}}
        <section class="v-exam" aria-label="Examen">
            <div>
                <label for="v-examen">Examen en curso</label>
                <select id="v-examen">
                    <option value="">Selecciona el examen…</option>
                    @foreach ($examenes as $x)
                        <option value="{{ $x->id }}" data-aula="{{ $x->ambiente }}" @selected($sugerido && $sugerido->id === $x->id)>
                            {{ $x->nombre }} · {{ $x->carrera }} · {{ $x->fecha->format('d/m') }} {{ $x->hora }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="v-aula" aria-live="polite">
                <small>Aula / ambiente</small>
                <b id="v-aula">—</b>
            </div>
        </section>

        {{-- ================= MÉTODO (pestañas grandes) ================= --}}
        <div class="v-tabs" role="tablist" aria-label="Método de verificación">
            <button type="button" class="v-tab" role="tab" data-metodo="CODIGO_UNIV" aria-selected="true">
                <span class="ic"><svg class="i"><use href="#i-id"/></svg></span>
                <span><b>Código universitario</b><small>9 dígitos · lector o teclado</small></span>
            </button>
            <button type="button" class="v-tab" role="tab" data-metodo="CI" aria-selected="false">
                <span class="ic"><svg class="i"><use href="#i-shield"/></svg></span>
                <span><b>Cédula de identidad</b><small>Solo el número</small></span>
            </button>
            <button type="button" class="v-tab" role="tab" data-metodo="QR" aria-selected="false">
                <span class="ic"><svg class="i"><use href="#i-search"/></svg></span>
                <span><b>Código QR</b><small>Cámara o lector USB</small></span>
            </button>
        </div>

        {{-- ================= CAMPO DE VERIFICACIÓN ================= --}}
        <form class="v-panel" id="v-form" autocomplete="off">
            <label class="big" for="v-dato" id="v-etiqueta">Código universitario</label>
            <div class="v-fila">
                <input id="v-dato" type="text" inputmode="numeric" maxlength="9" placeholder="Escanea o digita el código…" disabled>
                <button class="v-btn" type="submit" id="v-verificar" disabled>
                    <svg class="i"><use href="#i-check"/></svg> Verificar
                </button>
            </div>
            <p class="v-ayuda" id="v-ayuda">Presiona Enter o Verificar. Con 9 dígitos se verifica solo.</p>

            <div id="v-camara" style="display:none;margin-top:14px">
                <button type="button" class="v-btn ghost" id="v-btn-camara" style="height:56px;font-size:16px">Activar cámara para escanear QR</button>
                <div id="lector-qr"></div>
            </div>
        </form>

        {{-- ================= RESULTADO ================= --}}
        <section class="v-result" id="v-resultado" aria-live="assertive">
            <div class="v-vacio">Selecciona un examen y verifica a un estudiante: aquí aparecerán su foto y sus datos.</div>
        </section>

        <section class="v-hist" id="v-hist" style="display:none">
            <h3>Últimas verificaciones</h3>
            <ul id="v-hist-lista"></ul>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const URL_VERIFICAR = @json(route('verificacion.verificar'));
    const URL_INGRESO = @json(route('verificacion.ingreso'));
    const URL_INCIDENCIA = @json(route('incidencias.store'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ZONA = @json(config('sciem.zona_horaria'));

    const METODOS = {
        CODIGO_UNIV: { etiqueta: 'Código universitario', placeholder: 'Escanea o digita el código…', max: 9, numerico: true,
                       ayuda: 'Presiona Enter o Verificar. Con 9 dígitos se verifica solo.', bitacora: 'CODIGO_UNIVERSITARIO' },
        CI:          { etiqueta: 'Cédula de identidad', placeholder: 'Escribe la cédula…', max: 10, numerico: true,
                       ayuda: 'Solo el número de la cédula, sin complemento. Presiona Enter o Verificar.', bitacora: 'DOCUMENTO_IDENTIDAD' },
        QR:          { etiqueta: 'Código QR', placeholder: 'El QR escaneado aparece aquí…', max: 1000, numerico: false,
                       ayuda: 'Usa un lector USB (escribe solo y envía Enter) o activa la cámara.', bitacora: 'CODIGO_QR' },
    };

    const selExamen = document.getElementById('v-examen');
    const aula = document.getElementById('v-aula');
    const form = document.getElementById('v-form');
    const dato = document.getElementById('v-dato');
    const etiqueta = document.getElementById('v-etiqueta');
    const ayuda = document.getElementById('v-ayuda');
    const btnVerificar = document.getElementById('v-verificar');
    const resultado = document.getElementById('v-resultado');
    const bloqueCamara = document.getElementById('v-camara');
    const btnCamara = document.getElementById('v-btn-camara');
    const tabs = [...document.querySelectorAll('.v-tab')];
    const hist = document.getElementById('v-hist');
    const histLista = document.getElementById('v-hist-lista');

    let metodo = 'CODIGO_UNIV';
    let ultimo = null;      // última verificación (datos del estudiante) para registrar ingreso / incidencia
    let lector = null;
    let ocupado = false;

    // ---------- utilidades ----------
    function el(tag, clase, texto) {
        const e = document.createElement(tag);
        if (clase) e.className = clase;
        if (texto !== undefined && texto !== null) e.textContent = texto;
        return e;
    }
    function hora(iso) {
        if (!iso) return '—';
        try { return new Date(iso).toLocaleTimeString('es-BO', { timeZone: ZONA, hour: '2-digit', minute: '2-digit', second: '2-digit' }); }
        catch (e) { return iso; }
    }
    async function enviar(url, cuerpo) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(cuerpo),
        });
        let json = {};
        try { json = await res.json(); } catch (e) { /* sin cuerpo */ }
        return { res, json };
    }

    // ---------- sonidos (Web Audio, sin archivos) ----------
    let ctx = null;
    function tonos(lista, tipoOnda) {
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            lista.forEach((freq, i) => {
                const osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.type = tipoOnda; osc.frequency.value = freq; gain.gain.value = 0.16;
                osc.connect(gain); gain.connect(ctx.destination);
                const t = ctx.currentTime + i * 0.15;
                osc.start(t); osc.stop(t + 0.12);
            });
        } catch (e) { /* sin audio */ }
    }
    const sonidoOk = () => tonos([880, 1320], 'sine');
    const sonidoError = () => tonos([220, 180], 'square');
    const sonidoAlerta = () => tonos([300, 200, 300, 200, 300], 'sawtooth');
    // RQ12: alarma + vibración (el personal usa celulares / tablets) cuando el examen no le corresponde al estudiante.
    const sonidoNoCorresponde = () => {
        sonidoAlerta();
        try { if (navigator.vibrate) navigator.vibrate([250, 120, 250, 120, 250]); } catch (e) { /* sin vibración */ }
    };

    // ---------- método y examen ----------
    function setMetodo(m) {
        metodo = m;
        const c = METODOS[m];
        tabs.forEach(t => t.setAttribute('aria-selected', String(t.dataset.metodo === m)));
        etiqueta.textContent = c.etiqueta;
        dato.placeholder = c.placeholder;
        dato.maxLength = c.max;
        dato.inputMode = c.numerico ? 'numeric' : 'text';
        ayuda.textContent = c.ayuda;
        dato.value = '';
        bloqueCamara.style.display = m === 'QR' ? 'block' : 'none';
        if (m !== 'QR') detenerCamara();
        dato.focus();
    }
    tabs.forEach(t => t.addEventListener('click', () => setMetodo(t.dataset.metodo)));

    function examenId() { return selExamen.value ? parseInt(selExamen.value, 10) : null; }
    function actualizarExamen() {
        const opt = selExamen.options[selExamen.selectedIndex];
        const hay = !!selExamen.value;
        aula.textContent = hay ? (opt.dataset.aula || 'Sin aula') : '—';
        dato.disabled = !hay;
        btnVerificar.disabled = !hay;
        if (hay) { dato.focus(); sessionStorage.setItem('sciem_examen', selExamen.value); }
    }
    selExamen.addEventListener('change', actualizarExamen);
    (function restaurar() {
        const guardado = sessionStorage.getItem('sciem_examen');
        if (guardado && [...selExamen.options].some(o => o.value === guardado)) selExamen.value = guardado;
        actualizarExamen();
    })();

    // ---------- cámara ----------
    function detenerCamara() {
        if (lector) { lector.stop().catch(() => {}).finally(() => { lector = null; }); }
        btnCamara.textContent = 'Activar cámara para escanear QR';
    }
    btnCamara.addEventListener('click', async () => {
        if (lector) { detenerCamara(); return; }
        if (!examenId()) { mostrarMensaje('warn', 'Selecciona un examen antes de escanear.'); return; }
        if (typeof Html5Qrcode === 'undefined') { mostrarMensaje('no', 'No se pudo cargar el lector de QR (¿sin internet?). Usa un lector USB.'); return; }
        lector = new Html5Qrcode('lector-qr');
        btnCamara.textContent = 'Detener cámara';
        let leyendo = false;
        try {
            await lector.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (texto) => {
                if (leyendo) return;
                leyendo = true;
                setTimeout(() => { leyendo = false; }, 2500);
                dato.value = texto;
                form.requestSubmit();
            });
        } catch (e) {
            mostrarMensaje('no', 'No se pudo abrir la cámara. Revisa el permiso del navegador (en celular se necesita HTTPS).');
            lector = null;
            btnCamara.textContent = 'Activar cámara para escanear QR';
        }
    });

    // ---------- entrada del dato ----------
    dato.addEventListener('input', () => {
        if (METODOS[metodo].numerico) dato.value = dato.value.replace(/\D/g, '');
        if (metodo === 'CODIGO_UNIV' && dato.value.length === 9 && !ocupado) form.requestSubmit();
    });

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (ocupado) return;
        const valor = dato.value.trim();
        if (!examenId()) { mostrarMensaje('warn', 'Selecciona el examen primero.'); return; }
        if (!valor) { mostrarMensaje('warn', 'Ingresa o escanea el dato del estudiante.'); return; }

        const cuerpo = { id_examen: examenId(), metodo_verificacion: metodo };
        if (metodo === 'CODIGO_UNIV') cuerpo.codigo_universitario = valor;
        else if (metodo === 'CI') cuerpo.documento_identidad = valor;
        else cuerpo.token_qr = valor;

        ocupado = true; btnVerificar.disabled = true;
        try {
            const { res, json } = await enviar(URL_VERIFICAR, cuerpo);
            if (!json.tipo) {
                // errores de validación u otros
                const msg = json.errors ? Object.values(json.errors)[0][0] : (json.message || 'No se pudo verificar.');
                sonidoError(); mostrarMensaje('warn', msg);
            } else {
                mostrarResultado(json);
            }
        } catch (e) {
            sonidoError(); mostrarMensaje('no', 'Error de conexión con el servidor. Intenta de nuevo.');
        } finally {
            ocupado = false; btnVerificar.disabled = false;
            dato.value = ''; dato.focus();
        }
    });

    // ---------- pintado del resultado ----------
    function mostrarMensaje(clase, texto) {
        resultado.replaceChildren();
        const b = el('div', 'v-banner ' + clase);
        b.appendChild(el('span', 'ico', clase === 'ok' ? '✓' : '!'));
        const t = el('div'); t.appendChild(el('b', null, texto));
        b.appendChild(t);
        const c = el('div', 'v-card'); c.style.display = 'none';
        resultado.append(b);
    }

    function dato2(titulo, valor, extra) {
        const d = el('div', extra || '');
        d.appendChild(el('small', null, titulo));
        d.appendChild(el('b', null, valor || '—'));
        return d;
    }

    function mostrarResultado(d) {
        const est = d.estudiante || null;
        const info = {
            habilitado:           { clase: 'ok',     icono: '✓', titulo: 'HABILITADO',       sub: 'Puede ingresar al examen', sonido: sonidoOk },
            no_habilitado:        { clase: 'no',     icono: '✕', titulo: 'NO HABILITADO',    sub: d.motivo ? 'Motivo: ' + d.motivo : d.mensaje, sonido: sonidoError },
            inactivo:             { clase: 'no',     icono: '✕', titulo: 'ESTUDIANTE NO ACTIVO', sub: d.motivo || d.mensaje, sonido: sonidoError },
            codigo_no_registrado: { clase: 'no',     icono: '?', titulo: 'NO REGISTRADO',    sub: d.mensaje, sonido: sonidoError },
            ya_ingreso:           { clase: 'warn',   icono: '!', titulo: 'EL ESTUDIANTE YA INGRESÓ',
                                    sub: 'Ingresó el ' + (d.fecha_hora_local || '—') + (d.ambiente ? ' · Aula ' + d.ambiente : ''), sonido: sonidoAlerta },
            no_corresponde:       { clase: 'alerta', icono: '⚠', titulo: 'EXAMEN NO CORRESPONDE',
                                    sub: 'Este examen no le corresponde al estudiante · intento n.º ' + (d.numero_intento || 1), sonido: sonidoNoCorresponde },
        }[d.tipo] || { clase: 'warn', icono: '!', titulo: 'RESULTADO', sub: d.mensaje, sonido: sonidoError };

        info.sonido();
        ultimo = est ? { estudiante: est, tipo: d.tipo, codigo: d.codigo || est.codigo } : null;

        resultado.replaceChildren();

        const banner = el('div', 'v-banner ' + info.clase);
        banner.appendChild(el('span', 'ico', info.icono));
        const tb = el('div');
        tb.appendChild(el('b', null, info.titulo));
        tb.appendChild(el('span', null, info.sub));
        banner.appendChild(tb);
        resultado.appendChild(banner);

        const card = el('div', 'v-card' + (est ? '' : ' solo'));
        card.style.borderTop = est ? '0' : '';

        if (!est) {
            card.appendChild(el('p', 'muted', d.mensaje || ''));
            if (d.tipo === 'codigo_no_registrado') {
                card.appendChild(el('p', 'v-ayuda', 'El intento quedó registrado. Pide su carnet y verifica por otro método.'));
            }
            resultado.appendChild(card);
            agregarHistorial(info, '—');
            return;
        }

        // Foto + datos
        const fila = el('div', 'v-est');
        if (est.foto_url) {
            const img = el('img', 'foto lg'); img.src = est.foto_url; img.alt = 'Foto del estudiante';
            fila.appendChild(img);
        } else {
            fila.appendChild(el('div', 'foto-vacia lg', 'Sin foto'));
        }
        const datos = el('div', 'v-datos');
        datos.appendChild(dato2('Estudiante', (est.nombres + ' ' + est.apellidos).trim(), 'ancho nombre'));
        datos.appendChild(dato2('Código universitario', est.codigo));
        datos.appendChild(dato2('Cédula de identidad', est.carnet));
        datos.appendChild(dato2('Carrera', est.carrera, 'ancho'));
        datos.appendChild(dato2('Estado', est.estado));
        datos.appendChild(dato2('Aula del examen', d.ambiente || aula.textContent));
        fila.appendChild(datos);
        card.appendChild(fila);

        // Avisos
        if (d.tipo === 'ya_ingreso') {
            const a = el('div', 'v-aviso warn');
            a.appendChild(el('b', null, 'Ingreso anterior: ' + (d.fecha_hora_local || '—') + (d.ambiente ? ' en el aula ' + d.ambiente : '') + '. '));
            a.appendChild(document.createTextNode('Este nuevo intento fue a las ' + hora(d.hora_intento)
                + ' y quedó registrado' + (d.incidencia_id ? ' como incidencia #' + d.incidencia_id : '')
                + '. No se puede autorizar el ingreso otra vez: pide su carnet y avisa al docente.'));
            card.appendChild(a);
        }
        if (d.tipo === 'no_corresponde') {
            // RQ12: alerta inmediata + a qué examen le corresponde entrar (todo con textContent, nada de HTML).
            const a = el('div', 'v-aviso err');
            a.appendChild(el('b', null, 'Intento n.º ' + (d.numero_intento || 1) + ' a las ' + hora(d.hora_intento) + ': quedó registrado. '));
            a.appendChild(document.createTextNode('No se puede autorizar su ingreso a este examen. Pídele su carnet e indícale su aula y horario.'));
            card.appendChild(a);

            const propios = d.examenes_asignados || [];
            const s = el('div', 'v-aviso info');
            if (propios.length) {
                s.appendChild(el('b', null, propios.length > 1 ? 'Sus exámenes: ' : 'Su examen: '));
                s.appendChild(document.createTextNode(propios
                    .map(x => x.nombre + ' · ' + x.fecha_local + ' ' + x.hora + ' · Aula ' + (x.ambiente || '—'))
                    .join('   |   ')));
            } else {
                s.appendChild(document.createTextNode('El estudiante no tiene ningún examen habilitado pendiente.'));
            }
            card.appendChild(s);
        }
        if (d.motivo_inhabilitacion) {
            card.appendChild(el('div', 'v-aviso err', 'Motivo de inhabilitación: ' + d.motivo_inhabilitacion));
        }
        if (d.tipo === 'inactivo') {
            card.appendChild(el('div', 'v-aviso err', d.mensaje + ' No se puede autorizar su ingreso.'));
        }
        if (est.foto_estado === 'OBSERVADA') {
            card.appendChild(el('div', 'v-aviso warn', 'La foto fue observada por un docente: ' + (est.foto_observacion || '') + '. Verifica su identidad con el carnet.'));
        } else if (!est.foto_url) {
            card.appendChild(el('div', 'v-aviso warn', 'El estudiante no tiene foto: verifica su identidad con el carnet.'));
        }

        // Acciones
        const acc = el('div', 'v-acciones');
        // El botón siempre se muestra; solo se activa si el estudiante está habilitado (RQ9).
        const puedeIngresar = d.tipo === 'habilitado';
        const botonIngreso = el('button', 'v-btn ok', 'Autorizar ingreso');
        botonIngreso.type = 'button';
        botonIngreso.id = 'v-registrar';
        botonIngreso.disabled = !puedeIngresar;
        if (!puedeIngresar) botonIngreso.title = 'Este estudiante no puede ingresar';
        botonIngreso.addEventListener('click', () => registrarIngreso(botonIngreso));
        acc.appendChild(botonIngreso);
        const mini = (texto, clase, fn) => { const b = el('button', 'btn-mini ' + clase, texto); b.type = 'button'; b.addEventListener('click', fn); return b; };
        acc.appendChild(mini('La foto no coincide', 'rojo', () => reportar('FOTO_NO_COINCIDE', 'La foto del sistema no coincide con la persona.')));
        acc.appendChild(mini('Sospecha de suplantación', 'rojo', () => reportar('SUPLANTACION_SOSPECHADA', 'El personal sospecha de suplantación.')));
        acc.appendChild(mini('Otra incidencia…', '', () => {
            const t = prompt('Describe la incidencia:');
            if (t && t.trim()) reportar('OTRO', t.trim());
        }));
        card.appendChild(acc);
        card.appendChild(el('p', 'v-ayuda', ''));
        resultado.appendChild(card);

        const etiquetaHist = (est.nombres + ' ' + est.apellidos).trim();
        agregarHistorial(info, etiquetaHist);
    }

    // ---------- registrar el ingreso (la fecha y hora las pone el servidor; aquí NO se envía ninguna) ----------
    let guardando = false;

    function quitarErrorIngreso() {
        const previo = document.getElementById('v-error-ingreso');
        if (previo) previo.remove();
    }

    /** Falló el guardado: muestra el motivo debajo de los botones y, si tiene sentido, el botón "Reintentar". */
    function mostrarErrorIngreso(boton, mensaje, reintentable) {
        quitarErrorIngreso();
        const caja = el('div', 'v-aviso err');
        caja.id = 'v-error-ingreso';
        caja.setAttribute('role', 'alert');
        caja.appendChild(el('b', null, 'No se registró el ingreso. '));
        caja.appendChild(document.createTextNode(mensaje));
        if (reintentable) {
            const r = el('button', 'btn-mini', 'Reintentar');
            r.type = 'button';
            r.id = 'v-reintentar';
            r.addEventListener('click', () => registrarIngreso(boton));
            caja.appendChild(r);
        }
        const acciones = boton.closest('.v-acciones');
        (acciones || resultado).after(caja);
    }

    /** Confirmación para el controlador: hora registrada por el servidor en formato dd/mm/aaaa HH:mm:ss. */
    function mostrarConfirmacionIngreso(registro, yaEstabaRegistrado) {
        const est = ultimo.estudiante;
        const nombre = (est.nombres + ' ' + est.apellidos).trim();

        resultado.replaceChildren();

        const b = el('div', 'v-banner ' + (yaEstabaRegistrado ? 'warn' : 'ok'));
        b.appendChild(el('span', 'ico', yaEstabaRegistrado ? '!' : '✓'));
        const t = el('div');
        t.appendChild(el('b', null, yaEstabaRegistrado ? 'INGRESO YA REGISTRADO' : 'INGRESO REGISTRADO'));
        t.appendChild(el('span', null, nombre));
        b.appendChild(t);
        resultado.appendChild(b);

        const card = el('div', 'v-card');
        const datos = el('div', 'v-datos');
        datos.appendChild(dato2(yaEstabaRegistrado ? 'Ingresó el' : 'Hora de ingreso registrada', registro.fecha_hora_local, 'ancho nombre'));
        if (registro.ambiente) datos.appendChild(dato2(yaEstabaRegistrado ? 'Aula del ingreso anterior' : 'Aula', registro.ambiente, 'ancho'));
        card.appendChild(datos);
        card.appendChild(el('p', 'v-ayuda', 'Hora del servidor (' + ZONA + ').'));
        resultado.appendChild(card);

        if (!yaEstabaRegistrado) agregarHistorial({ clase: 'ok', titulo: 'INGRESÓ' }, nombre);
        ultimo = null;
        dato.focus();
    }

    async function registrarIngreso(boton) {
        if (!ultimo || guardando) return;
        guardando = true;
        quitarErrorIngreso();
        const textoBoton = boton.textContent;
        boton.disabled = true;
        boton.textContent = 'Registrando…';

        const cuerpo = { id_estudiante: ultimo.estudiante.id, id_examen: examenId(), metodo: metodo };
        if (/^\d{9}$/.test(ultimo.codigo || '')) cuerpo.codigo_universitario = ultimo.codigo;

        try {
            const { res, json } = await enviar(URL_INGRESO, cuerpo);
            if (res.ok && json.registro) {
                sonidoOk();
                mostrarConfirmacionIngreso(json.registro, false);
            } else if (res.status === 409 && json.registro) {
                // Ya estaba guardado (doble clic, o un reintento cuando la respuesta anterior se perdió): se muestra su hora.
                sonidoAlerta();
                mostrarConfirmacionIngreso(json.registro, true);
            } else {
                sonidoError();
                boton.disabled = false;
                boton.textContent = textoBoton;
                // 422/409 = el servidor rechazó el ingreso (reintentar no cambia nada); 5xx, 419… = falló el guardado.
                const reintentable = !(res.status === 422 || res.status === 409);
                const msg = json.mensaje || (res.status === 419
                    ? 'La sesión expiró. Recarga la página e inicia sesión de nuevo.'
                    : 'No se pudo guardar el ingreso.');
                mostrarErrorIngreso(boton, msg, reintentable);
            }
        } catch (e) {
            sonidoError();
            boton.disabled = false;
            boton.textContent = textoBoton;
            mostrarErrorIngreso(boton, 'Error de conexión con el servidor. Revisa la red e inténtalo de nuevo.', true);
        } finally {
            guardando = false;
        }
    }

    async function reportar(tipo, detalle) {
        if (!ultimo || !examenId()) return;
        try {
            const { res, json } = await enviar(URL_INCIDENCIA, {
                examen_id: examenId(),
                estudiante_id: ultimo.estudiante.id,
                tipo: tipo,
                metodo: METODOS[metodo].bitacora,
                detalle: detalle,
            });
            alert(res.ok ? json.mensaje : (json.message || 'No se pudo registrar la incidencia.'));
        } catch (e) {
            alert('Error de conexión con el servidor.');
        }
    }

    // ---------- historial de la sesión ----------
    function agregarHistorial(info, nombre) {
        hist.style.display = 'block';
        const li = el('li');
        li.appendChild(el('span', 'h', new Date().toLocaleTimeString('es-BO', { timeZone: ZONA, hour: '2-digit', minute: '2-digit', second: '2-digit' })));
        li.appendChild(el('span', null, nombre));
        const clase = info.clase === 'ok' ? 'ok' : (info.clase === 'warn' ? 'warn' : 'no');
        li.appendChild(el('span', 'p ' + clase, info.titulo));
        histLista.prepend(li);
        while (histLista.children.length > 8) histLista.lastChild.remove();
    }

    setMetodo('CODIGO_UNIV');
})();
</script>
@endpush
