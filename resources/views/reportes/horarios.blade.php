@extends('layouts.app')
@section('title', 'Horarios de ingreso')

@section('content')
    <style>
        .franja-barra { min-width:180px; }
        .franja-barra .bar { margin-top:0; height:8px; }
        .franja-filtro { display:flex; gap:12px; align-items:end; flex-wrap:wrap; padding:14px 16px; margin-top:18px; }
        .franja-filtro label { display:grid; gap:5px; color:var(--muted); font-size:12px; }
        .franja-filtro select { min-height:40px; padding:8px 12px; border:1px solid var(--line); border-radius:9px; background:#fff; }
        tr.fila-actual td { background:var(--blue-50); }
        .stat strong.texto { font-size:20px; line-height:1.2; }
    </style>

    @include('reportes._encabezado', [
        'titulo' => 'Horarios de ingreso y estudiantes registrados',
        'descripcion' => 'A qué hora llegan los estudiantes y cuántos se registran: sirve para planificar futuros exámenes.',
        'ruta' => 'reportes.horarios',
    ])

    <section class="stats cinco">
        <div class="stat"><div><small>Habilitados</small><strong>{{ $resumen['habilitados'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat ok"><div><small>Registrados</small><strong>{{ $resumen['registrados'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Antes del inicio</small><strong>{{ $resumen['antes_del_inicio'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span></div>
        <div class="stat"><div><small>Después del inicio</small><strong>{{ $resumen['despues_del_inicio'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-alert"/></svg></span></div>
        <div class="stat"><div><small>Hora pico</small><strong class="texto">{{ $resumen['hora_pico'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-dashboard"/></svg></span></div>
    </section>

    <div class="aviso info" role="note">
        El examen empieza a las {{ $resumen['inicio_examen'] }}. Primer ingreso: {{ $resumen['primer_ingreso'] }} · último ingreso: {{ $resumen['ultimo_ingreso'] }} ·
        llegada promedio: {{ $resumen['llegada_promedio'] }}.
    </div>

    <form class="card franja-filtro no-print" method="GET" action="{{ route('reportes.horarios', $examen) }}">
        <label>Agrupar los ingresos cada
            <select name="franja" onchange="this.form.submit()">
                @foreach (\App\Services\ReporteExamenService::FRANJAS_VALIDAS as $m)
                    <option value="{{ $m }}" @selected($datos['franja_min'] === $m)>{{ $m }} minutos</option>
                @endforeach
            </select>
        </label>
        <noscript><button class="btn" type="submit">Aplicar</button></noscript>
    </form>

    <section class="seccion">
        <h2>Ingresos por franja horaria <span class="n">{{ $resumen['registrados'] }}</span></h2>
        <div class="card">
            @php $maxFranja = max(1, collect($datos['franjas'])->max('cantidad')); @endphp
            <div class="scroll">
                <table>
                    <thead><tr><th>Franja</th><th>Respecto al inicio</th><th>Distribución</th><th class="t-right">Ingresaron</th><th class="t-right">Acumulado</th><th class="t-right">% acumulado</th></tr></thead>
                    <tbody>
                    @forelse ($datos['franjas'] as $f)
                        <tr>
                            <td class="num strong">{{ $f['franja'] }}</td>
                            <td class="muted">{{ $f['minutos_inicio'] < 0 ? abs($f['minutos_inicio']).' min antes' : ($f['minutos_inicio'] === 0 ? 'al inicio' : $f['minutos_inicio'].' min después') }}</td>
                            <td class="franja-barra"><div class="bar"><i class="g" style="width:{{ round($f['cantidad'] / $maxFranja * 100) }}%"></i></div></td>
                            <td class="num t-right strong">{{ $f['cantidad'] }}</td>
                            <td class="num t-right">{{ $f['acumulado'] }}</td>
                            <td class="num t-right">{{ number_format($f['porcentaje_acumulado'], 1, ',', '') }} %</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Todavía nadie registró ingreso en este examen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="seccion">
        <h2>Ingresos por aula <span class="n">{{ count($datos['por_ambiente']) }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Aula</th><th class="t-right">Estudiantes</th><th class="t-right">%</th></tr></thead>
                    <tbody>
                    @forelse ($datos['por_ambiente'] as $a)
                        <tr>
                            <td class="strong">{{ $a['ambiente'] }}</td>
                            <td class="num t-right">{{ $a['cantidad'] }}</td>
                            <td class="num t-right">{{ number_format($a['porcentaje'], 1, ',', '') }} %</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Sin ingresos registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="seccion">
        <h2>Comparativo de estudiantes registrados por examen <span class="n">{{ count($datos['comparativo']) }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Examen</th><th>Fecha y hora</th><th>Aula</th><th class="t-right">Habilitados</th><th class="t-right">Registrados</th><th class="t-right">% asistencia</th><th>Primer ingreso</th><th>Último ingreso</th></tr></thead>
                    <tbody>
                    @foreach ($datos['comparativo'] as $c)
                        <tr class="{{ $c['actual'] ? 'fila-actual' : '' }}">
                            <td class="strong">{{ $c['examen'] }} @if ($c['actual'])<span class="badge programado">Este examen</span>@endif</td>
                            <td class="num">{{ $c['fecha'] }}</td>
                            <td>{{ $c['aula'] }}</td>
                            <td class="num t-right">{{ $c['habilitados'] }}</td>
                            <td class="num t-right strong">{{ $c['registrados'] }}</td>
                            <td class="num t-right">{{ number_format($c['porcentaje'], 1, ',', '') }} %</td>
                            <td class="num">{{ $c['primer_ingreso'] }}</td>
                            <td class="num">{{ $c['ultimo_ingreso'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
