<p class="meta">
    @if ($post->date())<time datetime="{{ $post->date()->toIso8601String() }}">{{ $post->date()->locale($post->language())->isoFormat('LL') }}</time>@endif
    <span>{{ trans_choice(':count min read|:count min read', $post->readingTime()) }}</span>
</p>
