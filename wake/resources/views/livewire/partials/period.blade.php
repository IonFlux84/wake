<div class="segmented" role="group" aria-label="{{ __('Period') }}">
    @foreach (['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days'), '12m' => __('12 months')] as $key => $label)
        <button type="button" wire:click="setPeriod('{{ $key }}')" @class(['on' => $period === $key]) aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
</div>
