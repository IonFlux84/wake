<?php

use Illuminate\Support\Carbon;
use Wake\Maintenance\Maintenance;
use Wake\Stats\Stats;
use Wake\Stats\TrackingConfig;
use Wake\Support\Paths;

/**
 * Write raw hits the way public/hit.php does.
 *
 * @param  list<array<string, mixed>>  $hits
 */
function rawHits(string $day, array $hits): void
{
    $lines = array_map(function (array $h) use ($day) {
        $h = array_replace(['time' => strtotime($day.' 10:00'), 'type' => 'pageview', 'visitor' => 'v1', 'returning' => '', 'path' => '/first',
            'referrer' => '', 'source' => '', 'medium' => '', 'campaign' => '', 'language' => 'en', 'country' => 'DE',
            'device' => 'desktop', 'browser' => 'Firefox', 'os' => 'Linux', 'event' => '', 'label' => '', 'duration' => 0, 'scroll' => 0, 'level' => 1], $h);

        return implode("\t", $h);
    }, $hits);

    @mkdir(Paths::data('stats/raw'), 0775, true);
    file_put_contents(Paths::data("stats/raw/{$day}.log"), implode("\n", $lines)."\n", FILE_APPEND);
}

it('summarises a day', function () {
    rawHits('2026-05-30', [
        ['visitor' => 'a', 'referrer' => 'social.example', 'source' => 'mastodon', 'campaign' => 'launch'],
        ['visitor' => 'a', 'path' => '/about'],
        ['visitor' => 'a', 'type' => 'leave', 'path' => '/about', 'duration' => 30, 'scroll' => 95],
        ['visitor' => 'b', 'device' => 'mobile', 'country' => 'AT', 'level' => 2, 'returning' => 'returning'],
        ['visitor' => 'b', 'type' => 'leave', 'duration' => 10, 'scroll' => 40],
        ['visitor' => 'b', 'type' => 'event', 'event' => 'Download', 'label' => 'flyer.pdf'],
    ]);

    $report = app(Stats::class)->report('2026-05-30', '2026-05-30');

    expect($report->pageviews())->toBe(3)
        ->and($report->visitors())->toBe(2)
        ->and($report->averageDuration())->toBe(20)
        ->and($report->consentShare())->toBe(0.5)
        ->and($report->returning())->toBe(1)
        ->and($report->top('referrers'))->toBe(['social.example' => 1, '' => 1])
        ->and($report->top('campaigns'))->toBe(['mastodon / launch' => 1])
        ->and($report->top('devices'))->toBe(['desktop' => 1, 'mobile' => 1])
        ->and($report->events()['Download'])->toBe(['count' => 1, 'labels' => ['flyer.pdf' => 1]])
        ->and($report->page('/first')['next'])->toBe(['/about' => 1])
        ->and($report->page('/about')['read_rate'])->toBe(1.0);
});

it('links pages to entries', function () {
    rawHits('2026-05-30', [['path' => '/first'], ['path' => '/de/erster-beitrag', 'visitor' => 'b']]);

    $pages = collect(app(Stats::class)->report('2026-05-30', '2026-05-30')->pages())->keyBy('path');

    expect($pages['/first']['entry']->id())->toBe('posts/2026-01-10-first')
        ->and($pages['/de/erster-beitrag']['entry']->language())->toBe('de');
});

it('adds up ranges and builds a daily series', function () {
    rawHits('2026-05-29', [['visitor' => 'a'], ['visitor' => 'b']]);
    rawHits('2026-05-31', [['visitor' => 'a']]);

    $report = app(Stats::class)->report('2026-05-29', '2026-05-31');

    expect($report->visitors())->toBe(3)
        ->and($report->series())->toBe([
            '2026-05-29' => ['pageviews' => 2, 'visitors' => 2],
            '2026-05-30' => ['pageviews' => 0, 'visitors' => 0],
            '2026-05-31' => ['pageviews' => 1, 'visitors' => 1],
        ]);
});

it('counts visitors of the last minutes', function () {
    $now = Carbon::now()->getTimestamp();
    rawHits('2026-06-01', [
        ['visitor' => 'old', 'time' => $now - 900],
        ['visitor' => 'a', 'time' => $now - 60, 'path' => '/about'],
        ['visitor' => 'b', 'time' => $now - 30, 'path' => '/about'],
    ]);

    expect(app(Stats::class)->realtime())->toBe(['visitors' => 2, 'pages' => ['/about' => 2]]);
});

