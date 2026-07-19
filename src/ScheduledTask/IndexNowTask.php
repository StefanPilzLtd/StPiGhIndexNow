<?php declare(strict_types=1);

namespace StPiGh\IndexNow\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class IndexNowTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'stpi_indexnow.submit';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // taeglich
    }
}
