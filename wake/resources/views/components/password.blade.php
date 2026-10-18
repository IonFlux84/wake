@props(['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'minlength' => null, 'autofocus' => false, 'hint' => null])
@php($described = trim(($hint ? $id.'-hint ' : '').($errors->has($name) ? $id.'-error' : '')))
<div class="auth-secret" x-data="{ shown: false }">
    <input class="input" id="{{ $id }}" type="password" :type="shown ? 'text' : 'password'" name="{{ $name }}" required autocomplete="{{ $autocomplete }}"
           @if ($minlength) minlength="{{ $minlength }}" @endif @if ($autofocus) autofocus @endif
           @if ($errors->has($name)) aria-invalid="true" @endif @if ($described) aria-describedby="{{ $described }}" @endif>
    <button type="button" class="auth-reveal" x-cloak @click="shown = !shown" :aria-pressed="shown.toString()" aria-controls="{{ $id }}" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button>
</div>
@if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
@error($name)<span class="error" id="{{ $id }}-error">{{ $message }}</span>@enderror
