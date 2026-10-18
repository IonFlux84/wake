@php
    $pick = fn ($value) => is_array($value) ? ($value[app()->getLocale()] ?? $value['en'] ?? reset($value)) : $value;
    $fieldType = $field['type'] ?? 'text';
    $label = $this->fieldLabel($key, $field);
    $shared = ($field['translate'] ?? true) === false && $language !== app(\Wake\Localization\Languages::class)->default();
@endphp
<div class="field">
    <label for="f-{{ $key }}">{{ $label }}@if (! empty($field['required'])) <span class="muted">*</span>@endif</label>
    @if ($pluginField = app(\Wake\Plugins\Plugins::class)->fields[$fieldType] ?? null)
        @include($pluginField['view'], ['key' => $key, 'field' => $field, 'model' => 'custom.'.$key, 'disabled' => $shared])
    @elseif ($fieldType === 'textarea')
        <textarea id="f-{{ $key }}" class="textarea" style="min-height:80px" wire:model.blur="custom.{{ $key }}" placeholder="{{ $pick($field['placeholder'] ?? '') }}" @disabled($shared)></textarea>
    @elseif ($fieldType === 'toggle')
        <label class="check"><input id="f-{{ $key }}" type="checkbox" wire:model.live="custom.{{ $key }}" @disabled($shared)> {{ __('Yes') }}</label>
    @elseif ($fieldType === 'select')
        <select id="f-{{ $key }}" class="select" wire:model="custom.{{ $key }}" @disabled($shared)>
            <option value=""></option>
            @foreach ((array) ($field['options'] ?? []) as $value => $text)
                <option value="{{ is_int($value) ? $pick($text) : $value }}">{{ $pick($text) }}</option>
            @endforeach
        </select>
    @else
        <input id="f-{{ $key }}" class="input" wire:model.blur="custom.{{ $key }}" placeholder="{{ $pick($field['placeholder'] ?? '') }}" @disabled($shared)
               type="{{ ['number' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'url' => 'url', 'email' => 'email'][$fieldType] ?? 'text' }}">
    @endif
    @if ($shared)
        <span class="hint">{{ __('The same in every language. Change it in the default language.') }}</span>
    @elseif (! empty($field['hint']))
        <span class="hint">{{ $pick($field['hint']) }}</span>
    @endif
    @error('custom.'.$key)<span class="error">{{ $message }}</span>@enderror
</div>
