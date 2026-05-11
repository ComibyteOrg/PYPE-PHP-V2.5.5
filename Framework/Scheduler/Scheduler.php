<?php

namespace Framework\Scheduler;

/**
 * Task Scheduler
 * Cron-like scheduling within PHP.
 *
 * Usage:
 * $schedule = new Scheduler();
 * $schedule->call(fn() => Article::publishScheduled())->daily();
 * $schedule->command('cache:clear')->weekly();
 * $schedule->raw('php /path/to/script.php')->everyHour();
 * $schedule->run();
 *
 * Then add to system crontab: * * * * * php pype.php schedule:run
 */
class Scheduler
{
    protected array $tasks = [];
    protected array $output = [];

    public function call(callable $callback): Task
    {
        $task = new Task($callback);
        $this->tasks[] = $task;
        return $task;
    }

    public function command(string $command): Task
    {
        $task = new Task(function () use ($command) {
            exec($command, $output, $returnCode);
            return ['output' => $output, 'code' => $returnCode];
        });
        $this->tasks[] = $task;
        return $task;
    }

    public function raw(string $command): Task
    {
        return $this->command($command);
    }

    public function job(string $class, string $method = 'handle'): Task
    {
        $task = new Task(function () use ($class, $method) {
            $instance = new $class();
            return $instance->$method();
        });
        $this->tasks[] = $task;
        return $task;
    }

    public function run(?string $forceTime = null): array
    {
        $now = $forceTime ? strtotime($forceTime) : time();
        $results = [];

        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                if (!$task->shouldPreventOverlaps() || !$this->hasRunRecently($task)) {
                    $result = $task->execute();
                    $results[] = [
                        'task' => $task->getDescription(),
                        'result' => $result,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                    $this->markAsRun($task);
                }
            }
        }

        return $results;
    }

    public function listTasks(): array
    {
        $list = [];
        foreach ($this->tasks as $task) {
            $list[] = [
                'description' => $task->getDescription(),
                'schedule' => $task->getScheduleDescription(),
                'next_run' => $task->getNextRunDate(),
            ];
        }
        return $list;
    }

    protected function hasRunRecently(Task $task): bool
    {
        $lockFile = $this->lockPath() . '/' . md5($task->getDescription()) . '.lock';
        if (!file_exists($lockFile)) {
            return false;
        }
        $lastRun = (int) file_get_contents($lockFile);
        $window = $task->getOverlapWindow() ?? 1440;
        return (time() - $lastRun) < ($window * 60);
    }

    protected function markAsRun(Task $task): void
    {
        $lockPath = $this->lockPath();
        if (!is_dir($lockPath)) {
            mkdir($lockPath, 0755, true);
        }
        $lockFile = $lockPath . '/' . md5($task->getDescription()) . '.lock';
        file_put_contents($lockFile, time());
    }

    protected function lockPath(): string
    {
        return defined('STORAGE_PATH') ? STORAGE_PATH . '/scheduler' : __DIR__ . '/../../Storage/scheduler';
    }
}

class Task
{
    protected $callback;
    protected string $expression = '* * * * *';
    protected ?string $description = null;
    protected bool $preventOverlaps = false;
    protected ?int $overlapWindow = null;
    protected ?string $betweenStart = null;
    protected ?string $betweenEnd = null;
    protected array $environments = [];

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function everyHour(): self
    {
        return $this->hourly();
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron((int) $minute . ' ' . (int) $hour . ' * * *');
    }

    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron('0 ' . $first . ',' . $second . ' * * *');
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $day, string $time = '0:0'): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron((int) $minute . ' ' . (int) $hour . ' * * ' . $day);
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $day = 1, string $time = '0:0'): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron((int) $minute . ' ' . (int) $hour . ' ' . $day . ' * *');
    }

    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function weekends(): self
    {
        return $this->cron('0 0 * * 6,0');
    }

    public function mondays(): self
    {
        return $this->cron('0 0 * * 1');
    }

    public function tuesdays(): self
    {
        return $this->cron('0 0 * * 2');
    }

    public function wednesdays(): self
    {
        return $this->cron('0 0 * * 3');
    }

    public function thursdays(): self
    {
        return $this->cron('0 0 * * 4');
    }

    public function fridays(): self
    {
        return $this->cron('0 0 * * 5');
    }

    public function saturdays(): self
    {
        return $this->cron('0 0 * * 6');
    }

    public function sundays(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function between(string $start, string $end): self
    {
        $this->betweenStart = $start;
        $this->betweenEnd = $end;
        return $this;
    }

    public function inEnvironment(string ...$envs): self
    {
        $this->environments = $envs;
        return $this;
    }

    public function name(string $name): self
    {
        $this->description = $name;
        return $this;
    }

    public function withoutOverlapping(int $window = 1440): self
    {
        $this->preventOverlaps = true;
        $this->overlapWindow = $window;
        return $this;
    }

    public function isDue(int $timestamp): bool
    {
        if (!empty($this->environments) && !in_array(env('APP_ENV', 'production'), $this->environments)) {
            return false;
        }

        if ($this->betweenStart !== null) {
            $currentTime = date('H:i', $timestamp);
            if ($currentTime < $this->betweenStart || $currentTime > $this->betweenEnd) {
                return false;
            }
        }

        return $this->expressionPasses($timestamp);
    }

    public function execute(): mixed
    {
        return call_user_func($this->callback);
    }

    public function getDescription(): string
    {
        return $this->description ?: 'Task';
    }

    public function getScheduleDescription(): string
    {
        return $this->expression;
    }

    public function getNextRunDate(): string
    {
        return date('Y-m-d H:i:s', $this->getNextRunTimestamp());
    }

    public function shouldPreventOverlaps(): bool
    {
        return $this->preventOverlaps;
    }

    public function getOverlapWindow(): ?int
    {
        return $this->overlapWindow;
    }

    protected function expressionPasses(int $timestamp): bool
    {
        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = explode(' ', $this->expression);

        return $this->fieldPasses($minute, (int) date('i', $timestamp))
            && $this->fieldPasses($hour, (int) date('H', $timestamp))
            && $this->fieldPasses($dayOfMonth, (int) date('j', $timestamp))
            && $this->fieldPasses($month, (int) date('n', $timestamp))
            && $this->fieldPasses($dayOfWeek, (int) date('w', $timestamp));
    }

    protected function fieldPasses(string $expression, int $value): bool
    {
        if ($expression === '*') {
            return true;
        }

        if (str_contains($expression, ',')) {
            $values = array_map('trim', explode(',', $expression));
            return in_array((string) $value, $values);
        }

        if (str_contains($expression, '-')) {
            [$min, $max] = explode('-', $expression);
            return $value >= (int) $min && $value <= (int) $max;
        }

        if (str_contains($expression, '/')) {
            [$base, $step] = explode('/', $expression);
            $base = $base === '*' ? 0 : (int) $base;
            return ($value - $base) % (int) $step === 0;
        }

        return (string) $value === $expression;
    }

    protected function getNextRunTimestamp(): int
    {
        $timestamp = time() + 60;
        for ($i = 0; $i < 525960; $i++) {
            if ($this->expressionPasses($timestamp)) {
                return $timestamp;
            }
            $timestamp += 60;
        }
        return time();
    }
}
