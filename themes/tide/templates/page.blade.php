@extends('theme::layouts.default')

@section('content')
    <article class="page">
        <header class="wrap narrow post-header">
            @if ($page->parent())<p class="kicker-row"><a class="kicker" href="{{ $page->parent()->url() }}">{{ $page->parent()->title() }}</a></p>@endif
            <h1 class="display">{{ $page->title() }}</h1>
        </header>
        @if ($page->image()?->isImage())
            <figure class="wrap cover">{{ $page->image()->picture($page->title(), '(min-width: 1100px) 1100px, 100vw', ['loading' => 'eager']) }}</figure>
        @endif
        <div class="wrap narrow prose">{{ $page->content() }}</div>

        @php($children = $page->children()->published()->get())
        @if ($children->isNotEmpty())
            <section class="wrap grid children">
                @foreach ($children as $child)
                    <a class="card card-link" href="{{ $child->url() }}"><span class="card-body"><span class="card-title">{{ $child->title() }}</span><span class="card-excerpt">{{ $child->excerpt(20) }}</span></span></a>
                @endforeach
            </section>
        @endif
    </article>
@endsection
