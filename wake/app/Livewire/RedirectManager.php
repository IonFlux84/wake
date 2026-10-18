<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Wake\Cache\PageCache;
use Wake\Content\Query;
use Wake\Content\Redirects;
use Wake\Localization\Languages;

/**
 * Old addresses and where they lead, from settings/redirects.yml. Wake adds
 * entries itself when a published address changes; here people can add and
 * remove them by hand.
 */
class RedirectManager extends Component
{
    public string $from = '';

    public string $to = '';

    public string $language = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        $this->language = app(Languages::class)->default();
    }

    public function add(Redirects $redirects): void
    {
        $this->validate([
            'from' => ['required', 'string', 'max:300', 'regex:/^\/?[^\s?#]+$/'],
            'to' => ['required', 'string', 'max:500'],
            'language' => 'required|in:'.implode(',', app(Languages::class)->codes()),
        ], ['from.regex' => __('An address like /old-page, without spaces.')]);

        $target = trim($this->to);
        $isUrl = (bool) preg_match('#^https?://#', $target);

        if (! $isUrl && ! (new Query)->find($target)) {
            $this->addError('to', __('Choose an entry or enter a full address starting with https://.'));

            return;
        }

        $redirects->add($this->language, $this->from, $target);
        $this->reset('from', 'to');
        app(PageCache::class)->flush();
        session()->flash('status', __('Redirect saved.'));
    }

    public function remove(Redirects $redirects, string $language, string $path): void
    {
        $redirects->remove($language, $path);
        session()->flash('status', __('Redirect removed.'));
    }

    public function render(Redirects $redirects): View
    {
        $rows = [];

        foreach ($redirects->all() as $language => $paths) {
            foreach ($paths as $path => $target) {
                $entry = preg_match('#^https?://#', $target) ? null : (new Query)->in($language)->find($target);
                $rows[] = ['language' => $language, 'path' => $path, 'target' => $target, 'entry' => $entry];
            }
        }

        return view('wake::livewire.redirect-manager', [
            'rows' => $rows,
            'languages' => app(Languages::class),
            'entries' => (new Query)->in($this->language)->withFallback(true)->orderBy('title')->get(),
        ]);
    }
}
