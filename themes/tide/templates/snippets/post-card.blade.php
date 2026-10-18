<article class="card {{ ($large ?? false) ? 'card-large' : '' }}">
    @if ($post->thumbnail())
        <a href="{{ $post->url() }}" class="card-image" tabindex="-1" aria-hidden="true">{{ $post->thumbnail()->picture('', ($large ?? false) ? '(min-width: 960px) 60vw, 100vw' : '(min-width: 960px) 33vw, (min-width: 640px) 50vw, 100vw') }}</a>
    @endif
    <div class="card-body">
        @if ($post->tags()->isNotEmpty())<a class="kicker" href="{{ $post->tags()->first()->url() }}">{{ $post->tags()->first()->title() }}</a>@endif
        <h2 class="card-title"><a href="{{ $post->url() }}">{{ $post->title() }}</a></h2>
        <p class="card-excerpt">{{ $post->excerpt(($large ?? false) ? 40 : 24) }}</p>
        @include('theme::snippets.meta', ['post' => $post])
    </div>
</article>
