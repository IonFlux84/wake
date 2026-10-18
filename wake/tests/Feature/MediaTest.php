<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Symfony\Component\Yaml\Yaml;
use Wake\Accounts\Accounts;
use Wake\Accounts\Permissions;
use Wake\Accounts\Role;
use Wake\Content\Models\Post;
use Wake\Content\Writer;
use Wake\Livewire\MediaLibrary;
use Wake\Support\Files;
use Wake\Support\Paths;

it('serves the original and stores a public copy', function () {
    $url = Post::find('posts/first')->file('photo.png')->url();

    $this->get(parse_url($url, PHP_URL_PATH))->assertOk();

    expect(is_file(public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'))))->toBeTrue();
});

it('creates resized versions and WebP', function () {
    $photo = Post::find('posts/first')->file('photo.png');

    $this->get(parse_url($photo->url(320), PHP_URL_PATH))->assertOk();
    $this->get(parse_url($photo->url(320, 'webp'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/webp');

    $path = public_path(ltrim(parse_url($photo->url(320), PHP_URL_PATH), '/'));

    expect(getimagesize($path)[0])->toBe(320)
        ->and($photo->url(300))->toBe($photo->url(320));
});

it('refuses unknown files, sizes and stale versions', function () {
    $photo = Post::find('posts/first')->file('photo.png');
    $path = parse_url($photo->url(), PHP_URL_PATH);

    $this->get(str_replace('photo.png', 'other.png', $path))->assertNotFound();
    $this->get(str_replace('/o/', '/333/', $path))->assertNotFound();
    $this->get(preg_replace('#/[a-z0-9]+/o/#', '/zzz/o/', $path))->assertNotFound();
});

it('builds srcset and picture markup', function () {
    $photo = Post::find('posts/first')->image();

    expect($photo->width())->toBe(800)
        ->and($photo->srcset())->toContain('320w')->toContain('640w')->not->toContain('960w')
        ->and((string) $photo->picture('Alt'))->toContain('<source type="image/webp"')->toContain('alt="Alt"');
});

it('keeps details of a file in a sidecar file', function () {
    app(Writer::class)->saveMediaMeta('media/posts/first/photo.png', [
        'alt' => ['en' => 'A harbour at dusk', 'de' => 'Ein Hafen in der Dämmerung'],
        'caption' => ['en' => '', 'de' => ''],
        'credit' => 'Photo: Jane Doe',
        'focus' => '30% 60%',
    ]);

    $photo = Post::find('posts/first')->file('photo.png');
    $sidecar = Paths::content('media/posts/first/photo.png.yml');

    expect(is_file($sidecar))->toBeTrue()
        ->and(Yaml::parseFile($sidecar))->not->toHaveKey('caption')
        ->and($photo->alt('en'))->toBe('A harbour at dusk')
        ->and($photo->alt('de'))->toBe('Ein Hafen in der Dämmerung')
        ->and($photo->credit())->toBe('Photo: Jane Doe')
        ->and($photo->focus())->toBe('30% 60%')
        ->and((string) $photo->img())->toContain('alt="A harbour at dusk"')->toContain('object-position:30% 60%')
        ->and(Post::find('posts/first')->file('photo.png.yml'))->toBeNull();

    app(Writer::class)->saveMediaMeta('media/posts/first/photo.png', ['alt' => '', 'focus' => '']);

    expect(is_file($sidecar))->toBeFalse()
        ->and(Post::find('posts/first')->file('photo.png')->focus())->toBe('50% 50%');
});

it('ignores an invalid focus point', function () {
    Files::write(Paths::content('media/posts/first/photo.png.yml'), "focus: 'top left'\nalt: Boats\n");

    $photo = Post::find('posts/first')->file('photo.png');

    expect($photo->focus())->toBe('50% 50%')
        ->and($photo->alt('de'))->toBe('Boats')
        ->and((string) $photo->img())->not->toContain('object-position');
});

it('fills empty alt texts in Markdown from the file details', function () {
    $writer = app(Writer::class);
    $writer->saveMediaMeta('media/posts/first/photo.png', ['alt' => 'Boats in the harbour']);
    $writer->update('posts/first', 'en', body: "![](photo.png)\n\n![Own words](photo.png)");

    $html = (string) Post::find('posts/first')->content();

    expect($html)->toContain('alt="Boats in the harbour"')->toContain('alt="Own words"')->toContain('/1280/photo.png');
});

it('moves the sidecar file to the trash with its file', function () {
    $writer = app(Writer::class);
    $writer->saveMediaMeta('media/posts/first/photo.png', ['credit' => 'Jane']);
    $writer->deleteMedia('media/posts/first/photo.png');

    expect(is_file(Paths::content('media/posts/first/photo.png.yml')))->toBeFalse()
        ->and(glob(Paths::data('trash/*photo.png.yml')))->toHaveCount(1);
});

it('edits file details in the media library', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->withQueryParams(['file' => 'posts/first/photo.png'])->test(MediaLibrary::class)
        ->assertSee('Alt text')
        ->set('alt.en', 'Two boats')
        ->call('setFocus', 20, 80)
        ->call('saveDetails')
        ->assertHasNoErrors();

    $photo = Post::find('posts/first')->file('photo.png');

    expect($photo->alt('en'))->toBe('Two boats')->and($photo->focus())->toBe('20% 80%');
});

it('lets only people who may edit the entry change its file details', function () {
    $contributor = app(Accounts::class)->create(['name' => 'Cleo', 'email' => 'cleo@example.com', 'password' => 'secret secret', 'role' => Role::Contributor]);

    Livewire::actingAs($contributor)->withQueryParams(['file' => 'posts/first/photo.png'])->test(MediaLibrary::class)
        ->set('alt.en', 'Mine now')
        ->call('saveDetails')
        ->assertForbidden();
});

it('shows where a file is used and lists unused files', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    Files::copy(Paths::content('media/posts/first/photo.png'), Paths::content('media/documents/spare.png'));

    Livewire::actingAs($owner)->withQueryParams(['file' => 'posts/first/photo.png'])->test(MediaLibrary::class)
        ->assertSee('Used in')->assertSee('First post')
        ->set('unused', true)
        ->assertSee('spare.png')->assertDontSee('used 1 time');
});

it('uploads into a folder of the media library and renames taken names', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    foreach ([1, 2] as $i) {
        Livewire::actingAs($owner)->test(MediaLibrary::class)
            ->set('uploadFolder', 'documents')
            ->set('upload', UploadedFile::fake()->image('Summer Fest.png'))
            ->call('uploadFile')
            ->assertHasNoErrors();
    }

    expect(is_file(Paths::content('media/documents/summer-fest.png')))->toBeTrue()
        ->and(is_file(Paths::content('media/documents/summer-fest-2.png')))->toBeTrue();

    Livewire::actingAs($owner)->test(MediaLibrary::class)
        ->set('uploadFolder', '../outside')
        ->set('upload', UploadedFile::fake()->image('x.png'))
        ->call('uploadFile')
        ->assertHasErrors('uploadFolder');
});

it('lets only people who may delete everything delete used files', function () {
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    expect(Permissions::mayEditMedia($editor, 'posts/first/photo.png'))->toBeTrue()
        ->and(Permissions::mayDeleteMedia($editor, 'posts/first/photo.png'))->toBe($editor->can('content.delete_all'));

    Files::copy(Paths::content('media/posts/first/photo.png'), Paths::content('media/documents/spare.png'));

    expect(Permissions::mayDeleteMedia($editor, 'documents/spare.png'))->toBeTrue();
});
