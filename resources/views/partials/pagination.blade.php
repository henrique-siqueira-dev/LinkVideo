@if ($paginator->hasPages())
    <nav class="mt-8 flex items-center justify-between gap-4" aria-label="Paginação">
        <p class="text-sm text-zinc-500">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary pointer-events-none opacity-40">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary" rel="prev">Anterior</a>
            @endif

            <span class="hidden px-3 text-sm text-zinc-400 sm:inline">
                Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary" rel="next">Próxima</a>
            @else
                <span class="btn btn-secondary pointer-events-none opacity-40">Próxima</span>
            @endif
        </div>
    </nav>
@endif
