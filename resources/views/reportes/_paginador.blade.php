{{-- Pie de tabla con el total y paginación sencilla (usa las clases .foot y .pager del estándar visual). --}}
<div class="foot">
    <span>{{ number_format($filas->total(), 0, ',', '.') }} {{ $unidad ?? 'registro(s)' }}</span>
    @if ($filas->lastPage() > 1)
        <span class="pager">
            @if ($filas->onFirstPage())
                <span class="disabled">‹</span>
            @else
                <a href="{{ $filas->previousPageUrl() }}" aria-label="Página anterior">‹</a>
            @endif
            <span class="current">{{ $filas->currentPage() }}</span>
            <span>de {{ $filas->lastPage() }}</span>
            @if ($filas->hasMorePages())
                <a href="{{ $filas->nextPageUrl() }}" aria-label="Página siguiente">›</a>
            @else
                <span class="disabled">›</span>
            @endif
        </span>
    @endif
</div>
