@extends('layouts.app')
@section('title', 'Respaldos')

@section('content')
    <style>
        .resp-tipo { font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; background:var(--soft); color:var(--off); white-space:nowrap; }
        .resp-tipo.automatico { background:var(--blue-100); color:var(--blue-700); }
        .resp-tipo.previo { background:var(--warn-bg); color:var(--warn); }
        .resp-verif { margin-top:18px; padding:14px 16px; }
        .resp-verif ul { margin:6px 0 0 18px; }
        .resp-confirma { display:grid; gap:10px; }
        .resp-confirma input { text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
    </style>

    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Respaldos</b></nav>

    <div class="head">
        <div>
            <h1>Respaldos de la información</h1>
            <p>Copias de seguridad de todo lo registrado: estudiantes, exámenes, habilitaciones, ingresos y fotos.</p>
        </div>
        <form method="POST" action="{{ route('respaldos.store') }}" class="head-actions">
            @csrf
            <button class="btn" type="submit"><svg class="i sm"><use href="#i-database"/></svg> Crear respaldo ahora</button>
        </form>
    </div>

    @include('partials.flash')
    @if ($errors->any())
        <div class="flash err" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (session('verificacion'))
        @php $v = session('verificacion'); @endphp
        <div class="card resp-verif" role="status" style="border-color:{{ $v['ok'] ? 'var(--ok)' : 'var(--danger)' }}">
            <b style="color:{{ $v['ok'] ? 'var(--ok)' : 'var(--danger)' }}">{{ $v['ok'] ? 'Respaldo íntegro' : 'El respaldo tiene problemas' }}: {{ $v['nombre'] }}.zip</b>
            <ul>@foreach ($v['mensajes'] as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="stats three">
        <div class="stat"><div><small>Respaldos guardados</small><strong>{{ count($respaldos) }}</strong></div><span class="ic"><svg class="i"><use href="#i-database"/></svg></span></div>
        <div class="stat ok"><div><small>Último respaldo</small><strong style="font-size:20px;line-height:1.3">{{ $ultimo && $ultimo['creado_en'] ? $ultimo['creado_en']->format('d/m/Y H:i') : 'Ninguno' }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Espacio usado</small><strong>{{ number_format($bytes / 1048576, 1, ',', '.') }} <span style="font-size:14px">MB</span></strong></div><span class="ic"><svg class="i"><use href="#i-building"/></svg></span></div>
    </section>

    <div class="banner">
        <span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span>
        <div>
            <b>Respaldo automático todos los días a las {{ $hora }}</b>
            <small>
                Incluye la base de datos{{ $conArchivos ? ' y las fotos' : '' }}. Los automáticos se conservan {{ $dias > 0 ? $dias.' días (siempre quedan los 3 últimos)' : 'sin límite' }};
                los manuales no se borran solos. Requiere el cron de Laravel en el servidor (<code>php artisan schedule:run</code>).
            </small>
        </div>
    </div>

    <section class="card" style="margin-top:18px">
        <div class="scroll">
            <table>
                <thead>
                    <tr><th>Respaldo</th><th>Tipo</th><th>Creado por</th><th class="t-right">Tablas</th><th class="t-right">Filas</th><th class="t-right">Fotos y archivos</th><th class="t-right">Tamaño</th><th class="t-right">Acciones</th></tr>
                </thead>
                <tbody>
                @forelse ($respaldos as $r)
                    <tr>
                        <td class="strong">{{ $r['creado_en'] ? $r['creado_en']->format('d/m/Y H:i:s') : '—' }}<span class="sub">{{ $r['archivo'] }}</span></td>
                        <td><span class="resp-tipo {{ $r['tipo'] }}">{{ $r['tipo_etiqueta'] }}</span></td>
                        <td>{{ $r['creado_por'] ?? ($r['tipo'] === 'automatico' ? 'Sistema' : '—') }}</td>
                        <td class="num t-right">{{ $r['tablas'] }}</td>
                        <td class="num t-right">{{ number_format($r['filas'], 0, ',', '.') }}</td>
                        <td class="num t-right">{{ $r['archivos'] }}</td>
                        <td class="num t-right">{{ number_format($r['bytes'] / 1048576, 2, ',', '.') }} MB</td>
                        <td>
                            <div class="fila-acciones">
                                <a class="btn-mini" href="{{ route('respaldos.descargar', $r['nombre']) }}"><svg class="i sm"><use href="#i-download"/></svg> Descargar</a>
                                <form method="POST" action="{{ route('respaldos.verificar', $r['nombre']) }}">
                                    @csrf
                                    <button class="btn-mini" type="submit"><svg class="i sm"><use href="#i-check"/></svg> Verificar</button>
                                </form>
                                @if ($r['legible'])
                                    <button class="btn-mini" type="button" onclick="abrirRestaurar('{{ $r['nombre'] }}', '{{ $r['creado_en'] ? $r['creado_en']->format('d/m/Y H:i') : '' }}')"><svg class="i sm"><use href="#i-refresh"/></svg> Restaurar</button>
                                @endif
                                <form method="POST" action="{{ route('respaldos.destroy', $r['nombre']) }}">
                                    @csrf @method('DELETE')
                                    <button class="link-btn danger" type="button" onclick="confirmarEliminar(this.closest('form'), '¿Eliminar este respaldo? No se puede deshacer.')">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Todavía no hay respaldos. Crea el primero con «Crear respaldo ahora».</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ count($respaldos) }} respaldo(s)</span><span>Guardados en storage/app/backups (no son públicos)</span></div>
    </section>

    {{-- Modal de restauración: reemplaza toda la información, así que pide escribir RESTAURAR --}}
    <dialog id="modal-restaurar">
        <form method="POST" id="form-restaurar">
            @csrf
            <div class="m-head">
                <span class="ic" style="background:var(--warn-bg);color:var(--warn)"><svg class="i"><use href="#i-alert"/></svg></span>
                <div><b>Restaurar un respaldo</b><small id="restaurar-fecha"></small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="aviso warn" style="margin:0">
                    Esto <b>reemplaza toda la información actual</b> (estudiantes, exámenes, ingresos, usuarios, fotos…) por la del respaldo.
                    Antes se guarda una copia del estado actual para poder volver atrás. Nadie debería estar usando el sistema mientras se restaura.
                </div>
                <div class="field resp-confirma">
                    <label for="restaurar-confirmacion">Escribe <b>RESTAURAR</b> para confirmar</label>
                    <input id="restaurar-confirmacion" name="confirmacion" type="text" autocomplete="off" required>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn danger" id="restaurar-ok" disabled>Restaurar</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const rutaRespaldos = @json(url('respaldos'));
    function abrirRestaurar(nombre, fecha) {
        const d = document.getElementById('modal-restaurar');
        document.getElementById('form-restaurar').action = rutaRespaldos + '/' + encodeURIComponent(nombre) + '/restaurar';
        document.getElementById('restaurar-fecha').textContent = 'Respaldo del ' + fecha;
        const campo = document.getElementById('restaurar-confirmacion');
        campo.value = '';
        document.getElementById('restaurar-ok').disabled = true;
        d.showModal();
        campo.focus();
    }
    document.getElementById('restaurar-confirmacion').addEventListener('input', function () {
        this.value = this.value.toUpperCase();
        document.getElementById('restaurar-ok').disabled = this.value.trim() !== 'RESTAURAR';
    });
</script>
@endpush
