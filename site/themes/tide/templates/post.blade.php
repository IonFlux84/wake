@extends('theme::layouts.default')

@section('content')
    <article class="post">
        <header>
            <p class="meta">
                @if ($post->date())<time datetime="{{ $post->date()->toDateString() }}">{{ $post->date()->locale($post->language())->isoFormat('LL') }}</time> · @endif
                {{ trans_choice(':count min read|:count min read', $post->readingTime()) }}
            </p>
            <h1>{{ $post->title() }}</h1>
            @if ($post->tags()->isNotEmpty())
                <p class="tags">
                    @foreach ($post->tags() as $tag)
                        <a href="{{ $tag->url() }}">#{{ $tag->title() }}</a>
                    @endforeach
                </p>
            @endif
        </header>

        @if ($post->image()?->isImage())
            <figure class="cover">{{ $post->image()->picture($post->title(), '(min-width: 960px) 960px, 100vw', ['loading' => 'eager']) }}</figure>
        @endif

        <div class="prose">{{ $post->content() }}</div>
    </article>
@endsection
