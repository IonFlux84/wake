@extends('theme::layouts.default')

@section('content')
    <section class="wrap narrow intro">
        <h1 class="display">{{ __('Search') }}</h1>
        <form class="search-form" role="search">
            <input type="search" name="q" value="{{ $query }}" placeholder="{{ __('What are you looking for?') }}" aria-label="{{ __('Search') }}" autofocus>
            <button type="submit">{{ __('Search') }}</button>
        </form>
        @if ($query !== '')
            <p class="muted">{{ trans_choice(':count result for “:query”|:count results for “:query”', $results->count(), ['query' => $query]) }}</p>
        @endif
    </section>
    <section class="wrap narrow results">
        @foreach ($results as $result)
            <article class="result">
                <h2 class="card-title"><a href="{{ $result->url() }}">{{ $result->title() }}</a></h2>
                <p class="card-excerpt">{{ $result->excerpt(30) }}</p>
            </article>
        @endforeach
    </section>
@endsection
