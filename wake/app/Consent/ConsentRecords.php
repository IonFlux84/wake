<?php

namespace Wake\Consent;

use Carbon\CarbonImmutable;
use Wake\Support\Paths;

/**
 * Reads the consent records in data/consent/{year}-{month}.log.
 */
class ConsentRecords
{
    /**
     * Decisions in a period: totals per action, agreement per category and
     * the most recent records.
     *
     * @return array{total: int, actions: array<string, int>, categories: array<string, int>, recent: list<array<string, mixed>>}
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to, int $recent = 10): array
    {
        $result = ['total' => 0, 'actions' => ['accept' => 0, 'decline' => 0, 'custom' => 0], 'categories' => [], 'recent' => []];

        for ($month = $from->startOfMonth(); $month <= $to; $month = $month->addMonth()) {
            $handle = @fopen($this->file($month->format('Y-m')), 'r');

            while ($handle && ($line = fgets($handle)) !== false) {
                $record = json_decode($line, true);
                $time = isset($record['time']) ? CarbonImmutable::parse($record['time']) : null;

                if (! $time || $time < $from->startOfDay() || $time > $to->endOfDay()) {
                    continue;
                }

                $result['total']++;
                $result['actions'][$record['action']] = ($result['actions'][$record['action']] ?? 0) + 1;

                foreach ($record['categories'] as $category) {
                    $result['categories'][$category] = ($result['categories'][$category] ?? 0) + 1;
                }

                $result['recent'][] = $record;
            }

            $handle && fclose($handle);
        }

        $result['recent'] = array_slice(array_reverse($result['recent']), 0, $recent);

        return $result;
    }

    public function file(string $month): string
    {
        return Paths::data('consent/'.$month.'.log');
    }
}
