<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Wake\Accounts\Permissions;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Http\Controllers\SiteController;
use Wake\Localization\Languages;

/**
 * See an entry on the website before it is published: for the team, and
 * through a signed link for anyone the link is shared with.
 */
class PreviewController
{
    public const DAYS = 7;

    public function show(Request $request): Response
    {
        $entry = $this->entry($request);
        abort_unless(Permissions::mayEdit($request->user(), $entry) || $entry->isPublished(), 403);

        return $this->render($entry, __('Preview'), null);
    }

    public function shared(Request $request): Response
    {
        return $this->render($this->entry($request), __('Preview'), (int) $request->query('expires'));
    }

    /**
     * A link that shows the preview to anyone for a week, without an account.
     */
    public static function sharedUrl(Entry $entry): string
    {
        return URL::temporarySignedRoute('wake.preview.shared', now()->addDays(self::DAYS), [
            'id' => $entry->id(), 'language' => $entry->language(),
        ]);
    }

    protected function entry(Request $request): Entry
    {
        $row = app(Index::class)->find((string) $request->query('id'));
        $language = (string) $request->query('language', app(Languages::class)->default());

        abort_if($row === null || ! isset($row['languages'][$language]), 404);

        return Entry::make($row, $language);
    }

    protected function render(Entry $entry, string $label, ?int $expires): Response
    {
        $response = app(SiteController::class)->renderEntry($entry);

        $note = $expires ? ' · '.__('link valid until :date', ['date' => now()->setTimestamp($expires)->locale(app()->getLocale())->isoFormat('LL')]) : '';
        $status = $entry->isPublished() ? __('Published') : ($entry->isScheduled() ? __('Scheduled') : __('Draft'));
        $banner = '<div style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483001;padding:10px 16px;border-radius:999px;background:#0a0a0a;color:#fff;font:600 14px system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.3)">'
            .'<span style="color:#c6ff3d">●</span> '.e($label).' · '.e($status).e($note).'</div>';

        $html = (string) $response->getContent();
        $html = str_replace('</body>', $banner.'</body>', $html);
        $html = preg_replace('/<head>/', "<head>\n    <meta name=\"robots\" content=\"noindex, nofollow\">", $html, 1);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
