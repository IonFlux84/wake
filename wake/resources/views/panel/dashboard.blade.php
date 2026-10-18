<x-wake::layout.app :title="__('Dashboard')" active="dashboard">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ now()->locale(app()->getLocale())->isoFormat('dd, D. MMMM · H:mm') }}</div>
            <h1 class="title">{{ __('Good morning, :name', ['name' => explode(' ', auth()->user()->name())[0]]) }}</h1>
        </div>
    </header>
</x-wake::layout.app>
