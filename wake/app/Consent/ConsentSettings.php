<?php

namespace Wake\Consent;

use Symfony\Component\Yaml\Yaml;
use Wake\Content\Query;
use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Settings of the consent banner in settings/consent.yml, editable in the panel.
 * Texts exist per language; every change to them raises the version, which
 * asks every visitor again.
 */
class ConsentSettings
{
    /** The categories Wake brings itself. Custom categories cannot use these ids. */
    public const BUILT_IN = ['necessary', 'statistics', 'media', 'marketing'];

    /** What the id of a custom category looks like. */
    public const ID_PATTERN = '/^[a-z][a-z0-9_-]{1,31}$/';

    /** @var array<string, mixed>|null */
    protected ?array $settings = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->settings === null) {
            $file = $this->file();
            $stored = is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
            $this->settings = array_replace_recursive($this->defaults(), $stored);
        }

        return $this->settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    /**
     * The categories a visitor can agree to, besides "necessary".
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return [...array_values(array_filter([
            $this->asksForStatistics() ? 'statistics' : null,
            'media',
            $this->get('marketing') ? 'marketing' : null,
        ])), ...$this->customCategories()];
    }

    /**
     * The site's own categories from the "categories" list, in their order.
     * Invalid ids, ids of built-in categories or texts, and duplicates are
     * ignored.
     *
     * @return list<string>
     */
    public function customCategories(): array
    {
        return $this->normalizeCategories($this->get('categories', []));
    }

    /**
     * Whether an id can name a custom category: lowercase, 2 to 32 characters,
     * not a built-in category and not one of the banner's own text keys.
     */
    public function isValidCategoryId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1
            && ! str_ends_with($id, '_text')
            && ! in_array($id, self::BUILT_IN, true)
            && ! array_key_exists($id, $this->defaultTexts('en'));
    }

    /**
     * @return list<string>
     */
    protected function normalizeCategories(mixed $categories): array
    {
        $ids = [];

        foreach (is_array($categories) ? $categories : [] as $category) {
            $id = is_array($category) ? ($category['id'] ?? null) : $category;

            if (is_string($id) && $this->isValidCategoryId($id) && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Where the banner's privacy link leads: a custom address (privacy_url),
     * else the chosen page (privacy_page), else the site's privacy policy
     * (setting legal.privacy). Pages count only once published; null hides
     * the link.
     */
    public function privacyLink(?string $language = null): ?string
    {
        $url = trim((string) $this->get('privacy_url'));

        if ($url !== '') {
            return preg_match('#^https?://#i', $url) ? $url : url($url);
        }

        $id = (string) ($this->get('privacy_page') ?: config('wake.legal.privacy', ''));
        $page = $id !== '' ? (new Query)->withFallback(true)->in($language ?? app(Languages::class)->current())->find($id) : null;

        return $page?->isPublished() ? $page->url() : null;
    }

    /**
     * Statistics need a yes in the banner when the statistics cookie is
     * offered (level 2), or when level 1 waits for consent.
     */
    public function asksForStatistics(): bool
    {
        return (bool) $this->get('statistics') || config('wake.tracking.level1') === 'consent';
    }

    /**
     * Whether the banner appears on its own. Without a statistics cookie,
     * marketing scripts, custom categories or level-1 tracking that waits for
     * consent there is nothing to ask for up front: embeds ask when they are
     * clicked.
     */
    public function bannerNeeded(): bool
    {
        return (bool) ($this->get('statistics') || $this->get('marketing') || $this->get('always')
            || $this->customCategories() !== []
            || config('wake.tracking.level1') === 'consent');
    }

    /**
     * @return array<string, string>
     */
    public function texts(string $language): array
    {
        $texts = $this->get('texts', []);
        $defaults = $this->defaultTexts($language);

        // Without the cookie, "statistics" means counting visits anonymously.
        if (! $this->get('statistics')) {
            $defaults['statistics_text'] = $defaults['statistics_anonymous_text'];
        }

        unset($defaults['statistics_anonymous_text']);

        // A custom category without a text shows its id and no description.
        foreach ($this->customCategories() as $id) {
            $defaults[$id] = $id;
            $defaults[$id.'_text'] = '';
        }

        return array_merge($defaults, $texts[$language] ?? []);
    }

    /**
     * Merge changes into the settings. A "categories" list replaces the old
     * one, and the texts of removed categories go with them. Changing the
     * texts or the categories raises the version.
     *
     * @param  array<string, mixed>  $changes
     */
    public function save(array $changes): void
    {
        $before = $this->all();
        $settings = array_replace_recursive($before, $changes);

        if (array_key_exists('categories', $changes)) {
            $ids = $this->normalizeCategories($changes['categories']);
            $settings['categories'] = array_map(fn (string $id) => ['id' => $id], $ids);
            $removed = array_diff($this->customCategories(), $ids);

            foreach ($settings['texts'] as $language => $texts) {
                foreach ($removed as $id) {
                    unset($texts[$id], $texts[$id.'_text']);
                }

                $settings['texts'][$language] = $texts;
            }

            $settings['texts'] = array_filter($settings['texts']);
        }

        if ($settings['texts'] != $before['texts']
            || $this->normalizeCategories($settings['categories']) !== $this->customCategories()) {
            $settings['version'] = (int) $settings['version'] + 1;
        }

        Files::write($this->file(), Yaml::dump($settings, 4, 2));
        $this->settings = null;
    }

    public function file(): string
    {
        return Paths::settings('consent.yml');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'version' => 1,
            'statistics' => false,
            'marketing' => false,
            'always' => true,
            'position' => 'bottom',
            'privacy_page' => '',
            'privacy_url' => '',
            'categories' => [],
            'texts' => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultTexts(string $language): array
    {
        $de = [
            'title' => 'Ihre Privatsphäre',
            'text' => 'Wir verwenden Cookies und externe Dienste nur, wenn Sie zustimmen. Sie können Ihre Auswahl jederzeit über „Cookie-Einstellungen“ ändern.',
            'accept' => 'Alle akzeptieren',
            'decline' => 'Ablehnen',
            'settings' => 'Einstellungen',
            'save' => 'Auswahl speichern',
            'privacy' => 'Datenschutzerklärung',
            'reopen' => 'Cookie-Einstellungen',
            'necessary' => 'Notwendig',
            'necessary_text' => 'Speichert Ihre Auswahl. Immer aktiv.',
            'statistics' => 'Statistik',
            'statistics_text' => 'Ein Cookie hilft uns zu verstehen, ob Sie wiederkommen. Die Daten bleiben auf unserem Server.',
            'statistics_anonymous_text' => 'Wir zählen Besuche anonym und ohne Cookie. Die Daten bleiben auf unserem Server.',
            'media' => 'Externe Medien',
            'media_text' => 'Videos, Karten und Beiträge anderer Anbieter. Dabei werden Daten an diese Anbieter übertragen.',
            'marketing' => 'Marketing',
            'marketing_text' => 'Skripte für Werbung und Reichweitenmessung anderer Anbieter.',
            'embed_text' => 'Hier ist ein Inhalt von :service eingebunden. Beim Laden werden Daten an :service übertragen.',
            'embed_load' => 'Inhalt laden',
            'embed_always' => ':service immer erlauben',
        ];

        $en = [
            'title' => 'Your privacy',
            'text' => 'We only use cookies and external services if you agree. You can change your choice at any time under "Cookie settings".',
            'accept' => 'Accept all',
            'decline' => 'Decline',
            'settings' => 'Settings',
            'save' => 'Save choice',
            'privacy' => 'Privacy policy',
            'reopen' => 'Cookie settings',
            'necessary' => 'Necessary',
            'necessary_text' => 'Stores your choice. Always active.',
            'statistics' => 'Statistics',
            'statistics_text' => 'A cookie helps us understand whether you come back. The data stays on our server.',
            'statistics_anonymous_text' => 'We count visits anonymously and without a cookie. The data stays on our server.',
            'media' => 'External media',
            'media_text' => 'Videos, maps and posts from other providers. Data is sent to these providers.',
            'marketing' => 'Marketing',
            'marketing_text' => 'Scripts of other providers for advertising and reach measurement.',
            'embed_text' => 'This is content from :service. Loading it sends data to :service.',
            'embed_load' => 'Load content',
            'embed_always' => 'Always allow :service',
        ];

        return str_starts_with($language, 'de') ? $de : $en;
    }
}
