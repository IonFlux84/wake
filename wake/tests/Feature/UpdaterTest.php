<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\Settings;
use Wake\Update\Updater;
use Wake\Wake;

/**
 * A fake installation in the sandbox and a fake release ZIP, so the real
 * wake/ folder is never touched.
 */
function fakeRelease(string $sandbox, string $version, bool $complete = true): string
{
    $build = $sandbox.'/build/wake-'.$version;
    @mkdir($build.'/wake/bootstrap', 0775, true);
    @mkdir($build.'/wake/vendor', 0775, true);
    @mkdir($build.'/public', 0775, true);
    file_put_contents($build.'/wake/bootstrap/app.php', '<?php // new '.$version);
    if ($complete) {
        file_put_contents($build.'/wake/vendor/autoload.php', '<?php');
    }
    file_put_contents($build.'/public/index.php', '<?php // new index');

    $zip = new ZipArchive;
    $zip->open($sandbox.'/release.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox.'/build', FilesystemIterator::SKIP_DOTS)) as $file) {
        $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($sandbox.'/build/')));
    }
    $zip->close();

    return file_get_contents($sandbox.'/release.zip');
}

beforeEach(function () {
    $this->site = $this->sandbox.'/site-root';
    @mkdir($this->site.'/wake/bootstrap', 0775, true);
    @mkdir($this->site.'/public', 0775, true);
    file_put_contents($this->site.'/wake/bootstrap/app.php', '<?php // old');
    file_put_contents($this->site.'/public/index.php', '<?php // old index');
    $this->updater = app(Updater::class);
    $this->updater->root = $this->site;
});

function fakeGitHub(string $zip, ?string $checksum = null, string $version = '9.0.0'): void
{
    Http::fake([
        'api.github.com/*' => Http::response(['tag_name' => 'v'.$version, 'body' => 'Notes', 'assets' => [
            ['name' => "wake-{$version}.zip", 'browser_download_url' => 'https://github.example/wake.zip'],
            ['name' => "wake-{$version}.zip.sha256", 'browser_download_url' => 'https://github.example/wake.zip.sha256'],
        ]]),
        'github.example/wake.zip' => Http::response($zip),
        'github.example/wake.zip.sha256' => Http::response(($checksum ?? hash('sha256', $zip))."  wake-{$version}.zip\n"),
    ]);
}

it('finds a newer release', function () {
    fakeGitHub('zip');

    expect($this->updater->check())->toMatchArray(['version' => '9.0.0', 'notes' => 'Notes']);
});

it('ignores releases that are not newer', function () {
    fakeGitHub('zip', version: '0.0.1');

    expect($this->updater->check())->toBeNull();
});

it('installs a release and keeps a backup', function () {
    fakeGitHub(fakeRelease($this->sandbox, '9.0.0'));

    $backup = $this->updater->install($this->updater->check());

    expect(file_get_contents($this->site.'/wake/bootstrap/app.php'))->toContain('new 9.0.0')
        ->and(file_get_contents($this->site.'/public/index.php'))->toContain('new index')
        ->and(file_get_contents($backup.'/wake/bootstrap/app.php'))->toContain('old')
        ->and(file_get_contents($backup.'/public__index.php'))->toContain('old index');
});

it('changes nothing when the checksum does not match', function () {
    fakeGitHub(fakeRelease($this->sandbox, '9.0.0'), str_repeat('0', 64));

    expect(fn () => $this->updater->install($this->updater->check()))->toThrow(RuntimeException::class, 'checksum');
    expect(file_get_contents($this->site.'/wake/bootstrap/app.php'))->toContain('old');
});

it('changes nothing when the release is incomplete', function () {
    fakeGitHub(fakeRelease($this->sandbox, '9.0.0', complete: false));

    expect(fn () => $this->updater->install($this->updater->check()))->toThrow(RuntimeException::class, 'incomplete');
    expect(file_get_contents($this->site.'/wake/bootstrap/app.php'))->toContain('old');
});

it('lets only the owner install from the panel', function () {
    $admin = app(Accounts::class)->create(['name' => 'Ad', 'email' => 'ad@example.com', 'password' => 'secret secret', 'role' => Role::Admin]);
    fakeGitHub('zip');

    Livewire::actingAs($admin)->test(Settings::class)
        ->call('checkForUpdates')->assertSet('release.version', '9.0.0')
        ->assertSee('Only the owner can install updates.');
    Livewire::actingAs($admin)->test(Settings::class)->call('installUpdate')->assertForbidden();

    expect(Wake::VERSION)->toBeString();
});
