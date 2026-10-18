<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Backup\Backups;
use Wake\Content\Models\Post;
use Wake\Content\Writer;
use Wake\Livewire\BackupManager;
use Wake\Support\Files;
use Wake\Support\Paths;

function zipEntries(string $file): array
{
    $zip = new ZipArchive;
    $zip->open($file);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

it('writes content, settings and accounts into a ZIP', function () {
    app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    Files::write(Paths::data('stats/2026-10.log'), "x\n");
    Files::write(storage_path('cache/junk.txt'), 'cache');

    $backups = app(Backups::class);
    $entries = zipEntries($backups->path($backups->create()));

    expect($entries)->toContain('wake-backup.json', 'content/posts/2026-01-10-first/index.en.md', 'content/posts/2026-01-10-first/photo.png')
        ->and(collect($entries)->contains(fn ($e) => str_starts_with($e, 'settings/')))->toBeTrue()
        ->and(collect($entries)->contains(fn ($e) => str_starts_with($e, 'themes/')))->toBeTrue()
        ->and(collect($entries)->contains(fn ($e) => str_starts_with($e, 'data/accounts/')))->toBeTrue()
        ->and(collect($entries)->contains(fn ($e) => str_starts_with($e, 'data/stats/') || str_starts_with($e, 'temp/cache/')))->toBeFalse()
        ->and(zipEntries($backups->path($backups->create(withStats: true))))->toContain('data/stats/2026-10.log')
        ->and($backups->all())->toHaveCount(2);
});

it('restores a backup and keeps the replaced state', function () {
    $backups = app(Backups::class);
    $name = $backups->create();

    app(Writer::class)->update('posts/2026-01-10-first', 'en', ['title' => 'Changed after the backup']);
    app(Writer::class)->create('post', ['title' => 'Written later']);

    $safety = $backups->restore($backups->path($name));

    expect(Post::find('posts/2026-01-10-first')->title())->toBe('First post')
        ->and(Post::query()->get()->map->title()->all())->not->toContain('Written later')
        ->and($backups->path($safety))->not->toBeNull()
        ->and(glob(Paths::data('trash/*-before-restore/content/posts')))->toHaveCount(1);
});

it('refuses ZIP files that are not Wake backups or contain unsafe paths', function (array $files) {
    $file = storage_path('evil.zip');
    $zip = new ZipArchive;
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $path => $contents) {
        $zip->addFromString($path, $contents);
    }
    $zip->close();

    app(Backups::class)->restore($file);
})->with([
    'no manifest' => [['content/x.md' => 'x']],
    'path escape' => [['wake-backup.json' => '{"format":1}', 'content/../../evil.php' => '<?php']],
    'unknown folder' => [['wake-backup.json' => '{"format":1}', 'public/evil.php' => '<?php']],
    'env file' => [['wake-backup.json' => '{"format":1}', '.env' => 'APP_KEY=x']],
])->throws(InvalidArgumentException::class);

it('only accepts plain backup names', function () {
    expect(app(Backups::class)->path('../.env'))->toBeNull()
        ->and(app(Backups::class)->path('nope.zip'))->toBeNull();
});

it('manages backups in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    $this->actingAs($owner)->get('/wake/backups')->assertOk()->assertSee('No backup yet.');

    Livewire::actingAs($owner)->test(BackupManager::class)->call('create');
    $name = app(Backups::class)->all()[0]['name'];

    $this->actingAs($owner)->get('/wake/backups/'.$name)->assertOk()->assertDownload($name);

    $upload = UploadedFile::fake()->createWithContent('site.zip', file_get_contents(app(Backups::class)->path($name)));
    Livewire::actingAs($owner)->test(BackupManager::class)->set('zip', $upload)->assertHasNoErrors();

    expect(app(Backups::class)->all())->toHaveCount(2);

    Livewire::actingAs($owner)->test(BackupManager::class)->call('delete', $name);

    expect(app(Backups::class)->all())->toHaveCount(1);
});

it('keeps backups away from editors', function () {
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);
    $name = app(Backups::class)->create();

    $this->actingAs($editor)->get('/wake/backups')->assertForbidden();
    $this->actingAs($editor)->get('/wake/backups/'.$name)->assertForbidden();
});

it('writes and restores backups from the command line', function () {
    $this->artisan('wake:backup')->assertSuccessful();
    $name = app(Backups::class)->all()[0]['name'];

    app(Writer::class)->update('posts/2026-01-10-first', 'en', ['title' => 'Changed']);
    $this->artisan('wake:restore', ['file' => $name, '--force' => true])->assertSuccessful();

    expect(Post::find('posts/2026-01-10-first')->title())->toBe('First post');
});
