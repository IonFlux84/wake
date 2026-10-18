<?php

use Symfony\Component\Yaml\Yaml;
use Wake\Support\LayoutMigration;

/**
 * A small site in the layout of Wake 0.1, written into a folder of its own.
 */
function oldSite(string $root): void
{
    $files = [
        'site/config.php' => "<?php\n\nreturn ['languages' => ['en' => 'English', 'de' => 'Deutsch']];\n",
        'site/settings.yml' => "timezone: Europe/Berlin\n",
        'site/menus.yml' => "footer:\n  - page: pages/1_about/1_team\n",
        'site/redirects.yml' => "en:\n  crew: pages/1_about/1_team\n",
        'site/blueprints/event.yml' => "title: Events\nfolder: events\nfields:\n  start: { type: datetime }\n",
        'site/categories/termine.yml' => "title: { en: Events, de: Termine }\nslug: { en: events, de: termine }\nicon: calendar\n",
        'site/themes/mine/theme.yml' => "name: Mine\n",
        'storage/accounts/ola/account.yml' => "name: Ola\n",
        'storage/revisions/'.md5('pages/1_about/1_team/index.en.md').'/1.md' => "old\n",
        'storage/mail.yml' => "host: smtp.example.org\n",
        'storage/cache/index.php' => "<?php return [];\n",
        'content/site.en.md' => "---\ntitle: Old site\n---\n",
        'content/site.de.md' => "---\ntitle: Alte Seite\n---\n",
        'content/pages/home/index.en.md' => "---\ntitle: Home\n---\n",
        'content/pages/1_about/index.en.md' => "---\ntitle: About\n---\n",
        'content/pages/1_about/index.de.md' => "---\ntitle: Über uns\nslug: ueber-uns\n---\n",
        'content/pages/1_about/1_team/index.en.md' => "---\ntitle: Team\n---\n",
        'content/pages/imprint/index.en.md' => "---\ntitle: Imprint\n---\n",
        'content/posts/2026-10-17-harbour/index.en.md' => "---\ntitle: Harbour\ncover: boat.jpg\n---\n\n![](boat.jpg)\n",
        'content/posts/2026-10-17-harbour/boat.jpg' => 'jpeg',
        'content/posts/2026-10-17-harbour/boat.jpg.yml' => "alt: A boat\n",
        'content/tags/travel.en.md' => "---\ntitle: Travel\n---\n",
        'content/events/2026-05-01-fair/index.en.md' => "---\ntitle: Fair\nstart: 2026-05-01 10:00\n---\n",
    ];

    foreach ($files as $path => $contents) {
        @mkdir(dirname($root.'/'.$path), 0775, true);
        file_put_contents($root.'/'.$path, $contents);
    }
}

beforeEach(function () {
    $this->root = $this->sandbox.'/old-site';
    oldSite($this->root);
});

it('lists the steps without moving anything', function () {
    $steps = (new LayoutMigration($this->root))->run(false);

    expect($steps)->not->toBeEmpty()
        ->and(is_file($this->root.'/content/pages/1_about/index.en.md'))->toBeTrue()
        ->and(is_dir($this->root.'/settings'))->toBeFalse();
});

it('moves a site from the old layout to one folder per entry', function () {
    (new LayoutMigration($this->root))->run(true);
    $r = $this->root;

    expect(is_dir("{$r}/site"))->toBeFalse()
        ->and(is_dir("{$r}/storage"))->toBeFalse()
        ->and(is_dir("{$r}/content/pages/1_about"))->toBeFalse()
        ->and(is_file("{$r}/settings/config.php"))->toBeTrue()
        ->and(is_file("{$r}/settings/general.yml"))->toBeTrue()
        ->and(is_file("{$r}/themes/mine/theme.yml"))->toBeTrue()
        ->and(is_file("{$r}/data/accounts/ola/account.yml"))->toBeTrue()
        ->and(is_file("{$r}/data/secrets/mail.yml"))->toBeTrue()
        ->and(is_file("{$r}/data/versions/".md5('pages/about/team/content.md').'/1.md'))->toBeTrue()
        ->and(is_file("{$r}/content/website.md"))->toBeTrue()
        ->and(is_file("{$r}/content/website.de.md"))->toBeTrue()
        ->and(is_file("{$r}/content/pages/about/content.de.md"))->toBeTrue()
        ->and(is_file("{$r}/content/pages/imprint/content.md"))->toBeTrue()
        ->and(is_file("{$r}/content/tags/travel/content.md"))->toBeTrue()
        ->and(is_file("{$r}/content/events/fair/content.md"))->toBeTrue()
        ->and(is_file("{$r}/content/events/type.yml"))->toBeTrue()
        ->and(is_file("{$r}/content/categories/termine/content.md"))->toBeTrue()
        ->and(is_file("{$r}/content/categories/termine/content.de.md"))->toBeTrue()
        ->and(is_file("{$r}/content/media/posts/harbour/boat.jpg"))->toBeTrue()
        ->and(is_file("{$r}/content/media/posts/harbour/boat.jpg.yml"))->toBeTrue();

    $team = file_get_contents("{$r}/content/pages/about/team/content.md");
    $about = file_get_contents("{$r}/content/pages/about/content.md");
    $imprint = file_get_contents("{$r}/content/pages/imprint/content.md");
    $post = file_get_contents("{$r}/content/posts/harbour/content.md");

    expect($about)->toContain('order: 1')->toContain('menu: true')
        ->and($team)->toContain('menu: true')
        ->and($imprint)->not->toContain('menu:')
        ->and($post)->toContain('published: 2026-10-17')
        ->and($post)->toContain('cover: media/posts/harbour/boat.jpg')
        ->and($post)->toContain('![](media/posts/harbour/boat.jpg)')
        ->and(Yaml::parseFile("{$r}/settings/menus.yml")['footer'][0]['page'])->toBe('pages/about/team')
        ->and(Yaml::parseFile("{$r}/settings/redirects.yml")['en']['crew'])->toBe('pages/about/team')
        ->and(file_get_contents("{$r}/content/categories/termine/content.de.md"))->toContain('Termine');
});

it('does nothing on a site that is already moved', function () {
    $migration = new LayoutMigration($this->root);
    $migration->run(true);

    expect($migration->run(false))->toBe([])
        ->and($migration->needed())->toBeFalse();
});

it('zips the old folders before moving them', function () {
    $migration = new LayoutMigration($this->root);
    $zip = $migration->backup();
    $migration->run(true);

    $archive = new ZipArchive;
    $archive->open(str_replace('/storage/backups/', '/data/backups/', $zip));

    expect($archive->locateName('content/pages/1_about/index.en.md'))->not->toBeFalse()
        ->and($archive->locateName('site/config.php'))->not->toBeFalse()
        ->and($archive->locateName('storage/accounts/ola/account.yml'))->not->toBeFalse()
        ->and($archive->locateName('storage/cache/index.php'))->toBeFalse();
});
