@if ($paginator->total() > 0)
    <div class="pager">
        <span>Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $noun }}</span>

        @if ($paginator->hasPages())
            <div class="pager-buttons">
                <a href="{{ $paginator->previousPageUrl() ?? '#' }}"
                   class="btn btn-light btn-sm {{ $paginator->onFirstPage() ? 'is-disabled' : '' }}">‹ Sebelumnya</a>
                <a href="{{ $paginator->nextPageUrl() ?? '#' }}"
                   class="btn btn-light btn-sm {{ $paginator->hasMorePages() ? '' : 'is-disabled' }}">Berikutnya ›</a>
            </div>
        @endif
    </div>
@endif
