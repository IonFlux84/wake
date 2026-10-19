<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;

/**
 * Every test gets its own copy of the fixture content and its own storage
 * folder, so tests may write files without affecting each other or the
 * demo site.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $sandbox;

    public function createApplication(): Application
    {
        $this->sandbox = sys_get_temp_dir().'/wake-tests/'.bin2hex(random_bytes(6));

        foreach (['content', 'settings', 'themes', 'plugins'] as $folder) {
            $this->copyDirectory(__DIR__.'/Fixtures/'.$folder, $this->sandbox.'/'.$folder);
        }

        // The plugins that ship with Wake run in every test, next to the
        // fixture plugin, also those that start switched off.
        $bundled = [];

        foreach (glob(dirname(__DIR__, 2).'/plugins/*/plugin.php') ?: [] as $plugin) {
            $this->copyDirectory(dirname($plugin), $this->sandbox.'/plugins/'.($bundled[] = basename(dirname($plugin))));
        }

        file_put_contents($this->sandbox.'/settings/general.yml', Yaml::dump(['plugins' => ['enabled' => $bundled]]));
        mkdir($this->sandbox.'/temp/framework/views', 0775, true);
        mkdir($this->sandbox.'/data', 0775, true);
        mkdir($this->sandbox.'/public', 0775, true);

        $env = ['WAKE_CONTENT_PATH' => 'content', 'WAKE_SETTINGS_PATH' => 'settings', 'WAKE_THEMES_PATH' => 'themes', 'WAKE_PLUGINS_PATH' => 'plugins', 'WAKE_DATA_PATH' => 'data'];

        foreach ([...array_map(fn ($folder) => $this->sandbox.'/'.$folder, $env), 'WAKE_PAGE_CACHE' => 'true'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        $app = require __DIR__.'/../bootstrap/app.php';
        $app->useStoragePath($this->sandbox.'/temp');
        $app->usePublicPath($this->sandbox.'/public');
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('view.compiled', $this->sandbox.'/temp/framework/views');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');

        // Maintenance after requests would publish the due fixture post and
        // clear caches in the middle of unrelated tests.
        config(['wake.maintenance.mode' => 'cron']);

        // Tests read the panel in English; German is checked where it matters.
        config(['wake.panel.language' => 'en']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Files::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    protected function content(string $path = ''): string
    {
        return $this->sandbox.'/content'.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * Write a content file, making sure its modification time differs from
     * the previous version so the index notices the change.
     */
    protected function writeContent(string $path, string $contents): void
    {
        $file = $this->content($path);
        $before = is_file($file) ? filemtime($file) : 0;

        Files::write($file, $contents);
        touch($file, max(time(), $before + 1));
    }

    protected function copyDirectory(string $from, string $to): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        @mkdir($to, 0775, true);

        foreach ($items as $item) {
            $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);
            $item->isDir() ? @mkdir($target, 0775, true) : copy($item->getPathname(), $target);
        }
    }
}
