<?php

namespace Wake\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wake\Content\Index;
use Wake\Media\Images;
use Wake\Media\Media;
use Wake\Support\Files;

/**
 * Serves /media/{hash}/{version}/{variant}/{name}. On the first request the
 * file is copied or resized into public/media under the same path, so the
 * web server delivers it directly from then on.
 */
class MediaController
{
    public function __invoke(Index $index, string $hash, string $version, string $variant, string $name): BinaryFileResponse
    {
        $path = $index->mediaByHash($hash);
        $file = $path !== null ? Media::find($path) : null;
        $format = '';

        // photo.jpg.webp is photo.jpg converted to WebP.
        if ($file !== null && $name !== $file->name() && preg_match('/^(.+)\.(webp|avif)$/', $name, $m) && $m[1] === $file->name()) {
            $format = $m[2];
        }

        abort_if($file === null || ($name !== $file->name() && $format === ''), 404);
        abort_if(base_convert((string) $file->modifiedAt(), 10, 36) !== $version, 404);
        abort_unless(preg_match('/^(o|\d+)$/', $variant), 404);

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
