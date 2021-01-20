<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Cron;

use Cron\CronExpression;
use DateTime as NativeDateTime;
use DateTimeZone;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\Common\Domain\Value\DateTime\Timezone;
use Throwable;

/**
 * Class Scheduler
 */
final class Scheduler
{
    /**
     * Constructs Scheduler
     */
    public function __construct(protected Timezone $timezone)
    {
    }

    /**
     * Checks if a schedule is due
     *
     * @throws Throwable
     */
    public function isDue(string|callable $schedule): bool
    {
        if (is_callable($schedule)) {
            return call_user_func($schedule);
        }

        $schedule = (string) $schedule;

        $now = DateTime::now($this->timezone->toString());
        $scheduleDateTime = NativeDateTime::createFromFormat(
            'Y-m-d H:i:s',
            $schedule,
            new DateTimeZone($this->timezone->toString())
        );

        if ($scheduleDateTime !== false) {
            return $scheduleDateTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i');
        }

        return (new CronExpression($schedule))
            ->isDue($now->toNative(), $this->timezone->toString());
    }
}
