@extends('theme::layouts.default')

@section('content')
    @php($posts = $tag->posts()->paginate())
    <section class="wrap intro">
        <p class="kicker">{{ __('Tag') }}</p>
        <h1 class="display">{{ $tag->title() }}</h1>
        @if ($tag->body() !== '')<div class="lead prose">{{ $tag->content() }}</div>@endif
        <p class="muted">{{ trans_choice(':count post|:count posts', $posts->total()) }}</p>
    </section>
    <section class="wrap grid">
        @foreach ($posts as $post)@include('theme::snippets.post-card', ['post' => $post])@endforeach
    </section>
    <div class="wrap">@include('theme::snippets.pagination', ['paginator' => $posts])</div>
@endsection
