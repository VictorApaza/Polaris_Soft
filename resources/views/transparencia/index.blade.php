@extends('layouts.app')
@section('title', 'Transparencia de ingreso')

@section('content')
    <style>
        .toolbar.cuatro { grid-template-columns: minmax(200px, 1.2fr) minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr); }
        .toolbar.cuatro select { min-width: 0; text-overflow: ellipsis; }
        @media (max-width: 900px) { .toolbar.cuatro { grid-template-columns: 1fr; } }
        td.examen { min-width: 230px; }
        @keyframes resaltar-nuevo { from { background: #fff4c2; } to { background: transparent; } }
        tr.nuevo td { animation: resaltar-nuevo 4s ease-out; }
        .en-vivo { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }
        .en-vivo .punto { width: 9px; height: 9px; border-radius: 50%; background: #16a34a; }
        .en-vivo.pausado .punto { background: #94a3b8; }
        .en-vivo.error .punto { background: #dc2626; }
    </style>

    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Transparencia de ingreso</b></nav>

    <div class="head">
        <div style="flex:1 1 360px;min-width:0">
            <h1>Transparencia de ingreso</h1>
            <p>Auditoría de las autorizaciones de ingreso emitidas. Son registros de solo lectura: no se pueden modificar ni eliminar.</p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex:none">
            <span class="en-vivo" id="estado-vivo" role="status" aria-live="polite"><span class="punto"></span> <span id="estado-texto">En vivo</span></span>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" id="auto" checked> Actualizar automáticamente (cada 5 s)
            </label>
        </div>
    </div>

    {{-- Resumen --}}
    <section class="stats three">
        <div class="stat">
            <div><small>Autorizaciones registradas</small><strong id="s-total">0</strong></div>
            <span class="ic"><svg class="i"><use href="#i-shield"/></svg></span>
        </div>
        <div class="stat ok">
            <div><small>Última autorización</small><strong id="s-ultima" style="font-size:18px;margin-top:12px">—</strong></div>
            <span class="ic"><svg class="i"><use href="#i-check"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Última actualización</small><strong id="s-actualizado" style="font-size:18px;margin-top:12px">—</strong></div>
            <span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span>
        </div>
    </section>

    {{-- Filtros --}}
    <form class="toolbar cuatro" id="form-filtros" method="GET" action="{{ route('transparencia.index') }}">
        <label class="search">
            <svg class="i"><use href="#i-search"/></svg>
            <input type="search" name="estudiante" value="{{ $filtros['estudiante'] }}"
                   placeholder="Buscar por código o nombre del estudiante…" aria-label="Buscar estudiante" autocomplete="off">
        </label>
        <select name="examen_id" aria-label="Examen">
            <option value="">Examen (todos)</option>
            @foreach ($examenes as $ex)
                <option value="{{ $ex->examen_id }}" @selected($filtros['examen_id'] === (int) $ex->examen_id)>{{ $ex->examen_descripcion }}</option>
            @endforeach
        </select>
        <select name="ambiente" aria-label="Ambiente (aula)">
            <option value="">Ambiente (todos)</option>
            @foreach ($ambientes as $amb)
                <option value="{{ $amb }}" @selected($filtros['ambiente'] === $amb)>{{ $amb }}</option>
            @endforeach
        </select>
        <select name="metodo" aria-label="Método de verificación">
            <option value="">Método (todos)</option>
            @foreach ($metodos as $clave => $etiqueta)
                <option value="{{ $clave }}" @selected($filtros['metodo'] === $clave)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </form>

    {{-- Tabla --}}
    <section class="card" style="margin-top:16px">
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Fecha y hora (servidor)</th><th>Estudiante</th><th>Examen</th>
                        <th>Ambiente</th><th>Controlador</th><th>Método</th><th>IP de origen</th>
                    </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
        <div class="foot">
            <p id="pie">—</p>
            <p>Los registros más recientes aparecen primero.</p>
        </div>
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const URL_DATOS = @json(route('transparencia.datos'));
    const URL_PAGINA = @json(route('transparencia.index'));
    const INICIAL = @json($inicial);

    const form = document.getElementById('form-filtros');
    const tbody = document.getElementById('tbody');
    const auto = document.getElementById('auto');
    const indicador = document.getElementById('estado-vivo');
    const estadoTexto = document.getElementById('estado-texto');

    let vistos = new Set(INICIAL.registros.map(r => r.id));
    let ocupado = false;
    let pendiente = false;      // hubo un pedido de actualización mientras se consultaba
    let temporizadorBusqueda = null;

    function celda(texto, clase) {
        const td = document.createElement('td');
        if (clase) td.className = clase;
        td.textContent = (texto === null || texto === undefined || texto === '') ? '—' : texto;
        return td;
    }

    function fila(r, esNueva) {
        const tr = document.createElement('tr');
        if (esNueva) tr.className = 'nuevo';

        tr.appendChild(celda(r.fecha_hora, 'strong num'));

        const tdEst = celda(r.estudiante_nombre, 'strong');
        const sub = document.createElement('span');
        sub.className = 'sub';
        sub.textContent = r.estudiante_codigo;
        tdEst.appendChild(sub);
        tr.appendChild(tdEst);

        tr.appendChild(celda(r.examen, 'examen'));
        tr.appendChild(celda(r.ambiente));
        tr.appendChild(celda(r.controlador));

        const tdMetodo = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'badge programado';
        badge.textContent = r.metodo_etiqueta;
        tdMetodo.appendChild(badge);
        tr.appendChild(tdMetodo);

        const tdIp = celda(r.ip, 'num muted');
        if (r.user_agent) tdIp.title = r.user_agent;
        tr.appendChild(tdIp);

        return tr;
    }

    function pintar(datos, resaltarNuevos) {
        document.getElementById('s-total').textContent = new Intl.NumberFormat('es').format(datos.total);
        document.getElementById('s-ultima').textContent = datos.registros.length ? datos.registros[0].fecha_hora : '—';
        document.getElementById('s-actualizado').textContent = datos.generado_en;
        document.getElementById('pie').textContent =
            'Mostrando ' + datos.mostrados + ' de ' + new Intl.NumberFormat('es').format(datos.total) + ' registros';

        tbody.textContent = '';

        if (!datos.registros.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.className = 'empty';
            td.textContent = 'No hay autorizaciones registradas con esos filtros.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }

        datos.registros.forEach(r => {
            tbody.appendChild(fila(r, resaltarNuevos && !vistos.has(r.id)));
        });

        vistos = new Set(datos.registros.map(r => r.id));
    }

    function parametros() {
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach(k => { if (!params.get(k)) params.delete(k); });
        return params;
    }

    function estado(clase, texto) {
        indicador.className = 'en-vivo' + (clase ? ' ' + clase : '');
        estadoTexto.textContent = texto;
    }

    async function refrescar(resaltar = true) {
        if (ocupado) { pendiente = true; return; }
        ocupado = true;
        const params = parametros();

        try {
            const respuesta = await fetch(URL_DATOS + (params.toString() ? '?' + params.toString() : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);

            pintar(await respuesta.json(), resaltar);
            estado(auto.checked ? '' : 'pausado', auto.checked ? 'En vivo' : 'Pausado');
        } catch (e) {
            estado('error', 'Sin conexión con el servidor; reintentando…');
        } finally {
            ocupado = false;
            if (pendiente) { pendiente = false; refrescar(false); }
        }
    }

    // Filtros: se aplican sin recargar la página y quedan reflejados en la URL.
    function aplicarFiltros() {
        const params = parametros();
        history.replaceState(null, '', URL_PAGINA + (params.toString() ? '?' + params.toString() : ''));
        refrescar(false);     // al cambiar de filtro no se resalta todo como "nuevo"
    }

    form.addEventListener('submit', e => { e.preventDefault(); aplicarFiltros(); });
    form.querySelectorAll('select').forEach(s => s.addEventListener('change', aplicarFiltros));
    form.querySelector('input[type="search"]').addEventListener('input', () => {
        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(aplicarFiltros, 400);
    });

    auto.addEventListener('change', () => {
        estado(auto.checked ? '' : 'pausado', auto.checked ? 'En vivo' : 'Pausado');
        if (auto.checked) refrescar();
    });

    // Actualización automática cada 5 s (se detiene si la pestaña está oculta).
    setInterval(() => { if (auto.checked && !document.hidden) refrescar(); }, 5000);

    pintar(INICIAL, false);
});
</script>
@endpush
