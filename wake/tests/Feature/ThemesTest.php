<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\ThemeManager;
use Wake\Support\Settings;
use Wake\Theme\Themes;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    file_put_contents($this->sandbox.'/site/themes/test/theme.yml', "name: Test theme\noptions:\n  accent:\n    type: color\n    label: Accent\n    default: '#112233'\n  wide:\n    type: toggle\n    label: Wide\n    default: false\n");
});

function themeZip(string $path, array $files): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return $path;
}

it('lists themes with their details and options', function () {
    $theme = app(Themes::class)->get('test');

    expect($theme->name())->toBe('Test theme')
        ->and($theme->templates())->toContain('home', 'post')
        ->and(theme_option('accent'))->toBe('#112233');

    $this->actingAs($this->owner)->get('/wake/themes')->assertOk()->assertSee('Test theme')->assertSee('Accent');
});

it('saves theme options', function () {
    Livewire::actingAs($this->owner)->test(ThemeManager::class)
        ->set('options.accent', '#3D5AFE')->set('options.wide', true)
        ->call('saveOptions')->assertHasNoErrors();

    expect(Settings::read()['theme_options'])->toBe(['accent' => '#3D5AFE', 'wide' => true])
        ->and(theme_option('accent'))->toBe('#3D5AFE');

    Livewire::actingAs($this->owner)->test(ThemeManager::class)->set('options.accent', 'red')->call('saveOptions')->assertHasErrors('options.accent');
});

it('duplicates, activates and removes themes', function () {
    Livewire::actingAs($this->owner)->test(ThemeManager::class)->call('duplicate', 'test');

    $copy = collect(app(Themes::class)->all())->first(fn ($t) => $t->folder !== 'test');
    expect($copy->name())->toBe('Test theme (copy)');

    Livewire::actingAs($this->owner)->test(ThemeManager::class)->call('activate', $copy->folder);
    expect(Settings::read()['theme'])->toBe($copy->folder);

    Livewire::actingAs($this->owner)->test(ThemeManager::class)->call('remove', $copy->folder);
    expect(app(Themes::class)->get($copy->folder))->not->toBeNull();

    Livewire::actingAs($this->owner)->test(ThemeManager::class)->call('activate', 'test')->call('remove', $copy->folder);
    expect(app(Themes::class)->get($copy->folder))->toBeNull();
});

it('installs a theme from a ZIP and refuses unsafe ones', function () {
    $good = themeZip($this->sandbox.'/good.zip', ['harbour/theme.yml' => "name: Harbour\n", 'harbour/templates/home.blade.php' => 'Hi', 'harbour/templates/post.blade.php' => 'Post']);
    $evil = themeZip($this->sandbox.'/evil.zip', ['../../evil/templates/home.blade.php' => 'x']);
    $empty = themeZip($this->sandbox.'/empty.zip', ['readme.txt' => 'nothing']);

    Livewire::actingAs($this->owner)->test(ThemeManager::class)
        ->set('zip', UploadedFile::fake()->createWithContent('harbour.zip', file_get_contents($good)))->assertHasNoErrors();
    expect(app(Themes::class)->get('harbour')->templates())->toContain('home', 'post');

    Livewire::actingAs($this->owner)->test(ThemeManager::class)
        ->set('zip', UploadedFile::fake()->createWithContent('evil.zip', file_get_contents($evil)))->assertHasErrors('zip');
    Livewire::actingAs($this->owner)->test(ThemeManager::class)
        ->set('zip', UploadedFile::fake()->createWithContent('empty.zip', file_get_contents($empty)))->assertHasErrors('zip');
});

it('is only for people who may manage themes', function () {
    $editor = app(Accounts::class)->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($editor)->get('/wake/themes')->assertForbidden();
});
