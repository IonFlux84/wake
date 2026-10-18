<?php

namespace Wake\Maintenance;

use Throwable;
use Wake\Maintenance\Tasks\PublishScheduled;
use Wake\Maintenance\Tasks\SummariseStats;
use Wake\Support\Files;

/**
 * Housekeeping without cron. After a response has been sent to the visitor,
 * Wake runs the maintenance tasks at most once per "maintenance.interval"
 * seconds and for at most "maintenance.budget" seconds. With
 * "maintenance.mode" set to "cron", `php wake/artisan schedule:run` does
 * the same job instead.
 */
class Maintenance
{
    /** @var list<class-string<Task>> */
    protected array $tasks = [
        PublishScheduled::class,
        SummariseStats::class,
    ];

    /**
     * Add a task, e.g. from a plugin or a later phase of Wake.
     *
     * @param  class-string<Task>  $task
     */
    public function extend(string $task): void
    {
        $this->tasks[] = $task;
    }

    /**
     * @return list<class-string<Task>>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /**
     * Run when the interval has passed and nobody else is running.
     */
    public function runIfDue(): bool
    {
        if (time() - $this->lastRun() < (int) config('wake.maintenance.interval', 60)) {
            return false;
        }

        return (bool) Files::lock('maintenance', function () {
            if (time() - $this->lastRun() < (int) config('wake.maintenance.interval', 60)) {
                return false;
            }

            $this->runTasks();

            return true;
        }, wait: false);
    }

    /**
     * Run all tasks now.
     */
    public function run(): void
    {
        Files::lock('maintenance', fn () => $this->runTasks());
    }

    public function lastRun(): int
    {
        return (int) @filemtime($this->stamp());
    }

    protected function runTasks(): void
    {
        @touch($this->stamp());
        $deadline = new Deadline((float) config('wake.maintenance.budget', 5));

        foreach ($this->tasks as $task) {
            if ($deadline->expired()) {
                break;
            }

            try {
                app($task)->run($deadline);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    protected function stamp(): string
    {
        return storage_path('locks/maintenance.last');
    }
}
