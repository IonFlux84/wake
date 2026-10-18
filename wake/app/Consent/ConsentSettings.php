<?php

namespace Wake\Consent;

use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Settings of the consent banner in site/consent.yml, editable in the panel.
 * Texts exist per language; every change to them raises the version, which
 * asks every visitor again.
 */
class ConsentSettings
{
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
        return array_values(array_filter([
            $this->get('statistics') ? 'statistics' : null,
            'media',
            $this->get('marketing') ? 'marketing' : null,
        ]));
    }

    /**
     * Whether the banner appears on its own. Without a statistics cookie,
     * marketing scripts or level-1 tracking that waits for consent there is
     * nothing to ask for up front: embeds ask when they are clicked.
     */
    public function bannerNeeded(): bool
    {
        return (bool) ($this->get('statistics') || $this->get('marketing') || $this->get('always')
            || config('wake.tracking.level1') === 'consent');
    }

    /**
     * @return array<string, string>
     */
    public function texts(string $language): array
    {
        $texts = $this->get('texts', []);

        return array_merge($this->defaultTexts($language), $texts[$language] ?? []);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function save(array $changes): void
    {
        $settings = array_replace_recursive($this->all(), $changes);

        if (($changes['texts'] ?? null) !== null && $changes['texts'] != ($this->all()['texts'] ?? [])) {
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
            'always' => false,
            'position' => 'bottom',
            'privacy_url' => '',
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
