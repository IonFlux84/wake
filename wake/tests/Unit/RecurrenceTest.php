<?php

use Carbon\CarbonImmutable;
use Wake\Content\Recurrence;

function dates(Recurrence $r, string $from, string $to): array
{
    return array_map(fn ($o) => $o['start']->format('Y-m-d H:i'), $r->between(CarbonImmutable::parse($from), CarbonImmutable::parse($to)->endOfDay()));
}

it('repeats weekly with exceptions and an end', function () {
    $r = Recurrence::fromFields(['start' => '2026-06-02 19:00', 'repeat' => 'weekly', 'repeat_until' => '2026-06-30', 'repeat_except' => ['2026-06-16']]);

    expect(dates($r, '2026-06-01', '2026-12-31'))->toBe(['2026-06-02 19:00', '2026-06-09 19:00', '2026-06-23 19:00', '2026-06-30 19:00']);
});

it('repeats every other week', function () {
    $r = Recurrence::fromFields(['start' => '2026-06-02 19:00', 'repeat' => 'biweekly']);

    expect(dates($r, '2026-06-01', '2026-07-01'))->toBe(['2026-06-02 19:00', '2026-06-16 19:00', '2026-06-30 19:00']);
});

it('repeats monthly on the same day and skips short months', function () {
    $r = Recurrence::fromFields(['start' => '2026-01-31 10:00', 'repeat' => 'monthly_date']);

    expect(dates($r, '2026-01-01', '2026-05-31'))->toBe(['2026-01-31 10:00', '2026-03-31 10:00', '2026-05-31 10:00']);
});

it('repeats monthly on the n-th and the last weekday', function () {
    $second = Recurrence::fromFields(['start' => '2026-06-09 19:00', 'repeat' => 'monthly_weekday']);
    $last = Recurrence::fromFields(['start' => '2026-06-30 19:00', 'repeat' => 'monthly_weekday']);

    expect(dates($second, '2026-06-01', '2026-08-31'))->toBe(['2026-06-09 19:00', '2026-07-14 19:00', '2026-08-11 19:00'])
        ->and(dates($last, '2026-06-01', '2026-08-31'))->toBe(['2026-06-30 19:00', '2026-07-28 19:00', '2026-08-25 19:00']);
});

it('repeats yearly', function () {
    $r = Recurrence::fromFields(['start' => '2026-12-24', 'all_day' => true, 'repeat' => 'yearly']);

    expect(dates($r, '2026-01-01', '2028-12-31'))->toBe(['2026-12-24 00:00', '2027-12-24 00:00', '2028-12-24 00:00']);
});

it('finds the next and the last date and keeps the duration', function () {
    $r = Recurrence::fromFields(['start' => '2026-06-02 19:00', 'end' => '2026-06-02 21:00', 'repeat' => 'weekly', 'repeat_until' => '2026-06-16']);

    expect($r->next(CarbonImmutable::parse('2026-06-05'))['start']->format('Y-m-d H:i'))->toBe('2026-06-09 19:00')
        ->and($r->next(CarbonImmutable::parse('2026-06-05'))['end']->format('H:i'))->toBe('21:00')
        ->and($r->next(CarbonImmutable::parse('2026-07-01')))->toBeNull()
        ->and($r->last(CarbonImmutable::parse('2026-07-01'))['start']->format('Y-m-d'))->toBe('2026-06-16');
});

it('describes the rule in words and for calendars', function () {
    $r = Recurrence::fromFields(['start' => '2026-06-09 19:00', 'repeat' => 'monthly_weekday', 'repeat_until' => '2026-12-31']);

    expect($r->describe('en'))->toBe('Every 2nd Tuesday of the month, 7:00 PM until December 31, 2026')
        ->and($r->rrule())->toStartWith('FREQ=MONTHLY;BYDAY=2TU;UNTIL=');
});

it('treats events without a rule as a single date', function () {
    $r = Recurrence::fromFields(['start' => '2026-06-02 19:00']);

    expect($r->isRecurring())->toBeFalse()
        ->and(dates($r, '2026-01-01', '2026-12-31'))->toBe(['2026-06-02 19:00'])
        ->and(Recurrence::fromFields(['title' => 'no start']))->toBeNull();
});
