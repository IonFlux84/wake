<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count file|:count files', $files->count()) }}</div>
            <h1 class="title">{{ __('Media') }}</h1>
            <p class="lead">{{ __('Files live next to the text of their entry. Upload them in the editor.') }}</p>
        </div>
        <div class="segmented" role="group">
            <button type="button" wire:click="$set('kind', 'images')" @class(['on' => $kind === 'images'])>{{ __('Images') }}</button>
            <button type="button" wire:click="$set('kind', 'files')" @class(['on' => $kind === 'files'])>{{ __('Other files') }}</button>
        </div>
    </header>
    <input type="search" class="input" style="max-width:360px" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by file or entry') }}" aria-label="{{ __('Search by file or entry') }}">
    <section class="grid grid-4">
        @forelse ($files as $item)
            <a class="card" style="padding:0;overflow:hidden;gap:0;text-decoration:none" href="{{ route('wake.entries.edit', ['type' => $item['entry']->type(), 'id' => $item['entry']->id()]) }}">
                @if ($item['file']->isImage())
                    <img src="{{ $item['file']->url(640) }}" alt="" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover">
                @else
                    <span style="display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;background:var(--hover);font:600 14px var(--mono)">.{{ $item['file']->extension() }}</span>
                @endif
                <span style="padding:12px 14px;display:block">
                    <b style="display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $item['file']->name() }}</b>
                    <span class="muted" style="font-size:12px">{{ $item['entry']->title() }} · {{ number_format($item['file']->filesize() / 1024, 0, ',', '.') }} KB</span>
                </span>
            </a>
        @empty
            <p class="muted">{{ __('No files yet.') }}</p>
        @endforelse
    </section>
</div>
