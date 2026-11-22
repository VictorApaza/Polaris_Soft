@extends('layouts.app')
@section('title', 'Intentos a un examen que no corresponde')

@section('content')
    @include('reportes._encabezado', [
        'titulo' => 'Intentos a un examen que no corresponde',
        'descripcion' => 'Estudiantes que quisieron entrar a este examen sin estar inscritos en su materia (registrados al verificar el ingreso).',
        'ruta' => 'reportes.intentos',
    ])

    <section class="stats three">
        <div class="stat"><div><small>Intentos registrados</small><strong style="{{ $resumen['intentos'] ? 'color:var(--danger)' : '' }}">{{ $resumen['intentos'] }}</strong></div><span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span></div>
        <div class="stat"><div><small>Estudiantes distintos</small><strong>{{ $resumen['estudiantes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat"><div><small>Reincidentes (2 o más intentos)</small><strong>{{ $resumen['reincidentes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-shield"/></svg></span></div>
    </section>

    <div class="aviso info" role="note">
        Son los mismos registros que guarda el control de ingreso con el motivo «examen no corresponde» (RQ12):
        <a href="{{ route('intentos.examen', ['examen' => $examen, 'motivo' => \App\Services\ExamenNoCorrespondeService::MOTIVO]) }}" target="_blank" rel="noopener" style="text-decoration:underline">ver la consulta original (JSON)</a>.
    </div>

    <section class="card" style="margin-top:18px">
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th class="t-right">N°</th><th>Fecha y hora</th><th>Estudiante</th><th>Cédula</th>
                        <th>Método</th><th>Aula</th><th>Controlador</th><th>Intento</th><th>Examen que sí le corresponde</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    <tr class="{{ $f['intento'] >= 2 ? 'alerta-fila' : '' }}">
                        <td class="num t-right">{{ $f['n'] }}</td>
                        <td class="num strong">{{ $f['fecha'] }} {{ $f['hora'] }}</td>
                        <td class="strong">{{ $f['nombre'] }}<span class="sub">{{ $f['codigo'] }}</span></td>
                        <td class="num">{{ $f['ci'] }}</td>
                        <td>{{ $f['metodo'] }}<span class="sub">{{ $f['dato'] }}</span></td>
                        <td>{{ $f['ambiente'] }}</td>
                        <td>{{ $f['controlador'] }}</td>
                        <td><span class="badge {{ $f['intento'] >= 2 ? 'inhabilitado' : 'pendiente' }}">n.º {{ $f['intento'] }}</span></td>
                        <td style="max-width:280px;white-space:normal">{{ $f['le_corresponde'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Nadie intentó ingresar a este examen sin corresponderle.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('reportes._paginador', ['unidad' => 'intento(s)'])
    </section>
@endsection
