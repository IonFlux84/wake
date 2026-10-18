<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Consent\ConsentRecords;
use Wake\Consent\ConsentSettings;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Localization\Languages;

/**
 * Consent page: how visitors decide, and the banner editor with a preview.
 */
class Consent extends Component
{
    public bool $statistics = false;

    public bool $marketing = false;

    public bool $always = false;

    public string $position = 'bottom';

    /** The banner's privacy link: "" for the site's privacy policy, a page id, or "custom". */
    public string $privacy = '';

    /** The address when $privacy is "custom". */
    public string $privacyUrl = '';

    /** @var array<string, array<string, string>> */
    public array $texts = [];

    public string $language = '';

    public function mount(ConsentSettings $settings, Languages $languages): void
    {
        $this->statistics = (bool) $settings->get('statistics');
        $this->marketing = (bool) $settings->get('marketing');
        $this->always = (bool) $settings->get('always');
        $this->position = (string) $settings->get('position');
        $this->privacyUrl = (string) $settings->get('privacy_url');
        $this->privacy = $this->privacyUrl !== '' ? 'custom' : (string) $settings->get('privacy_page');
        $this->language = $languages->default();

        foreach ($languages->codes() as $code) {
            $this->texts[$code] = array_intersect_key($settings->texts($code), array_flip(['title', 'text', 'accept', 'decline']));
        }
    }

    public function save(ConsentSettings $settings): void
    {
        abort_unless(auth()->user()->can('consent.manage'), 403);

        $this->validate([
            'position' => 'in:bottom,center',
            'privacy' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== '' && $value !== 'custom' && (new Query('page'))->find((string) $value) === null) {
                    $fail(__('This page does not exist.'));
                }
            }],
            'privacyUrl' => ['nullable', 'string', 'max:300', 'required_if:privacy,custom', 'regex:#^(/|https?://)#i'],
            'texts.*.title' => 'required|string|max:120',
            'texts.*.text' => 'required|string|max:1000',
            'texts.*.accept' => 'required|string|max:40',
            'texts.*.decline' => 'required|string|max:40',
        ]);

        // Only texts that differ from the defaults are stored.
        $texts = [];
        foreach ($this->texts as $code => $values) {
            $defaults = (new ConsentSettings)->texts($code);
            $custom = array_filter($values, fn ($value, $key) => ($defaults[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH);

            if ($custom !== []) {
                $texts[$code] = $custom;
            }
        }

        $before = (int) $settings->get('version');
        $settings->save([
            'statistics' => $this->statistics,
            'marketing' => $this->marketing,
            'always' => $this->always,
            'position' => $this->position,
            'privacy_page' => $this->privacy === 'custom' ? '' : $this->privacy,
            'privacy_url' => $this->privacy === 'custom' ? trim($this->privacyUrl) : '',
            'texts' => $texts,
        ]);

        session()->flash('status', (int) $settings->get('version') > $before
            ? __('Saved. The texts changed, so every visitor is asked again.')
            : __('Saved.'));

        $this->redirectRoute('wake.consent', navigate: false);
    }

    public function render(ConsentSettings $settings, ConsentRecords $records, Index $index): View
    {
        $today = CarbonImmutable::now();

        return view('wake::livewire.consent', [
            'summary' => $records->summary($today->subDays(29), $today),
            'version' => (int) $settings->get('version'),
            'bannerNeeded' => $settings->bannerNeeded(),
            'services' => $index->services(),
            'pages' => (new Query('page'))->withFallback(true)->any()->orderBy('title')->get(),
            'sitePrivacy' => (new Query('page'))->withFallback(true)->any()->find((string) config('wake.legal.privacy', '')),
        ]);
    }
}
