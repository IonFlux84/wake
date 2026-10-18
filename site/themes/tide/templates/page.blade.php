@extends('theme::layouts.default')

@section('content')
    <article class="page">
        <h1>{{ $page->title() }}</h1>
        <div class="prose">{{ $page->content() }}</div>

        @if ($page->children()->published()->exists())
            <ul class="children">
                @foreach ($page->children()->published() as $child)
                    <li><a href="{{ $child->url() }}">{{ $child->title() }}</a></li>
                @endforeach
            </ul>
        @endif
    </article>
@endsection
