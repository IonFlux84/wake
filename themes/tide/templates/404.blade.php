@extends('theme::layouts.default')

@section('content')
    <section class="wrap narrow intro">
        <p class="kicker">404</p>
        <h1 class="display">{{ __('This page does not exist (anymore).') }}</h1>
        <form class="search-form" role="search" action="{{ app(\Wake\Localization\Languages::class)->url('search') }}">
            <input type="search" name="q" placeholder="{{ __('What are you looking for?') }}" aria-label="{{ __('Search') }}">
            <button type="submit">{{ __('Search') }}</button>
        </form>
        <p><a href="{{ $site->url() }}">{{ __('Back to the start page') }}</a></p>
    </section>
@endsection
