<article class="post-card">
    @if ($post->image()?->isImage())
        <a href="{{ $post->url() }}" class="post-card-image">{{ $post->image()->picture($post->title(), '(min-width: 720px) 33vw, 100vw') }}</a>
    @endif
    <p class="meta">
        @if ($post->date())<time datetime="{{ $post->date()->toDateString() }}">{{ $post->date()->locale($post->language())->isoFormat('LL') }}</time>@endif
    </p>
    <h2><a href="{{ $post->url() }}">{{ $post->title() }}</a></h2>
    <p>{{ $post->excerpt(28) }}</p>
</article>
