<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Wake\Content\FrontMatter;
use Wake\Mcp\WakeServer;

// Documentation is part of every change (see CONTRIBUTING.md). These tests
// make that rule hard to forget.

function repository(string $path = ''): string
{
    return dirname(base_path()).($path === '' ? '' : '/'.$path);
}

/**
 * @return list<string> Markdown files of the documentation and the repository root
 */
function documentationFiles(): array
{
    $files = glob(repository('*.md'));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repository('docs'), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('gives every documentation page a title', function () {
    $missing = collect(documentationFiles())
        ->filter(fn (string $file) => str_starts_with($file, repository('docs/')))
        ->reject(fn (string $file) => $file === repository('docs/internal/konzept.md'))
        ->filter(fn (string $file) => empty(FrontMatter::parse(file_get_contents($file))[0]['title']))
        ->map(fn (string $file) => Str::after($file, repository().'/'))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Pages without a title in their front matter');
});

it('has no broken relative links', function () {
    $broken = [];

    foreach (documentationFiles() as $file) {
        if (basename($file) === 'template.md') {
            continue; // placeholders on purpose
        }

        $markdown = preg_replace('/```.*?```/s', '', file_get_contents($file));
        preg_match_all('/\]\(([^)\s]+)\)/', $markdown, $matches);

        foreach ($matches[1] as $link) {
            if (preg_match('#^([a-z]+:|\#|/)#i', $link)) {
                continue;
            }

            $target = dirname($file).'/'.rawurldecode(explode('#', $link)[0]);

            if (! file_exists($target)) {
                $broken[] = Str::after($file, repository().'/')." → {$link}";
            }
        }
    }

    expect($broken)->toBe([], 'Links pointing to files that do not exist');
});

it('documents every setting', function () {
    // Values that are lists or maps of their own, not groups of settings.
    $values = ['languages', 'images.widths'];

    $flatten = function (array $config, string $prefix = '') use (&$flatten, $values): array {
        $keys = [];

        foreach ($config as $key => $value) {
            $path = $prefix.$key;
            $keys = is_array($value) && ! array_is_list($value) && ! in_array($path, $values, true)
                ? [...$keys, ...$flatten($value, $path.'.')]
                : [...$keys, $path];
        }

        return $keys;
    };

    $reference = file_get_contents(repository('docs/reference/configuration.md'));
    $missing = array_values(array_filter(
        $flatten(require base_path('config/wake.php')),
        fn (string $key) => ! str_contains($reference, "`{$key}`"),
    ));

    expect($missing)->toBe([], 'Settings missing from docs/reference/configuration.md');
});

it('documents every Artisan command', function () {
    $reference = file_get_contents(repository('docs/reference/cli.md'));
    $missing = collect(array_keys(Artisan::all()))
        ->filter(fn (string $name) => str_starts_with($name, 'wake:'))
        ->reject(fn (string $name) => str_contains($reference, "`{$name}"))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Commands missing from docs/reference/cli.md');
});

it('lists every decision record in the overview', function () {
    $overview = file_get_contents(repository('docs/decisions/README.md'));
    $missing = collect(glob(repository('docs/decisions/[0-9][0-9][0-9][0-9]-*.md')))
        ->map(fn (string $file) => basename($file))
        ->reject(fn (string $name) => str_contains($overview, "({$name})"))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Decision records missing from docs/decisions/README.md');
});

it('documents every MCP tool, resource and prompt', function () {
    $reference = file_get_contents(repository('docs/reference/mcp.md'));
    $server = new ReflectionClass(WakeServer::class);
    $missing = [];

    foreach (['tools', 'resources', 'prompts'] as $kind) {
        foreach ($server->getProperty($kind)->getDefaultValue() as $class) {
            $primitive = app($class);
            $name = $kind === 'resources' ? $primitive->uri() : $primitive->name();

            if (! str_contains($reference, "`{$name}`")) {
                $missing[] = $name;
            }
        }
    }

    expect($missing)->toBe([], 'MCP primitives missing from docs/reference/mcp.md');
});
