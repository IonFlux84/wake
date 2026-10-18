<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Throwable;

/**
 * The dates of an event, from its fields:
 *
 *     start: 2026-11-03 19:00
 *     end: 2026-11-03 21:00
 *     repeat: weekly          # weekly, biweekly, monthly_date, monthly_weekday, yearly
 *     repeat_until: 2027-06-30
 *     repeat_except: [2026-12-22, 2026-12-29]
 *
 * A recurring event stays one entry; its dates are calculated.
 */
final class Recurrence
{
    public const RULES = ['weekly', 'biweekly', 'monthly_date', 'monthly_weekday', 'yearly'];

    /** Safety limit for open-ended rules. */
    protected const MAX_STEPS = 2000;

    protected int $duration;

    /**
     * @param  list<string>  $except  dates (Y-m-d) without an event
     */
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end = null,
        public readonly ?string $rule = null,
        public readonly ?CarbonImmutable $until = null,
        public readonly array $except = [],
        public readonly bool $allDay = false,
    ) {
        $this->duration = $end && $end->gt($start) ? $end->getTimestamp() - $start->getTimestamp() : 0;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function fromFields(array $fields): ?self
    {
        $tz = config('app.timezone');
        $parse = function ($value) use ($tz): ?CarbonImmutable {
            if ($value === null || $value === '') {
                return null;
            }

            try {
                return CarbonImmutable::parse((string) $value, $tz);
            } catch (Throwable) {
                return null;
            }
        };

        $start = $parse($fields['start'] ?? null);

        if ($start === null) {
            return null;
        }

        $rule = in_array($fields['repeat'] ?? null, self::RULES, true) ? $fields['repeat'] : null;
        $except = $fields['repeat_except'] ?? [];
        $except = is_array($except) ? $except : preg_split('/[\s,;]+/', (string) $except);

        return new self(
            start: $start,
            end: $parse($fields['end'] ?? null),
            rule: $rule,
            until: $parse($fields['repeat_until'] ?? null)?->endOfDay(),
            except: array_values(array_filter(array_map(fn ($d) => $parse($d)?->toDateString(), $except))),
            allDay: (bool) ($fields['all_day'] ?? false),
        );
    }

    public function isRecurring(): bool
    {
        return $this->rule !== null;
    }

    /**
     * Occurrences that overlap the period, earliest first.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function between(CarbonInterface $from, CarbonInterface $to, int $limit = 500): array
    {
        [$from, $to] = [CarbonImmutable::instance($from), CarbonImmutable::instance($to)];
        $result = [];

        foreach ($this->starts() as $start) {
            if ($start->gt($to) || count($result) >= $limit) {
                break;
            }

            $end = $this->endOf($start);

            if ($end->gte($from)) {
                $result[] = ['start' => $start, 'end' => $end];
            }
        }

        return $result;
    }

    /**
     * The first occurrence that has not ended before $after.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    public function next(CarbonInterface $after): ?array
    {
        $after = CarbonImmutable::instance($after);
        foreach ($this->starts() as $start) {
            $end = $this->endOf($start);

            if ($end->gte($after)) {
                return ['start' => $start, 'end' => $end];
            }
        }

        return null;
    }

    /**
     * The last occurrence before $before (for past events).
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    public function last(CarbonInterface $before): ?array
    {
        $before = CarbonImmutable::instance($before);
        $last = null;

        foreach ($this->starts() as $start) {
            if ($this->endOf($start)->gte($before)) {
                break;
            }

            $last = ['start' => $start, 'end' => $this->endOf($start)];
        }

        return $last;
    }

    /**
     * The rule in words, e.g. "Every Tuesday until 30 June 2027".
     */
    public function describe(?string $locale = null): string
    {
        if (! $this->isRecurring()) {
            return '';
        }

        $locale ??= app()->getLocale();
        $start = $this->start->locale($locale);
        $weekday = $start->isoFormat('dddd');
        $nth = (int) ceil($this->start->day / 7);
        $ordinal = match (true) {
            $this->isLastWeekday() => __('last', locale: $locale),
            str_starts_with($locale, 'en') => $nth.(['st', 'nd', 'rd'][$nth - 1] ?? 'th'),
            default => $nth.'.',
        };

        $text = match ($this->rule) {
            'weekly' => __('Every :weekday', ['weekday' => $weekday], $locale),
            'biweekly' => __('Every other :weekday', ['weekday' => $weekday], $locale),
            'monthly_date' => __('Monthly on the :day.', ['day' => $start->day], $locale),
            'monthly_weekday' => __('Every :ordinal :weekday of the month', ['ordinal' => $ordinal, 'weekday' => $weekday], $locale),
            'yearly' => __('Every year on :date', ['date' => $start->isoFormat('D. MMMM')], $locale),
        };

        if (! $this->allDay) {
            $text .= ', '.$start->isoFormat('LT');
        }

        if ($this->until) {
            $text .= ' '.__('until :date', ['date' => $this->until->locale($locale)->isoFormat('LL')], $locale);
        }

        return $text;
    }

    /**
     * The rule for calendar files (RFC 5545), or null.
     */
    public function rrule(): ?string
    {
        if (! $this->isRecurring()) {
            return null;
        }

        $days = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
        $day = $days[$this->start->dayOfWeek];

        $rule = match ($this->rule) {
            'weekly' => 'FREQ=WEEKLY',
            'biweekly' => 'FREQ=WEEKLY;INTERVAL=2',
            'monthly_date' => 'FREQ=MONTHLY;BYMONTHDAY='.$this->start->day,
            'monthly_weekday' => 'FREQ=MONTHLY;BYDAY='.($this->isLastWeekday() ? '-1' : (int) ceil($this->start->day / 7)).$day,
            'yearly' => 'FREQ=YEARLY',
        };

        if ($this->until) {
            $rule .= ';UNTIL='.$this->until->utc()->format('Ymd\THis\Z');
        }

        return $rule;
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    protected function starts(): Generator
    {
        if (! $this->isRecurring()) {
            yield $this->start;

            return;
        }

        $nth = (int) ceil($this->start->day / 7);
        $last = $this->isLastWeekday();

        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            $date = match ($this->rule) {
                'weekly' => $this->start->addWeeks($i),
                'biweekly' => $this->start->addWeeks($i * 2),
                'monthly_date' => $this->monthDay($i),
                'monthly_weekday' => $this->monthWeekday($i, $nth, $last),
                'yearly' => $this->start->day === 29 && $this->start->month === 2 && ! $this->start->addYears($i)->isLeapYear()
                    ? null : $this->start->addYears($i),
            };

            if ($date === null) {
                continue;
            }

            if ($this->until && $date->gt($this->until)) {
                return;
            }

            if (! in_array($date->toDateString(), $this->except, true)) {
                yield $date;
            }
        }
    }

    /**
     * The same day of month, skipping months that are too short (31st).
     */
    protected function monthDay(int $months): ?CarbonImmutable
    {
        $month = $this->start->startOfMonth()->addMonths($months);

        return $this->start->day <= $month->daysInMonth
            ? $month->setDay($this->start->day)->setTime($this->start->hour, $this->start->minute)
            : null;
    }

    /**
     * The n-th (or last) weekday of a month, e.g. the 2nd Tuesday.
     */
    protected function monthWeekday(int $months, int $nth, bool $last): CarbonImmutable
    {
        $month = $this->start->startOfMonth()->addMonths($months);
        $weekday = $this->start->dayOfWeek;

        if ($last) {
            $date = $month->endOfMonth()->startOfDay();

            while ($date->dayOfWeek !== $weekday) {
                $date = $date->subDay();
            }
        } else {
            $date = $month;

            while ($date->dayOfWeek !== $weekday) {
                $date = $date->addDay();
            }

            $date = $date->addWeeks($nth - 1);
        }

        return $date->setTime($this->start->hour, $this->start->minute);
    }

    /**
     * Whether the start is the last such weekday of its month.
     */
    protected function isLastWeekday(): bool
    {
        return $this->start->addWeek()->month !== $this->start->month && (int) ceil($this->start->day / 7) >= 4;
    }

    protected function endOf(CarbonImmutable $start): CarbonImmutable
    {
        if ($this->duration > 0) {
            return $start->addSeconds($this->duration);
        }

        return $this->allDay || $start->format('H:i') === '00:00' ? $start->endOfDay() : $start;
    }
}
