<x-wake::layout.auth :title="__('Connect an assistant')">
    @php($labels = ['stats:read' => __('Read statistics'), 'content:read' => __('Read content'), 'content:write' => __('Write drafts'), 'content:publish' => __('Publish')])
    <div class="auth-head">
        <p class="eyebrow">{{ __('Assistant') }} · {{ app(\Wake\Content\Site::class)->title() }}</p>
        @if ($problem)
            <h1 class="auth-title">{{ __('Cannot connect') }}</h1>
            <p class="auth-lead">{{ $problem }}</p>
        @else
            <h1 class="auth-title">{{ __('Connect :name?', ['name' => $client['client_name']]) }}</h1>
            <p class="auth-lead">{{ __(':name wants to work with this website on your behalf. It will return to :host. You can disconnect it at any time under Assistants.', ['name' => $client['client_name'], 'host' => $host]) }}</p>
        @endif
    </div>
    @unless ($problem)
        <form class="auth-form" method="post" action="{{ route('wake.oauth.authorize') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            @foreach ($params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <fieldset style="border:0;padding:0;margin:0;display:grid;gap:10px">
                <legend class="label" style="margin-bottom:8px">{{ __('What may it do?') }}</legend>
                @foreach ($allowed as $scope)
                    <label class="check"><input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope, $checked, true))> {{ $labels[$scope] ?? $scope }}</label>
                @endforeach
            </fieldset>
            <p class="muted" style="margin:0;font-size:13px">{{ __('Assistants never delete anything. New posts always start as drafts.') }}</p>
            <button class="btn btn-primary auth-submit" type="submit" name="decision" value="approve" :disabled="busy">{{ __('Connect') }} <span class="mono" aria-hidden="true">→</span></button>
            <button class="btn auth-submit" type="submit" name="decision" value="deny">{{ __('Cancel') }}</button>
        </form>
    @endunless
</x-wake::layout.auth>
