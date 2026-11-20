<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Cache\PageCache;
use Wake\Consent\ConsentRecords;
use Wake\Consent\ConsentSettings;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Localization\Languages;
use Wake\Stats\TrackingConfig;

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

    /**
     * The site's own categories: id, plus label and description per language.
     *
     * @var list<array{id: string, label: array<string, string>, text: array<string, string>}>
     */
    public array $categories = [];

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

        foreach ($settings->customCategories() as $id) {
            $category = ['id' => $id, 'label' => [], 'text' => []];

            foreach ($languages->codes() as $code) {
                $texts = $settings->texts($code);
                $category['label'][$code] = $texts[$id];
                $category['text'][$code] = $texts[$id.'_text'];
            }

            $this->categories[] = $category;
        }
    }

    public function addCategory(Languages $languages): void
    {
        $empty = array_fill_keys($languages->codes(), '');
        $this->categories[] = ['id' => '', 'label' => $empty, 'text' => $empty];
    }

    public function removeCategory(int $index): void
    {
        unset($this->categories[$index]);
        $this->categories = array_values($this->categories);
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
            'categories.*.id' => ['required', 'string', 'distinct', function (string $attribute, mixed $value, \Closure $fail) use ($settings) {
                if (! $settings->isValidCategoryId((string) $value)) {
                    $fail(__('Use 2 to 32 lowercase letters, digits, - or _, starting with a letter, and no name Wake uses itself.'));
                }
            }],
            'categories.*.label.*' => 'nullable|string|max:80',
            'categories.*.text.*' => 'nullable|string|max:500',
        ]);

        // Only texts that differ from what is in place are stored.
        $texts = [];
        foreach ($this->texts as $code => $values) {
            foreach ($this->categories as $category) {
                // Without a label, the banner shows the id.
                $values[$category['id']] = trim((string) ($category['label'][$code] ?? '')) ?: $category['id'];
                $values[$category['id'].'_text'] = trim((string) ($category['text'][$code] ?? ''));
            }

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
            'categories' => array_map(fn (array $category) => ['id' => $category['id']], $this->categories),
            'texts' => $texts,
        ]);

        // Cached pages carry the banner settings, and hit.php needs the categories.
        app(PageCache::class)->flush();
        app(TrackingConfig::class)->sync();

        session()->flash('status', (int) $settings->get('version') > $before
            ? __('Saved. The texts changed, so every visitor is asked again.')
            : __('Saved.'));

        $this->redirectRoute('wake.consent', navigate: false);
    }

    public function render(ConsentSettings $settings, ConsentRecords $records, Index $index): View
    {
        $today = CarbonImmutable::now();
        $texts = $settings->texts(app()->getLocale());
        $labels = ['statistics' => __('Statistics'), 'media' => __('External media'), 'marketing' => __('Marketing')];

        foreach ($settings->customCategories() as $id) {
            $labels[$id] = $texts[$id];
        }

        return view('wake::livewire.consent', [
            'summary' => $records->summary($today->subDays(29), $today),
            'version' => (int) $settings->get('version'),
            'bannerNeeded' => $settings->bannerNeeded(),
            'labels' => $labels,
            'services' => $index->services(),
            'pages' => (new Query('page'))->withFallback(true)->any()->orderBy('title')->get(),
            'sitePrivacy' => (new Query('page'))->withFallback(true)->any()->find((string) config('wake.legal.privacy', '')),
        ]);
    }
}
