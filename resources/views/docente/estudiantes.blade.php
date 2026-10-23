@extends('layouts.app')
@section('title', 'Buscar estudiantes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Buscar estudiantes</b></nav>

    <div class="head">
        <div>
            <h1>Buscar estudiantes</h1>
            <p>Encuentra a un estudiante por nombre, apellido, código universitario o cédula.</p>
        </div>
    </div>

    <form class="toolbar two" method="GET" action="{{ route('buscador.index') }}" style="margin-top:20px;padding:14px 16px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:var(--radius)">
        <label class="search">
            <svg class="i"><use href="#i-search"/></svg>
            <input type="search" name="q" value="{{ $q }}" placeholder="Ej.: Guevara, 202400123 o 7123456…" aria-label="Buscar estudiante" autofocus autocomplete="off">
        </label>
        <select name="carrera" onchange="this.form.submit()" aria-label="Carrera">
            <option value="">Todas las carreras</option>
            @foreach ($carreras as $c)
                <option value="{{ $c }}" @selected(request('carrera') === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </form>

    <section class="card" style="margin-top:16px">
        <div class="scroll">
            <table>
                <thead>
                    <tr><th>Foto</th><th>Estudiante</th><th>Cédula</th><th>Carrera</th><th>Estado</th><th>Foto</th><th style="text-align:right">Detalle</th></tr>
                </thead>
                <tbody>
                @forelse ($estudiantes as $e)
                    <tr>
                        <td>@if ($e->tiene_foto)<img class="foto sm" src="{{ $e->foto_url }}" alt="" loading="lazy">@else<div class="foto-vacia sm">—</div>@endif</td>
                        <td class="strong">{{ $e->apellidos }}, {{ $e->nombres }}<span class="sub">{{ $e->codigo_universitario }}</span></td>
                        <td class="num">{{ $e->carnet_completo }}</td>
                        <td>{{ $e->carrera ?: '—' }}</td>
                        <td><span class="badge {{ strtolower($e->estado) }}">{{ ucfirst(strtolower($e->estado)) }}</span></td>
                        <td><span class="badge {{ strtolower($e->foto_estado ?: 'sin_foto') }}">{{ $e->foto_estado_etiqueta }}</span></td>
                        <td style="text-align:right"><a class="link-btn ok" href="{{ route('buscador.show', $e->id_estudiante) }}">Ver detalle</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No se encontró ningún estudiante con esos datos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $estudiantes->total() }} estudiante(s)</span><span>{{ $estudiantes->links() }}</span></div>
    </section>
@endsection
