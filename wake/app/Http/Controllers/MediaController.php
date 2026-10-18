<?php

namespace Wake\Http\Controllers;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wake\Content\Index;
use Wake\Media\Images;
use Wake\Media\Media;
use Wake\Media\MediaAccess;
use Wake\Support\Files;

/**
 * Serves /media/{hash}/{version}/{variant}/{name}. On the first request a
 * public file is copied or resized into public/media under the same path, so
 * the web server delivers it directly from then on.
 *
 * Files that only drafts use are private (MediaAccess): they are made in
 * temp/media instead and served only to team members who may read such a
 * draft, without being cached anywhere.
 */
class MediaController
{
    public function __invoke(Request $request, Index $index, string $hash, string $version, string $variant, string $name): BinaryFileResponse
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
        $public = MediaAccess::isPublic($path);

        abort_unless($public || MediaAccess::mayView($this->viewer($request), $path), 404);

        $base = $public ? public_path('media') : storage_path('media');

        if ($width === null && $format === '') {
            $target = "{$base}/{$hash}/{$version}/o/{$name}";

            if (! is_file($target)) {
                Files::copy($file->path(), $target);
            }
        } else {
            abort_unless($file->isImage(), 404);
            abort_if($width !== null && ! in_array($width, Images::widths(), true), 404);
            abort_if($format !== '' && (! in_array($format, Images::FORMATS, true) || ! Images::supports($format)), 404);

            $target = "{$base}/{$hash}/{$version}/{$variant}/{$name}";

            if (! is_file($target)) {
                Images::resize($file->path(), $target, $width, $format ?: $file->extension());
            }
        }

        $type = $format !== '' ? 'image/'.($format === 'jpg' ? 'jpeg' : $format) : null;

        $response = response()->file($target, array_filter(['Content-Type' => $type]));

        if ($public) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }

    /**
     * The website runs without sessions; for a private file the session is
     * started to see who is asking, like on a closed site.
     */
    protected function viewer(Request $request): mixed
    {
        if ($request->user()) {
            return $request->user();
        }

        $user = null;

        (new Pipeline(app()))
            ->send($request)
            ->through([EncryptCookies::class, StartSession::class])
            ->then(function (Request $request) use (&$user) {
                $user = $request->user();

                return response()->noContent();
            });

        return $user;
    }
}
