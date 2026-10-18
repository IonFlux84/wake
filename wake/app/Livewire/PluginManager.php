<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Wake\Cache\PageCache;
use Wake\Plugins\Plugins;
use Wake\Support\Settings;

/**
 * The plugins in site/plugins: switch them on and off and change their
 * settings. Installing happens by copying the folder (FTP or Git).
 */
class PluginManager extends Component
{
    /** @var array<string, array<string, mixed>> folder => option => value */
    public array $options = [];

    public function mount(Plugins $plugins): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        foreach ($plugins->all() as $folder => $info) {
            foreach ($info->options() as $key => $option) {
                $this->options[$folder][$key] = config("wake.plugins.options.{$folder}.{$key}") ?? $option['default'] ?? (($option['type'] ?? '') === 'toggle' ? false : '');
            }
        }
    }

    public function toggle(Plugins $plugins, string $folder): void
    {
        abort_unless($plugins->get($folder), 404);

        $disabled = (array) config('wake.plugins.disabled', []);
        $disabled = in_array($folder, $disabled, true) ? array_values(array_diff($disabled, [$folder])) : [...$disabled, $folder];

        Settings::save(['plugins.disabled' => $disabled]);
        app(PageCache::class)->flush();
        session()->flash('status', in_array($folder, $disabled, true) ? __('Plugin switched off.') : __('Plugin switched on.'));
        $this->redirect(route('wake.plugins'));
    }

    public function saveOptions(Plugins $plugins, string $folder): void
    {
        $info = $plugins->get($folder) ?? abort(404);
        $values = [];

        foreach ($info->options() as $key => $option) {
            $value = $this->options[$folder][$key] ?? null;

            if (($option['type'] ?? '') === 'color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                $this->addError("options.{$folder}.{$key}", __('Please choose a colour like #C6FF3D.'));

                return;
            }

            $values[$key] = ($option['type'] ?? '') === 'toggle' ? (bool) $value : $value;
        }

        Settings::save(['plugins.options' => [...(array) config('wake.plugins.options', []), $folder => $values]]);
        app(PageCache::class)->flush();
        session()->flash('status', __('Settings of :name saved.', ['name' => $info->name()]));
    }

    public function render(Plugins $plugins): View
    {
        return view('wake::livewire.plugin-manager', ['plugins' => $plugins->all(), 'manager' => $plugins]);
    }
}
