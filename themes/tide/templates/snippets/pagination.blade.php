@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('Pages') }}">
        @if ($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}" rel="prev">← {{ __('Newer posts') }}</a>@else<span></span>@endif
        <span class="muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Older posts') }} →</a>@else<span></span>@endif
    </nav>
@endif
