@extends('theme::layouts.default')

@section('content')
    <div class="progress" aria-hidden="true"><span></span></div>
    <article class="post">
        <header class="wrap narrow post-header">
            @if ($post->tags()->isNotEmpty())
                <p class="kicker-row">@foreach ($post->tags() as $tag)<a class="kicker" href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach</p>
            @endif
            <h1 class="display">{{ $post->title() }}</h1>
            @if ($post->get('excerpt'))<p class="lead">{{ $post->get('excerpt') }}</p>@endif
            @include('theme::snippets.meta', ['post' => $post])
        </header>

        @if ($post->image()?->isImage())
            <figure class="wrap cover">{{ $post->image()->picture($post->title(), '(min-width: 1100px) 1100px, 100vw', ['loading' => 'eager', 'fetchpriority' => 'high']) }}</figure>
        @endif

        <div class="wrap narrow prose">{{ $post->content() }}</div>
    </article>

    @php
        $tag = $post->tags()->first();
        $related = $tag ? $tag->posts()->where(fn ($f, $row) => $row['id'] !== $post->id())->limit(3)->get() : collect();
        $all = $site->posts()->get();
        $position = $all->search(fn ($p) => $p->id() === $post->id());
        $newer = $position !== false && $position > 0 ? $all[$position - 1] : null;
        $older = $position !== false ? $all[$position + 1] ?? null : null;
    @endphp

    @if ($newer || $older)
        <nav class="wrap narrow post-nav" aria-label="{{ __('More posts') }}">
            @if ($older)<a href="{{ $older->url() }}" rel="prev"><span class="muted">← {{ __('Older') }}</span>{{ $older->title() }}</a>@else<span></span>@endif
            @if ($newer)<a href="{{ $newer->url() }}" rel="next" class="right"><span class="muted">{{ __('Newer') }} →</span>{{ $newer->title() }}</a>@endif
        </nav>
    @endif

    @if ($related->isNotEmpty())
        <section class="wrap related">
            <h2 class="section-title">{{ __('More about :tag', ['tag' => $tag->title()]) }}</h2>
            <div class="grid">@foreach ($related as $item)@include('theme::snippets.post-card', ['post' => $item])@endforeach</div>
        </section>
    @endif

    <script>
        // Reading progress: a thin line in the accent colour.
        (() => { const bar = document.querySelector('.progress span'); const update = () => { const h = document.documentElement; bar.style.transform = 'scaleX(' + Math.min(1, h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight)) + ')'; }; addEventListener('scroll', update, { passive: true }); update(); })();
    </script>
@endsection
