{{-- One setting from a theme.yml or plugin.yml: $key, $option, $model (wire:model path), $pick --}}
@php($type = $option['type'] ?? 'text')
<div class="field">
    <label for="o-{{ $model }}">{{ $pick($option['label'] ?? $key) }}</label>
    @if ($type === 'color')
        <div style="display:flex;gap:8px;align-items:center">
            <input type="color" wire:model.live="{{ $model }}" style="width:44px;height:44px;border:1px solid var(--line);border-radius:10px;padding:2px;background:var(--paper)" aria-label="{{ $pick($option['label'] ?? $key) }}">
            <input id="o-{{ $model }}" class="input mono" style="width:140px" wire:model.live.debounce.400ms="{{ $model }}">
        </div>
    @elseif ($type === 'toggle')
        <label class="check"><input id="o-{{ $model }}" type="checkbox" wire:model="{{ $model }}"> {{ __('Yes') }}</label>
    @elseif ($type === 'select')
        <select id="o-{{ $model }}" class="select" wire:model="{{ $model }}">@foreach ((array) ($option['options'] ?? []) as $value => $text)<option value="{{ $value }}">{{ $pick($text) }}</option>@endforeach</select>
    @elseif ($type === 'textarea')
        <textarea id="o-{{ $model }}" class="textarea" wire:model="{{ $model }}" placeholder="{{ $pick($option['placeholder'] ?? '') }}"></textarea>
    @else
        <input id="o-{{ $model }}" class="input" wire:model="{{ $model }}" placeholder="{{ $pick($option['placeholder'] ?? '') }}">
    @endif
    @if (! empty($option['hint']))<span class="hint">{{ $pick($option['hint']) }}</span>@endif
    @error($model)<span class="error">{{ $message }}</span>@enderror
</div>
