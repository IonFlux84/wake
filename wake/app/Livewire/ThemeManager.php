<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wake\Cache\PageCache;
use Wake\Support\PublicAssets;
use Wake\Support\Settings;
use Wake\Theme\Themes;

/**
 * Themes: choose, configure, copy, install and remove.
 */
class ThemeManager extends Component
{
    use WithFileUploads;

    /** @var array<string, mixed> options of the active theme */
    public array $options = [];

    /** @var mixed a theme ZIP */
    public $zip = null;

    public function mount(Themes $themes): void
    {
        abort_unless(auth()->user()->can('themes.manage'), 403);
        $this->loadOptions($themes);
    }

    public function activate(Themes $themes, string $folder): void
    {
        abort_unless($themes->get($folder), 404);

        Settings::save(['theme' => $folder]);
        app(PageCache::class)->flush();
        $this->loadOptions($themes);
        session()->flash('status', __('“:name” is now the theme of the website.', ['name' => $themes->get($folder)->name()]));
    }

    public function saveOptions(Themes $themes): void
    {
        $theme = $themes->get((string) config('wake.theme'));
        $values = [];

        foreach ($theme?->options() ?? [] as $key => $option) {
            $value = $this->options[$key] ?? null;

            if (($option['type'] ?? '') === 'color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                $this->addError('options.'.$key, __('Please choose a colour like #3D5AFE.'));

                continue;
            }

            $values[$key] = ($option['type'] ?? '') === 'toggle' ? (bool) $value : $value;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        Settings::save(['theme_options' => $values]);
        app(PageCache::class)->flush();
        session()->flash('status', __('Theme settings saved.'));
    }

    public function duplicate(Themes $themes, string $folder): void
    {
        $copy = $themes->duplicate($folder, $themes->get($folder)->name().' '.__('(copy)'));
        session()->flash('status', __('Copied to themes/:folder. Change the copy as you like, then activate it.', ['folder' => $copy->folder]));
    }

    public function updatedZip(Themes $themes): void
    {
        $this->validate(['zip' => 'required|file|max:51200|mimes:zip']);

        try {
            $theme = $themes->install($this->zip->getRealPath());
            session()->flash('status', __('“:name” is installed. Activate it when you are ready.', ['name' => $theme->name()]));
        } catch (InvalidArgumentException $e) {
            $this->addError('zip', $e->getMessage());
        }

        $this->zip = null;
    }

    public function remove(Themes $themes, string $folder): void
    {
        try {
            $themes->trash($folder);
            session()->flash('status', __('Theme moved to the trash.'));
        } catch (InvalidArgumentException $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    protected function loadOptions(Themes $themes): void
    {
        $this->options = [];

        foreach ($themes->get((string) config('wake.theme'))?->options() ?? [] as $key => $option) {
            $this->options[$key] = config('wake.theme_options.'.$key) ?? $option['default'] ?? (($option['type'] ?? '') === 'toggle' ? false : '');
        }
    }

    public function render(Themes $themes): View
    {
        $all = $themes->all();

        return view('wake::livewire.theme-manager', [
            'themes' => $all,
            'active' => (string) config('wake.theme'),
            'screenshots' => collect($all)->map(fn ($t) => $t->screenshot()
                ? PublicAssets::url($t->screenshot(), 'assets/themes/'.$t->folder.'/'.basename($t->screenshot()))
                : null),
        ]);
    }
}
