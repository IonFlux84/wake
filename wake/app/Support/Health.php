<?php

namespace Wake\Support;

/**
 * Checks the installation for problems a site owner must fix, shown during
 * setup and in the settings.
 */
class Health
{
    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (['storage' => storage_path(), 'content' => Paths::content(), 'public/media' => public_path('media')] as $name => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (! is_writable($path)) {
                $problems[] = __('Wake cannot write to the folder :folder. Please allow write access (e.g. 775).', ['folder' => $name]);
            }
        }

        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

        if ($documentRoot !== false && $documentRoot !== '' && $documentRoot !== realpath(public_path())
            && ! app()->runningInConsole()) {
            $problems[] = __('The web server serves the folder :root. Point the document root to the public folder, otherwise your content and statistics may be reachable from outside.', ['root' => $documentRoot]);
        }

        return $problems;
    }
}
