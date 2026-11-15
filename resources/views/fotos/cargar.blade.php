@extends('layouts.app')
@section('title', 'Cargar fotos')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Cargar fotos de estudiantes</b></nav>

    <div class="head">
        <div>
            <h1>Cargar fotos de estudiantes</h1>
            <p>Solo JPG o PNG, de máximo {{ config('sciem.foto_max_kb') / 1024 }} MB cada una y mínimo 200 x 200 píxeles.</p>
        </div>
    </div>

    @include('partials.flash')

    @if ($errors->any())
        <div class="flash err" role="alert" style="display:block">
            <ul style="margin-left:18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('resultado_zip'))
        @php $r = session('resultado_zip'); @endphp
        <div class="flash" role="status" style="display:block">
            <b>ZIP procesado: {{ $r['cargadas'] }} foto(s) cargada(s) y {{ count($r['no_asociadas']) }} archivo(s) no asociado(s).</b>
            @if (count($r['no_asociadas']))
                <ul style="margin:8px 0 0 18px">
                    @foreach ($r['no_asociadas'] as $linea)<li>{{ $linea }}</li>@endforeach
                </ul>
            @endif
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;align-items:start">
        <section class="card" style="padding:20px">
            <h2 style="font-size:16px;margin-bottom:12px">Una foto (individual)</h2>
            <form method="POST" action="{{ route('fotos.individual') }}" enctype="multipart/form-data" style="display:grid;gap:12px">
                @csrf
                <div class="field">
                    <label for="f-codigo">Código universitario <span class="req">*</span></label>
                    <input id="f-codigo" name="codigo_universitario" value="{{ old('codigo_universitario') }}" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" placeholder="202400034" required>
                </div>
                <div class="field">
                    <label for="f-foto">Foto (JPG o PNG) <span class="req">*</span></label>
                    <input id="f-foto" type="file" name="foto" accept="image/jpeg,image/png" required>
                </div>
                <div><button class="btn" type="submit">Cargar foto</button></div>
            </form>
        </section>

        <section class="card" style="padding:20px">
            <h2 style="font-size:16px;margin-bottom:6px">Muchas fotos (ZIP)</h2>
            <p class="muted" style="margin-bottom:12px">Cada archivo debe llamarse como el <b>código universitario</b> del estudiante, por ejemplo <code>202400034.jpg</code>. Al terminar verás cuáles archivos no se pudieron asociar y por qué.</p>
            <form method="POST" action="{{ route('fotos.zip') }}" enctype="multipart/form-data" style="display:grid;gap:12px">
                @csrf
                <div class="field">
                    <label for="f-zip">Archivo ZIP <span class="req">*</span></label>
                    <input id="f-zip" type="file" name="archivo_zip" accept=".zip,application/zip" required>
                </div>
                <div><button class="btn" type="submit">Cargar ZIP</button></div>
            </form>
        </section>
    </div>
@endsection
