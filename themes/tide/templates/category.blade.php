@extends('theme::layouts.default')

@section('content')
    @php($posts = $category->posts()->paginate())
    <section class="wrap intro">
        <p class="kicker">{{ __('Category') }}</p>
        <h1 class="display">{{ $category->title() }}</h1>
        @if ($category->description())<p class="lead">{{ $category->description() }}</p>@endif
    </section>
    <section class="wrap grid">
        @foreach ($posts as $post)@include('theme::snippets.post-card', ['post' => $post])@endforeach
    </section>
    <div class="wrap">@include('theme::snippets.pagination', ['paginator' => $posts])</div>
@endsection
