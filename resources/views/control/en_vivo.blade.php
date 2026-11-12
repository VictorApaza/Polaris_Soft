@extends('layouts.app')
@section('title', 'Control en vivo')

@section('content')
    <style>
        .ev-top { display:flex; gap:14px; flex-wrap:wrap; align-items:end; background:#fff; border:1px solid var(--line); border-radius:14px; padding:12px 16px; margin-top:12px; }
        .ev-top .field { flex:1 1 320px; margin:0; }
        .ev-actualiza { margin-left:auto; color:var(--muted); font-size:13px; }
        .ev-contadores { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-top:12px; }
        .ev-cont { background:#fff; border:1px solid var(--line); border-radius:14px; padding:12px 16px; }
        .ev-cont b { display:block; font-size:28px; line-height:1.1; color:var(--navy-900); }
        .ev-cont span { color:var(--muted); font-size:13px; }
        .ev-cont.ok b { color:#15803d; } .ev-cont.pend b { color:#b45309; } .ev-cont.exp b { color:#b91c1c; }
        .ev-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
        .ev-tab { border:2px solid var(--line); background:#fff; border-radius:12px; padding:9px 16px; font-weight:600; cursor:pointer; }
        .ev-tab.activa { border-color:var(--blue-600, #2f5fd0); background:var(--blue-50); color:var(--blue-700); }
        .ev-tab[disabled] { opacity:.55; cursor:not-allowed; }
        .ev-tab small { font-weight:500; margin-left:4px; }
        .ev-filtros { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
        .ev-filtros input, .ev-filtros select { padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:#fff; min-width:220px; }
        .ev-aviso { display:none; align-items:center; gap:12px; margin-top:12px; padding:10px 14px; border-radius:10px; background:#fef3c7; color:#92400e; font-weight:600; }
        .ev-aviso.visible { display:flex; }
        .ev-aviso .btn-mini { height:34px; padding:0 16px; font-weight:700; border-radius:10px; border:1px solid #f59e0b; background:#fff; color:#92400e; cursor:pointer; }
        .ev-tabla tr.nuevo td { animation: evNuevo 4s ease-out; }
        @keyframes evNuevo { from { background:#bbf7d0; } to { background:transparent; } }
        .ev-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; background:#fee2e2; color:#b91c1c; }
        .ev-vacio { padding:28px; text-align:center; color:var(--muted); }
        /* RQ21: pestaña "Situaciones" */
        .ev-det { max-width:380px; white-space:normal; }
        .ev-det-sub { display:block; margin-top:3px; font-size:12px; color:var(--muted); }
        .ev-btn-rev { display:block; margin-top:6px; padding:6px 12px; border-radius:9px; border:1px solid #bbf7d0; background:#fff; color:#15803d; font-weight:700; cursor:pointer; }
        .ev-btn-rev:hover { background:#dcfce7; }
        .ev-btn-rev[disabled] { opacity:.6; cursor:wait; }
    </style>

    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Control en vivo</b></nav>

    <div class="head">
        <div>
            <h1>Control en vivo</h1>
            <p>Quiénes ya ingresaron y quiénes todavía no se presentan. Se actualiza solo cada 5 segundos.</p>
        </div>
    </div>

    <div class="ev-top">
        <div class="field">
            <label for="ev-examen">Examen</label>
            <select id="ev-examen">
                @if ($examenes->isEmpty()) <option value="">No hay exámenes disponibles</option> @endif
                @foreach ($examenes as $x)
                    <option value="{{ $x->id }}" @selected($examen && $examen->id === $x->id)>{{ $x->nombre }} · {{ $x->carrera }} · {{ $x->fecha->format('d/m') }} {{ $x->hora }} · {{ $x->ambiente }}</option>
                @endforeach
            </select>
        </div>
        <div class="ev-actualiza" id="ev-actualiza" aria-live="polite">—</div>
    </div>

    <div class="ev-aviso" id="ev-aviso" role="alert">
        <span>No se pudo actualizar (sin conexión con el servidor). Se muestran los últimos datos y se reintenta solo.</span>
        <button type="button" class="btn-mini" id="ev-reintentar">Reintentar</button>
    </div>

    @if (! $examen)
        <div class="banner" style="margin-top:16px"><span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
            <div><b>No hay exámenes para controlar</b><small>Cuando haya un examen programado o abierto aparecerá aquí.</small></div></div>
    @else
        <div class="ev-contadores">
            <div class="ev-cont"><b id="c-habilitados">0</b><span>Habilitados</span></div>
            <div class="ev-cont ok"><b id="c-ingresaron">0</b><span>Ingresaron</span></div>
            <div class="ev-cont pend"><b id="c-pendientes">0</b><span>Pendientes</span></div>
            <div class="ev-cont exp"><b id="c-expulsados">0</b><span>Expulsados</span></div>
        </div>

        <div class="ev-tabs" role="tablist">
            <button type="button" class="ev-tab activa" data-tab="ingresaron" role="tab">Ingresaron</button>
            <button type="button" class="ev-tab" data-tab="pendientes" role="tab">Pendientes</button>
            <button type="button" class="ev-tab" data-tab="expulsados" role="tab">Expulsados</button>
            <button type="button" class="ev-tab" data-tab="situaciones" role="tab">Situaciones <small id="ev-sit-n"></small></button>
        </div>

        <div class="ev-filtros">
            <input type="search" id="ev-buscar" placeholder="Buscar por nombre, código o CI…" autocomplete="off">
            <select id="ev-filtro" aria-label="Filtro"><option value="">Todos los ambientes</option></select>
        </div>

        <section class="card" style="margin-top:12px">
            <div class="scroll">
                <table class="ev-tabla" id="ev-tabla">
                    <thead id="ev-thead"></thead>
                    <tbody id="ev-tbody"></tbody>
                </table>
            </div>
            <div class="foot"><span id="ev-pie"></span></div>
        </section>

        @include('control._modal_expulsion')
    @endif
@endsection

@push('scripts')
@if ($examen)
<script>
(function () {
    const EXAMEN = {{ $examen->id }};
    const URL_INGRESADOS = @json(route('ingreso.ingresados', $examen));
    const URL_PENDIENTES = @json(route('ingreso.pendientes', $examen));
    const URL_EXAMEN = @json(route('en-vivo.examen', '__ID__'));
    const CADA_MS = 5000;
    const URL_EXPULSADOS = @json(route('ingreso.expulsados', $examen));   // RQ20
    const URL_EXPULSAR = @json(route('ingreso.expulsar'));                // RQ20
    const URL_SITUACIONES = @json(route('ingreso.situaciones', $examen)); // RQ21
    const URL_REVISAR = @json(route('ingreso.situaciones.revisar', '__ID__')); // RQ21
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const $ = (id) => document.getElementById(id);
    let pestana = 'ingresaron';
    const ingresos = new Map();     // id -> fila (solo se agregan los nuevos, no se recarga todo)
    let desde = null;               // hora del último ingreso conocido
    let pendientes = [];
    let nuevos = new Set();
    let timer = null;
    let expulsados = [];            // RQ20: expulsados del examen (se pide de nuevo solo cuando cambia su cantidad)
    let situaciones = [];           // RQ21: situaciones excepcionales del examen (se piden en cada actualización)

    $('ev-examen').addEventListener('change', (e) => { if (e.target.value) location.href = URL_EXAMEN.replace('__ID__', e.target.value); });

    function pedir(url) {
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    function contadores(r) {
        $('c-habilitados').textContent = r.habilitados;
        $('c-ingresaron').textContent = r.ingresaron;
        $('c-pendientes').textContent = r.pendientes;
        $('c-expulsados').textContent = r.expulsados;
    }

    async function actualizar() {
        if (document.hidden) return;
        try {
            const url = URL_INGRESADOS + (desde ? '?desde=' + encodeURIComponent(desde) : '');
            const di = await pedir(url);
            nuevos = new Set();
            di.nuevos.forEach((f) => { if (!ingresos.has(f.id)) { ingresos.set(f.id, f); if (desde) nuevos.add(f.id); } else { ingresos.set(f.id, f); } });
            if (di.ultimo) desde = di.ultimo;
            contadores(di.resumen);
            if (di.resumen.expulsados !== expulsados.length) { expulsados = (await pedir(URL_EXPULSADOS)).expulsados; }   // RQ20
            // RQ21: si esta consulta falla (p. ej. falta correr `php artisan migrate`), solo se avisa estando en esta pestaña:
            // las demás pestañas siguen actualizándose.
            try { situaciones = (await pedir(URL_SITUACIONES)).situaciones; contarSituaciones(); }
            catch (errSit) { if (pestana === 'situaciones') throw errSit; }

            if (pestana === 'pendientes') {
                const dp = await pedir(URL_PENDIENTES);
                pendientes = dp.pendientes;
                contadores(dp.resumen);
            }
            $('ev-aviso').classList.remove('visible');
            $('ev-actualiza').textContent = 'Actualizado: ' + di.hora_servidor;
            pintar();
        } catch (e) {
            $('ev-aviso').classList.add('visible');
        }
    }

    function texto(t) { return (t || '').toString().toLowerCase(); }

    /** RQ21: en la pestaña se muestra cuántas situaciones siguen pendientes de revisar. */
    function contarSituaciones() {
        const n = situaciones.filter((s) => s.estado === 'PENDIENTE').length;
        $('ev-sit-n').textContent = n ? '(' + n + ')' : '';
    }

    function opcionesFiltro(valores, titulo) {
        const sel = $('ev-filtro');
        const actual = sel.value;
        const lista = [...new Set(valores.filter(Boolean))].sort();
        sel.innerHTML = '';
        const todos = document.createElement('option'); todos.value = ''; todos.textContent = titulo; sel.appendChild(todos);
        lista.forEach((v) => { const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
        if (lista.includes(actual)) sel.value = actual;
    }

    function celda(tr, valor, clase) { const td = document.createElement('td'); td.textContent = valor; if (clase) td.className = clase; tr.appendChild(td); return td; }

    function pintar() {
        const q = texto($('ev-buscar').value).trim();
        const filtro = $('ev-filtro').value;
        const thead = $('ev-thead'); const tbody = $('ev-tbody');
        tbody.innerHTML = '';
        let lista;

        if (pestana === 'ingresaron') {
            const todas = [...ingresos.values()].sort((a, b) => (b.fecha_hora_ingreso.localeCompare(a.fecha_hora_ingreso)) || (b.id - a.id));
            opcionesFiltro(todas.map((f) => f.ambiente), 'Todos los ambientes');
            lista = todas.filter((f) => (!filtro || f.ambiente === $('ev-filtro').value)
                && (!q || texto(f.nombre).includes(q) || texto(f.codigo).includes(q) || texto(f.ci).includes(q)));
            thead.innerHTML = '<tr><th>Hora de ingreso</th><th>Código</th><th>CI</th><th>Nombre</th><th>Ambiente</th><th>Estado</th><th></th></tr>';
            const exp = new Map(expulsados.map((x) => [x.estudiante_id, x]));   // RQ20
            lista.forEach((f) => {
                const tr = document.createElement('tr'); if (nuevos.has(f.id)) tr.className = 'nuevo';
                celda(tr, f.fecha_hora_local, 'num'); celda(tr, f.codigo, 'num'); celda(tr, f.ci, 'num'); celda(tr, f.nombre, 'strong'); celda(tr, f.ambiente);
                const td = document.createElement('td');
                const x = exp.get(f.estudiante_id);   // RQ20
                const expulsado = f.expulsado || !!x;
                if (expulsado) {
                    const b = document.createElement('span'); b.className = 'ev-badge'; b.textContent = 'EXPULSADO'; td.appendChild(b);
                    if (x) { const sub = document.createElement('span'); sub.className = 'ev-sub'; sub.textContent = x.fecha_hora_local.slice(11) + ' · ' + x.motivo; td.appendChild(sub); }
                } else { td.textContent = 'Dentro'; }
                tr.appendChild(td);
                const ta = document.createElement('td');   // RQ20: solo quien sigue dentro puede ser expulsado
                if (!expulsado) { const bt = document.createElement('button'); bt.type = 'button'; bt.className = 'ev-btn-exp'; bt.textContent = 'Expulsar'; bt.dataset.id = f.id; ta.appendChild(bt); }
                tr.appendChild(ta); tbody.appendChild(tr);
            });
            $('ev-pie').textContent = lista.length + ' de ' + ingresos.size + ' ingresos';
        } else if (pestana === 'expulsados') {
            // RQ20: estudiante, motivo, hora y usuario que registró la expulsión
            opcionesFiltro(expulsados.map((f) => f.ambiente), 'Todos los ambientes');
            lista = expulsados.filter((f) => (!filtro || f.ambiente === $('ev-filtro').value)
                && (!q || texto(f.nombre).includes(q) || texto(f.codigo).includes(q) || texto(f.ci).includes(q)));
            thead.innerHTML = '<tr><th>Hora</th><th>Código</th><th>CI</th><th>Nombre</th><th>Ambiente</th><th>Motivo</th><th>Registró</th></tr>';
            lista.forEach((f) => {
                const tr = document.createElement('tr');
                celda(tr, f.fecha_hora_local, 'num'); celda(tr, f.codigo, 'num'); celda(tr, f.ci, 'num'); celda(tr, f.nombre, 'strong'); celda(tr, f.ambiente); celda(tr, f.motivo); celda(tr, f.registrado_por);
                tbody.appendChild(tr);
            });
            $('ev-pie').textContent = lista.length + ' de ' + expulsados.length + ' expulsados';
        } else if (pestana === 'situaciones') {
            // RQ21: hora, categoría, estudiante, detalle, usuario que la registró y estado (con "Marcar revisada")
            opcionesFiltro(situaciones.map((f) => f.categoria_etiqueta), 'Todas las categorías');
            lista = situaciones.filter((f) => (!filtro || f.categoria_etiqueta === $('ev-filtro').value)
                && (!q || texto(f.nombre).includes(q) || texto(f.codigo).includes(q) || texto(f.ci).includes(q) || texto(f.detalle).includes(q)));
            thead.innerHTML = '<tr><th>Hora</th><th>Categoría</th><th>Estudiante</th><th>Detalle</th><th>Registró</th><th>Estado</th></tr>';
            lista.forEach((f) => {
                const tr = document.createElement('tr');
                celda(tr, f.fecha_hora_local, 'num'); celda(tr, f.categoria_etiqueta);

                const te = document.createElement('td');
                if (f.nombre) {
                    const n = document.createElement('span'); n.className = 'strong'; n.textContent = f.nombre; te.appendChild(n);
                    const c = document.createElement('span'); c.className = 'ev-det-sub'; c.textContent = f.codigo; te.appendChild(c);
                } else { te.textContent = '—'; }
                tr.appendChild(te);

                const td = document.createElement('td'); td.className = 'ev-det'; td.textContent = f.detalle;
                if (f.categoria === 'CAMBIO_AMBIENTE') {
                    const s = document.createElement('span'); s.className = 'ev-det-sub';
                    s.textContent = 'Ambiente: ' + (f.ambiente_origen || '—') + ' → ' + (f.ambiente_destino || '—');
                    td.appendChild(s);
                }
                tr.appendChild(td);

                celda(tr, f.registrado_por);

                const ts = document.createElement('td');
                const b = document.createElement('span');
                const revisada = f.estado === 'REVISADA';
                b.className = 'badge ' + (revisada ? 'revisada' : 'pendiente'); b.textContent = revisada ? 'REVISADA' : 'PENDIENTE';
                ts.appendChild(b);
                if (revisada) {
                    const s = document.createElement('span'); s.className = 'ev-det-sub';
                    s.textContent = (f.revisada_por || '—') + (f.revisada_at_local ? ' · ' + f.revisada_at_local : '');
                    ts.appendChild(s);
                } else {
                    const bt = document.createElement('button'); bt.type = 'button'; bt.className = 'ev-btn-rev'; bt.textContent = 'Marcar revisada'; bt.dataset.id = f.id;
                    ts.appendChild(bt);
                }
                tr.appendChild(ts); tbody.appendChild(tr);
            });
            $('ev-pie').textContent = lista.length + ' de ' + situaciones.length + ' situaciones';
        } else {
            opcionesFiltro(pendientes.map((f) => f.grupo), 'Todos los grupos');
            lista = pendientes.filter((f) => (!filtro || f.grupo === $('ev-filtro').value)
                && (!q || texto(f.nombre).includes(q) || texto(f.codigo).includes(q) || texto(f.ci).includes(q)));
            thead.innerHTML = '<tr><th>Código</th><th>CI</th><th>Nombre</th><th>Grupo</th></tr>';
            lista.forEach((f) => {
                const tr = document.createElement('tr');
                celda(tr, f.codigo, 'num'); celda(tr, f.ci, 'num'); celda(tr, f.nombre, 'strong'); celda(tr, f.grupo || '—');
                tbody.appendChild(tr);
            });
            $('ev-pie').textContent = lista.length + ' de ' + pendientes.length + ' pendientes';
        }

        if (!lista.length) {
            const tr = document.createElement('tr'); const td = document.createElement('td');
            td.colSpan = 7; td.className = 'ev-vacio';
            td.textContent = pestana === 'ingresaron' ? 'Todavía no hay ingresos que coincidan.'
                : (pestana === 'expulsados' ? 'No hay estudiantes expulsados que coincidan.'
                : (pestana === 'situaciones' ? 'No hay situaciones excepcionales que coincidan.'
                : 'No hay pendientes que coincidan: todos los habilitados ya se presentaron.'));
            tr.appendChild(td); tbody.appendChild(tr);
        }
    }

    document.querySelectorAll('.ev-tab:not([disabled])').forEach((b) => b.addEventListener('click', async () => {
        pestana = b.dataset.tab;
        document.querySelectorAll('.ev-tab').forEach((x) => x.classList.toggle('activa', x === b));
        $('ev-buscar').value = ''; $('ev-filtro').value = '';
        if (pestana === 'pendientes' || pestana === 'expulsados' || pestana === 'situaciones') { await actualizar(); } else { pintar(); }
    }));
    $('ev-buscar').addEventListener('input', pintar);
    $('ev-filtro').addEventListener('change', pintar);
    $('ev-reintentar').addEventListener('click', actualizar);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) actualizar(); });

    // ---------- RQ20: expulsar a un estudiante que ya ingresó ----------
    const dlgExp = $('modal-expulsion');
    const frmExp = $('form-expulsion');
    let aExpulsar = null;
    const motivoValido = () => $('exp-motivo').value.trim().length >= 5;

    $('ev-tbody').addEventListener('click', (e) => {
        const bt = e.target.closest('.ev-btn-exp');
        if (!bt) return;
        aExpulsar = ingresos.get(Number(bt.dataset.id)) || null;
        if (!aExpulsar) return;
        frmExp.reset();
        $('exp-quien').textContent = aExpulsar.nombre + ' · ' + aExpulsar.codigo;
        $('exp-cuenta').textContent = '0 / 500';
        $('exp-error').style.display = 'none';
        $('exp-confirmar').disabled = true;
        dlgExp.showModal();
        $('exp-motivo').focus();
    });

    $('exp-motivo').addEventListener('input', () => {
        $('exp-cuenta').textContent = $('exp-motivo').value.length + ' / 500';
        $('exp-confirmar').disabled = !motivoValido();   // el botón "Confirmar" queda desactivado hasta escribir el motivo
    });

    frmExp.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!aExpulsar || !motivoValido()) return;
        const confirmar = $('exp-confirmar');
        confirmar.disabled = true;
        $('exp-error').style.display = 'none';
        try {
            const res = await fetch(URL_EXPULSAR, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ examen_id: EXAMEN, estudiante_id: aExpulsar.estudiante_id, motivo: $('exp-motivo').value.trim() }),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok) {
                expulsados = [json.expulsion, ...expulsados.filter((x) => x.estudiante_id !== json.expulsion.estudiante_id)];
                dlgExp.close();
                aExpulsar = null;
                pintar();
                actualizar();
                return;
            }
            $('exp-error').textContent = json.errors ? Object.values(json.errors)[0][0] : (json.mensaje || json.message || 'No se pudo registrar la expulsión.');
            $('exp-error').style.display = 'block';
            if (json.tipo === 'ya_expulsado') { actualizar(); }
        } catch (err) {
            $('exp-error').textContent = 'Error de conexión con el servidor. Inténtalo de nuevo.';
            $('exp-error').style.display = 'block';
        } finally {
            confirmar.disabled = !motivoValido();
        }
    });

    // ---------- RQ21: marcar una situación excepcional como REVISADA ----------
    $('ev-tbody').addEventListener('click', async (e) => {
        const bt = e.target.closest('.ev-btn-rev');
        if (!bt) return;
        bt.disabled = true;
        bt.textContent = 'Guardando…';
        try {
            const res = await fetch(URL_REVISAR.replace('__ID__', bt.dataset.id), {
                method: 'PATCH', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.situacion) throw new Error(json.mensaje || json.message || ('HTTP ' + res.status));
            situaciones = situaciones.map((s) => (s.id === json.situacion.id ? json.situacion : s));
            contarSituaciones();
            pintar();
        } catch (err) {
            // No se pudo guardar: el botón queda para volver a intentarlo, con el motivo al pasar el mouse.
            bt.disabled = false;
            bt.textContent = 'Reintentar';
            bt.title = err.message || 'No se pudo marcar como revisada.';
        }
    });

    actualizar();
    timer = setInterval(actualizar, CADA_MS);
})();
</script>
@endif
@endpush
