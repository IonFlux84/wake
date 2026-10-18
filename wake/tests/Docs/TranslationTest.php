<?php

// The panel ships in English and German. Every text passed to __() or
// trans_choice() in Wake's own code needs a German translation in lang/de.json.

function translationKeys(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if ($file->getExtension() !== 'php' || ! preg_match('#/wake/(app|resources/views)/#', $path)) {
            continue;
        }

        preg_match_all('/(?:__|trans_choice)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/', file_get_contents($path), $matches);

        foreach ($matches[1] as $key) {
            $key = stripslashes($key);

            // Keys with a namespace or a dot belong to PHP translation files.
            if (! str_contains($key, '::') && ! preg_match('/^[a-z_]+\.[a-z_.]+$/', $key)) {
                $keys[$key] = true;
            }
        }
    }

    return array_keys($keys);
}

it('translates every panel text into German', function () {
    $german = json_decode(file_get_contents(base_path('lang/de.json')), true);
    $missing = array_values(array_filter(translationKeys(), fn ($key) => ! array_key_exists($key, $german)));

    sort($missing);

    expect($missing)->toBe([], 'Texts missing from wake/lang/de.json');
});

it('has no unused German translations', function () {
    $german = json_decode(file_get_contents(base_path('lang/de.json')), true);
    $unused = array_values(array_diff(array_keys($german), translationKeys()));

    expect($unused)->toBe([], 'Translations in wake/lang/de.json that no code uses');
});

it('uses no text that clashes with a translation file name', function () {
    // __('History') would return the array from lang/en/history.php on
    // case-insensitive file systems.
    $files = array_map(fn ($f) => strtolower(basename($f, '.php')), glob(base_path('lang/en/*.php')));
    $clashes = array_values(array_filter(translationKeys(), fn ($key) => in_array(strtolower($key), $files, true)));

    expect($clashes)->toBe([]);
});