it('summarises finished days in maintenance and prunes old data', function () {
    rawHits('2026-04-01', [['visitor' => 'a']]);
    rawHits('2026-05-31', [['visitor' => 'a']]);
    rawHits('2026-06-01', [['visitor' => 'a']]);
    @mkdir(Paths::data('stats/salt'), 0700, true);
    touch(Paths::data('stats/salt/2026-05-31'));

    app(Maintenance::class)->run();

    expect(is_file(Paths::data('stats/daily/2026-05-31.json')))->toBeTrue()
        ->and(is_file(Paths::data('stats/daily/2026-06-01.json')))->toBeFalse()
        ->and(is_file(Paths::data('stats/monthly/2026-05.json')))->toBeTrue()
        ->and(is_file(Paths::data('stats/raw/2026-04-01.log')))->toBeFalse()
        ->and(is_file(Paths::data('stats/raw/2026-05-31.log')))->toBeTrue()
        ->and(is_file(Paths::data('stats/salt/2026-05-31')))->toBeFalse()
        ->and(app(Stats::class)->day('2026-04-01')['totals']['pageviews'])->toBe(1);
});

it('includes the tracking script and writes the rules for hit.php', function () {
    $this->get('/first')->assertSee('data-endpoint="'.url('hit.php').'"', false);

    expect(require Paths::data('stats/.config.php'))->toMatchArray(['enabled' => true, 'level1' => 'always']);
});

it('leaves the script out when tracking is off', function () {
    config(['wake.tracking.enabled' => false]);

    $this->get('/first')->assertDontSee('track.js');
});

describe('public/hit.php', function () {
    beforeEach(function () {
        copy(dirname(base_path()).'/public/hit.php', $this->sandbox.'/public/hit.php');
        app(TrackingConfig::class)->sync();

        $this->port = random_int(20000, 40000);
        $this->server = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$this->port, '-t', $this->sandbox.'/public'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        usleep(300000);
    });

    afterEach(fn () => proc_terminate($this->server));

    function beacon(int $port, array $data, array $headers = []): int
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", ['Content-Type: application/json', 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Firefox/130.0', ...$headers]),
            'content' => json_encode($data),
            'ignore_errors' => true,
        ]]);
        file_get_contents("http://127.0.0.1:{$port}/hit.php", false, $context);

        return (int) explode(' ', $http_response_header[0])[1];
    }

    it('appends a line without the IP address', function () {
        expect(beacon($this->port, ['t' => 'pageview', 'p' => '/first?utm_source=news', 'r' => 'https://www.example.org/x', 'l' => 'en']))->toBe(204);

        $line = file_get_contents($this->sandbox.'/data/stats/raw/'.date('Y-m-d').'.log');

        expect($line)->toContain("pageview\t")->toContain("/first\texample.org\tnews")->toContain("desktop\tFirefox\tmacOS")
            ->not->toContain('127.0.0.1');
    });

    it('ignores bots, team members and invalid data', function () {
        beacon($this->port, ['t' => 'pageview', 'p' => '/'], ['User-Agent: Googlebot/2.1']);
        beacon($this->port, ['t' => 'pageview', 'p' => '/'], ['Cookie: wake_session=abc']);

        expect(beacon($this->port, ['t' => 'nonsense']))->toBe(400)
            ->and(is_file($this->sandbox.'/data/stats/raw/'.date('Y-m-d').'.log'))->toBeFalse();
    });

    it('stores consent proofs even with statistics switched off', function () {
        config(['wake.tracking.enabled' => false]);
        app(TrackingConfig::class)->sync();

        beacon($this->port, ['t' => 'pageview', 'p' => '/']);
        beacon($this->port, ['t' => 'consent', 'id' => str_repeat('b2', 10), 'c' => ['necessary'], 'a' => 'decline']);

        expect(is_file($this->sandbox.'/data/stats/raw/'.date('Y-m-d').'.log'))->toBeFalse()
            ->and(file_get_contents($this->sandbox.'/data/consent/'.gmdate('Y-m').'.log'))->toContain('"decline"');
    });

    it('stores consent decisions as proof', function () {
        beacon($this->port, ['t' => 'consent', 'id' => str_repeat('a1', 10), 'c' => ['necessary', 'statistics', 'evil'], 'a' => 'custom', 'v' => 3, 'l' => 'de']);

        $proof = json_decode(trim(file_get_contents($this->sandbox.'/data/consent/'.gmdate('Y-m').'.log')), true);

        expect($proof)->toMatchArray(['categories' => ['necessary', 'statistics'], 'version' => 3, 'action' => 'custom', 'language' => 'de']);
    });
});
