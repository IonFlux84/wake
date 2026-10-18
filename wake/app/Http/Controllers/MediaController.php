<?php

namespace Wake\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Localization\Languages;
use Wake\Media\Images;
use Wake\Support\Files;

/**
 * Serves /media/{hash}/{version}/{variant}/{name}. On the first request the
 * file is copied or resized into public/media under the same path, so the
 * web server delivers it directly from then on.
 */
class MediaController
{
    public function __invoke(Index $index, Languages $languages, string $hash, string $version, string $variant, string $name): BinaryFileResponse
    {
        $row = $index->findByHash($hash);
        $source = $name;
        $format = '';

        // photo.jpg.webp is photo.jpg converted to WebP.
        if (! isset($row['media'][$name]) && preg_match('/^(.+)\.(webp|avif)$/', $name, $m)) {
            [$source, $format] = [$m[1], $m[2]];
        }

        $mtime = $row['media'][$source] ?? null;

        abort_if($mtime === null || base_convert((string) $mtime, 10, 36) !== $version, 404);
        abort_unless(preg_match('/^(o|\d+)$/', $variant), 404);

        $file = Entry::make($row, $languages->default())->file($source);
        $width = $variant === 'o' ? null : (int) $variant;

        if ($width === null && $format === '') {
            $target = public_path("media/{$hash}/{$version}/o/{$name}");

            if (! is_file($target)) {
                Files::copy($file->path(), $target);
            }
        } else {
            abort_unless($file->isImage(), 404);
            abort_if($width !== null && ! in_array($width, Images::widths(), true), 404);
            abort_if($format !== '' && (! in_array($format, Images::FORMATS, true) || ! Images::supports($format)), 404);

            $target = public_path("media/{$hash}/{$version}/{$variant}/{$name}");

            if (! is_file($target)) {
                Images::resize($file->path(), $target, $width, $format ?: $file->extension());
            }
        }

        $type = $format !== '' ? 'image/'.($format === 'jpg' ? 'jpeg' : $format) : null;

        return response()->file($target, array_filter([
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]));
    }
}
