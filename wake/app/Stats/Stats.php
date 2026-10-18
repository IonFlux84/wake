<?php

namespace Wake\Stats;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Answers questions about visits: the dashboard, the statistics page, the
 * editor and the MCP server all ask here.
 *
 * Finished days come from storage/stats/daily/{day}.json, today is computed
 * from the raw file (cached for a minute). Monthly sums in
 * storage/stats/monthly are kept for long-term views and exports.
 */
class Stats
{
    public function __construct(protected Aggregator $aggregator, protected RawLog $raw) {}

    /**
     * The summary of one day.
     *
     * @return array<string, mixed>
     */
    public function day(string $day): array
    {
        if ($day === $this->today()) {
            return Cache::remember('wake.stats.today', 60, fn () => $this->aggregator->day($day));
        }

        $file = $this->dailyFile($day);

        if (is_file($file)) {
            return json_decode((string) file_get_contents($file), true) ?: Aggregator::empty($day);
        }

        // Not summarised yet (maintenance has not run since the day ended).
        return is_file($this->raw->file($day)) ? $this->aggregator->day($day) : Aggregator::empty($day);
    }

    /**
     * A report for a range of days: totals, a daily series and every dimension.
     */
    public function report(CarbonImmutable|string $from, CarbonImmutable|string $to): Report
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();
        $summary = Aggregator::empty($from->toDateString().'/'.$to->toDateString());
        $series = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $this->day($date->toDateString());
            Aggregator::merge($summary, $day);
            $series[$date->toDateString()] = [
                'pageviews' => $day['totals']['pageviews'],
                'visitors' => $day['totals']['visitors'],
            ];
        }

        return new Report($from, $to, $summary, $series);
    }

    /**
     * A report for "7d", "30d", "12m", "today" and similar shortcuts.
     */
    public function period(string $period): Report
    {
        $today = CarbonImmutable::now()->startOfDay();

        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '30d' => [$today->subDays(29), $today],
            '90d' => [$today->subDays(89), $today],
            '12m' => [$today->subMonths(12)->addDay(), $today],
            'month' => [$today->startOfMonth(), $today],
            default => [$today->subDays(6), $today],
        };

        return $this->report($from, $to);
    }

    /**
     * Visits of AI crawlers (GPTBot, ClaudeBot, …), counted by public/index.php.
     *
     * @return array{total: int, bots: array<string, int>, pages: array<string, int>}
     */
    public function aiCrawlers(CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        $result = ['total' => 0, 'bots' => [], 'pages' => []];

        foreach (CarbonPeriod::create(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()) as $date) {
            $handle = @fopen(Paths::data('stats/ai/'.$date->toDateString().'.log'), 'r');

            while ($handle && ($line = fgets($handle)) !== false) {
                [, $bot, $path] = array_pad(explode("\t", rtrim($line, "\n"), 3), 3, '');
                $result['total']++;
                $result['bots'][$bot] = ($result['bots'][$bot] ?? 0) + 1;
                $result['pages'][$path] = ($result['pages'][$path] ?? 0) + 1;
            }

            $handle && fclose($handle);
        }

        arsort($result['bots']);
        arsort($result['pages']);

        return $result;
    }

    /**
     * Visitors and page views of one path per day.
     *
     * @return array<string, array{pageviews: int, visitors: int}>
     */
    public function pageSeries(string $path, CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        $series = [];

        foreach (CarbonPeriod::create(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()) as $date) {
            $page = $this->day($date->toDateString())['pages'][$path] ?? [];
            $series[$date->toDateString()] = ['pageviews' => $page['pageviews'] ?? 0, 'visitors' => $page['visitors'] ?? 0];
        }

        return $series;
    }

    /**
     * Visitors right now: everyone seen in the last five minutes.
     *
     * @return array{visitors: int, pages: array<string, int>}
     */
    public function realtime(int $seconds = 300): array
    {
        $visitors = [];
        $pages = [];

        foreach ($this->raw->recent($seconds) as $hit) {
            if ($hit->type === 'pageview' || $hit->type === 'leave') {
                $visitors[$hit->visitor] = $hit->path;
            }
        }

        foreach ($visitors as $path) {
            $pages[$path] = ($pages[$path] ?? 0) + 1;
        }

        arsort($pages);

        return ['visitors' => count($visitors), 'pages' => $pages];
    }

    /**
     * Write summaries for finished days and months. Returns the number of
     * days summarised. Called by maintenance.
     */
    public function summarise(?callable $shouldStop = null): int
    {
        $count = 0;

        foreach ($this->raw->days() as $day) {
            if ($day >= $this->today()) {
                break;
            }

            if (! is_file($this->dailyFile($day))) {
                Files::write($this->dailyFile($day), json_encode($this->aggregator->day($day), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $count++;
                $this->summariseMonth(substr($day, 0, 7));
            }

            if ($shouldStop && $shouldStop()) {
                break;
            }
        }

        return $count;
    }

    /**
     * Delete raw files older than the retention period and old salts.
     */
    public function prune(): void
    {
        $limit = CarbonImmutable::now()->subDays((int) config('wake.tracking.retention', 30))->toDateString();

        foreach ($this->raw->days() as $day) {
            if ($day < $limit && is_file($this->dailyFile($day))) {
                @unlink($this->raw->file($day));
            }
        }

        foreach (glob(Paths::data('stats/ai/*.log')) ?: [] as $file) {
            if (basename($file, '.log') < $limit) {
                @unlink($file);
            }
        }

        foreach (glob(Paths::data('stats/salt/*')) ?: [] as $salt) {
            if (basename($salt) < $this->today()) {
                @unlink($salt);
            }
        }
    }

    public function dailyFile(string $day): string
    {
        return Paths::data('stats/daily/'.$day.'.json');
    }

    public function monthlyFile(string $month): string
    {
        return Paths::data('stats/monthly/'.$month.'.json');
    }

    protected function summariseMonth(string $month): void
    {
        $summary = Aggregator::empty($month);

        foreach (glob(Paths::data('stats/daily/'.$month.'-*.json')) ?: [] as $file) {
            Aggregator::merge($summary, json_decode((string) file_get_contents($file), true));
        }

        Files::write($this->monthlyFile($month), json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function today(): string
    {
        return CarbonImmutable::now()->toDateString();
    }
}
