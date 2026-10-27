{{-- Paginación con el estilo de la clínica. Uso: {{ $coleccion->links('vendor.pagination.clinica') }} --}}
@if ($paginator->hasPages())
    <nav class="mt-5 flex items-center justify-between gap-3" aria-label="Paginación">
        <p class="text-sm text-on-surface">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="flex size-9 items-center justify-center rounded-radius text-on-surface/40" aria-disabled="true"><x-icono nombre="chevron-izquierda" class="size-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex size-9 items-center justify-center rounded-radius bg-surface ring-1 ring-outline hover:bg-surface-alt" aria-label="Página anterior"><x-icono nombre="chevron-izquierda" class="size-4" /></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="px-2 text-on-surface">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $pagina => $url)
                        <li>
                            @if ($pagina == $paginator->currentPage())
                                <span aria-current="page" class="flex size-9 items-center justify-center rounded-radius bg-primary text-sm font-bold text-on-primary">{{ $pagina }}</span>
                            @else
                                <a href="{{ $url }}" class="flex size-9 items-center justify-center rounded-radius bg-surface text-sm ring-1 ring-outline hover:bg-surface-alt">{{ $pagina }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex size-9 items-center justify-center rounded-radius bg-surface ring-1 ring-outline hover:bg-surface-alt" aria-label="Página siguiente"><x-icono nombre="chevron-derecha" class="size-4" /></a>
                @else
                    <span class="flex size-9 items-center justify-center rounded-radius text-on-surface/40" aria-disabled="true"><x-icono nombre="chevron-derecha" class="size-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
