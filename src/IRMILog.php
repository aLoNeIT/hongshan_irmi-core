<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

use Psr\Log\LoggerInterface;

/**
 * 日志管理对象
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMILog
{
    private LoggerInterface $logger;

    public static function setLogger(LoggerInterface $logger): void
    {
        static::$logger = $logger;
    }

    public static function getLogger(): LoggerInterface
    {
        return static::$logger;
    }
}
