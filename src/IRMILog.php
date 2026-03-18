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
    /**
     * 日志对象
     *
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger = null;

    /**
     * 设置日志对象
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        static::$logger = $logger;
    }

    public static function getLogger(): LoggerInterface
    {
        return static::$logger;
    }
    /**
     * 转换为日志消息
     *
     * @param string $sign
     * @param string $msg
     * @param array $data
     * @return string
     */
    protected function convert2Message(string $sign, string $msg, array $data): string
    {
        $data = [
            'sign' => $sign,
            'msg' => $msg,
            'data' => $data,
        ];
        return \json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function log(string $sign, string $msg, array $data, $level): void
    {
        static::getLogger()?->log($level, static::convert2Message($sign, $msg, $data));
    }
    /**
     * System is unusable.
     *
     * @param mixed[] $context
     */
    public function emergency(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->emergency(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param mixed[] $context
     */
    public function alert(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->alert(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param mixed[] $context
     */
    public function critical(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->critical(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param mixed[] $context
     */
    public function error(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->error(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param mixed[] $context
     */
    public function warning(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->warning(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Normal but significant events.
     *
     * @param mixed[] $context
     */
    public function notice(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->notice(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param mixed[] $context
     */
    public function info(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->info(static::convert2Message($sign, $msg, $data));
    }

    /**
     * Detailed debug information.
     *
     * @param mixed[] $context
     */
    public function debug(string $sign, string $msg, array $data): void
    {
        static::getLogger()?->debug(static::convert2Message($sign, $msg, $data));
    }
}
