<?php

namespace Wake\Livewire;

use DateTimeZone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Cache\PageCache;
use Wake\Content\ContentTypes;
use Wake\Content\FrontMatter;
use Wake\Content\History;
use Wake\Content\Index;
use Wake\Import\GhostImporter;
use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Health;
use Wake\Support\Paths;
use Wake\Support\Settings as SiteSettings;

/**
 * Settings: the site (owners and administrators) and your own account
 * (everyone).
 */
class Settings extends Component
{
    use WithFileUploads;

    /** @var mixed a Ghost export to import */
    public $ghostFile = null;

    public string $ghostUrl = '';

    /** @var array<string, mixed>|null result of the last import */
    public ?array $importResult = null;

    /** @var array<string, array{title: string, description: string}> */
    public array $site = [];

    public string $timezone = 'UTC';

    public string $postUrl = '{slug}';

    public string $missingTranslation = 'hide';

    public bool $trackingEnabled = true;

    public string $level1 = 'always';

    public bool $privacySignals = false;

    public int $retention = 30;

    public bool $approval = false;

    public string $accent = '#C6FF3D';

    public string $name = '';

    public string $email = '';

    public string $language = 'en';

    public string $currentPassword = '';

    public string $newPassword = '';

    public function mount(Index $index, Languages $languages): void
    {
        foreach ($languages->codes() as $code) {
            $fields = $index->site($code);
            $this->site[$code] = ['title' => (string) ($fields['title'] ?? ''), 'description' => (string) ($fields['description'] ?? '')];
        }

        $this->timezone = (string) config('app.timezone');
        $this->postUrl = (string) config('wake.post_url');
        $this->missingTranslation = (string) config('wake.missing_translation');
        $this->trackingEnabled = (bool) config('wake.tracking.enabled');
        $this->level1 = (string) config('wake.tracking.level1');
        $this->privacySignals = (bool) config('wake.tracking.respect_privacy_signals');
        $this->retention = (int) config('wake.tracking.retention');
        $this->approval = (bool) config('wake.mcp.approval');
        $this->accent = (string) config('wake.panel.accent');

        $me = auth()->user();
        $this->name = $me->name();
        $this->email = $me->email();
        $this->language = $me->language();
    }

    public function saveSite(Index $index): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'site.*.title' => 'required|string|max:120',
            'site.*.description' => 'nullable|string|max:300',
            'timezone' => 'required|timezone',
            'postUrl' => ['required', 'string', 'max:60', 'regex:/\{slug\}/'],
            'missingTranslation' => 'in:hide,fallback',
            'level1' => 'in:always,consent',
            'retention' => 'integer|min:1|max:3650',
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        foreach ($this->site as $code => $values) {
            $file = Paths::content(app(Languages::class)->default() === $code && is_file(Paths::content('site.md')) ? 'site.md' : "site.{$code}.md");
            [$fields, $body] = is_file($file) ? FrontMatter::parse((string) file_get_contents($file)) : [[], ''];
            $fields = array_filter([...$fields, 'title' => $values['title'], 'description' => $values['description']], fn ($v) => $v !== '' && $v !== null);
            Files::write($file, FrontMatter::dump($fields, $body));
        }

        SiteSettings::save([
            'timezone' => $this->timezone,
            'post_url' => $this->postUrl,
            'missing_translation' => $this->missingTranslation,
            'tracking.enabled' => $this->trackingEnabled,
            'tracking.level1' => $this->level1,
            'tracking.respect_privacy_signals' => $this->privacySignals,
            'tracking.retention' => $this->retention,
            'mcp.approval' => $this->approval,
            'panel.accent' => strtoupper($this->accent),
        ]);

        app(ContentTypes::class)->flush();
        $index->refresh(force: true);
        app(PageCache::class)->flush();
        session()->flash('status', __('Settings saved.'));
        $this->redirectRoute('wake.settings');
    }

    public function saveAccount(Accounts $accounts): void
    {
        $me = auth()->user();

        $this->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:190', function ($attribute, $value, $fail) use ($accounts, $me) {
                $other = $accounts->findByEmail($value);

                if ($other && $other->id() !== $me->id()) {
                    $fail(__('There is already an account with this address.'));
                }
            }],
            'language' => 'in:en,de',
            'newPassword' => ['nullable', Password::min(10)],
        ]);

        $changes = ['name' => $this->name, 'email' => mb_strtolower($this->email), 'language' => $this->language];

        if ($this->newPassword !== '') {
            if (! Hash::check($this->currentPassword, $me->getAuthPassword())) {
                $this->addError('currentPassword', __('Your current password is not right.'));

                return;
            }

            $changes['password'] = Hash::make($this->newPassword);
        }

        $accounts->save($me->with($changes));
        session()->flash('status', __('Your account has been saved.'));
        $this->redirectRoute('wake.settings');
    }

    public function importGhost(GhostImporter $importer): void
    {
        $this->authorizeAdmin();
        $this->validate(['ghostFile' => 'required|file|max:51200', 'ghostUrl' => 'nullable|url']);

        try {
            $importer->import((string) file_get_contents($this->ghostFile->getRealPath()), $this->ghostUrl ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('ghostFile', $e->getMessage());

            return;
        }

        $this->importResult = ['counts' => $importer->counts, 'warnings' => array_slice($importer->warnings, 0, 20)];
        $this->ghostFile = null;
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->role()->atLeast(Role::Admin), 403);
    }

    public function render(Health $health, History $history): View
    {
        return view('wake::livewire.settings', [
            'isAdmin' => auth()->user()->role()->atLeast(Role::Admin),
            'problems' => $health->problems(),
            'history' => $history->latest(15),
            'timezones' => DateTimeZone::listIdentifiers(),
            'languages' => app(Languages::class),
            'warnings' => app(Index::class)->warnings(),
        ]);
    }
}
