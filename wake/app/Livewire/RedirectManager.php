<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Wake\Cache\PageCache;
use Wake\Content\Query;
use Wake\Content\Redirects;
use Wake\Localization\Languages;
use Wake\Media\Media;

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

        if (Redirects::kind($target) === 'entry' && ! (new Query)->find($target)) {
            $this->addError('to', __('Choose an entry, a path like /blog, a file like media/flyer.pdf, a full address starting with https:// or "gone".'));

            return;
        }

        if (Redirects::kind($target) === 'media' && ! Media::find($target)) {
            $this->addError('to', __('There is no such file in the media library.'));

            return;
        }

        if (Redirects::kind($target) === 'path' && trim($target, '/') === trim($this->from, '/')) {
            $this->addError('to', __('An address cannot lead to itself.'));

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
                $kind = Redirects::kind($target);
                $entry = $kind === 'entry' ? (new Query)->in($language)->find($target) : null;
                $rows[] = ['language' => $language, 'path' => $path, 'target' => $target, 'kind' => $kind, 'entry' => $entry];
            }
        }

        return view('wake::livewire.redirect-manager', [
            'rows' => $rows,
            'languages' => app(Languages::class),
            'entries' => (new Query)->in($this->language)->withFallback(true)->orderBy('title')->get(),
        ]);
    }
}
