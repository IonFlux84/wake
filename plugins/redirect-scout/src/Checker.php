<?php

namespace RedirectScout;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Wake\Content\Redirects;
use Wake\Http\Controllers\SiteController;
use Wake\Localization\Languages;
use Wake\Routing\Resolver;

/**
 * Asks this site what it answers for an old path, the same way a visitor
 * would get it: an existing page or file, a redirect, 410 Gone, or nothing.
 */
class Checker
{
    public function __construct(protected Resolver $resolver, protected Languages $languages, protected Redirects $redirects) {}

    /**
     * @return array{state: string, target: ?string}
     */
    public function check(string $path): array
    {
        [$language, $rest] = $this->resolver->split($path);
        $target = $this->redirects->target($language, $rest);

        if ($target !== null) {
            return ['state' => $target === Redirects::GONE ? 'gone' : 'redirect', 'target' => $target];
        }

        if ($path !== '' && is_file(public_path($path))) {
            return ['state' => 'exists', 'target' => null];
        }

        try {
            $route = Route::getRoutes()->match(Request::create('/'.implode('/', array_map('rawurlencode', explode('/', $path)))));
        } catch (Throwable) {
            return ['state' => 'missing', 'target' => null];
        }

        if ($route->getActionName() !== SiteController::class.'@show') {
            return ['state' => 'exists', 'target' => null];
        }

        return ['state' => $this->render($path), 'target' => null];
    }

    /**
     * Renders the path like the website does and reads the status.
     */
    protected function render(string $path): string
    {
        $language = $this->languages->current();
        $locale = app()->getLocale();

        try {
            $status = app(SiteController::class)->show($path)->getStatusCode();
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        } catch (Throwable) {
            $status = 500;
        } finally {
            $this->languages->setCurrent($language);
            app()->setLocale($locale);
        }

        return match (true) {
            $status < 300 => 'exists',
            $status < 400 => 'redirect',
            $status === 410 => 'gone',
            $status === 404 => 'missing',
            default => 'error',
        };
    }
}
