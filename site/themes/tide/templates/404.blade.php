@extends('theme::layouts.default')

@section('content')
    <section class="intro">
        <h1>404</h1>
        <p class="lead">{{ __('This page does not exist (anymore).') }}</p>
        <p><a href="{{ $site->url() }}">{{ __('Back to the start page') }}</a></p>
    </section>
@endsection
