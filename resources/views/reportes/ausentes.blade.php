@extends('layouts.app')
@section('title', 'Estudiantes ausentes')

@section('content')
    @include('reportes._encabezado', [
        'titulo' => 'Estudiantes ausentes',
        'descripcion' => 'Habilitados para este examen que todavía no tienen un ingreso registrado.',
        'ruta' => 'reportes.ausentes',
    ])

    <section class="stats">
        <div class="stat"><div><small>Habilitados</small><strong>{{ $resumen['habilitados'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat ok"><div><small>Ingresaron (habilitados)</small><strong>{{ $resumen['presentes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Ausentes</small><strong style="{{ $resumen['ausentes'] ? 'color:var(--danger)' : '' }}">{{ $resumen['ausentes'] }}</strong></div><span class="ic" style="{{ $resumen['ausentes'] ? 'background:var(--danger-bg);color:var(--danger)' : '' }}"><svg class="i"><use href="#i-calendar"/></svg></span></div>
        <div class="stat"><div><small>% de ausentismo</small><strong>{{ number_format($resumen['porcentaje'], 1, ',', '') }} %</strong></div><span class="ic"><svg class="i"><use href="#i-dashboard"/></svg></span></div>
    </section>

    <div class="aviso info" role="note">
        Ausente = habilitado sin ingreso registrado. Los {{ $resumen['inhabilitados'] }} estudiante(s) inhabilitados no cuentan como ausentes.
        Habilitados {{ $resumen['habilitados'] }} − ingresaron {{ $resumen['presentes'] }} = {{ $resumen['ausentes'] }} ausente(s).
    </div>

    <section class="card" style="margin-top:18px">
        <div class="scroll">
            <table>
                <thead>
                    <tr><th class="t-right">N°</th><th>Estudiante</th><th>Cédula</th><th>Carrera</th><th>Correo institucional</th><th>Foto</th></tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    <tr>
                        <td class="num t-right">{{ $f['n'] }}</td>
                        <td class="strong">{{ $f['nombre'] }}<span class="sub">{{ $f['codigo'] }}</span></td>
                        <td class="num">{{ $f['ci'] }}</td>
                        <td>{{ $f['carrera'] }}</td>
                        <td class="mail">{{ $f['correo'] }}</td>
                        <td>{{ $f['foto'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Todos los habilitados ingresaron (o todavía no hay habilitados).</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('reportes._paginador', ['unidad' => 'ausente(s)'])
    </section>
@endsection
