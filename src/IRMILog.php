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
    private static ?LoggerInterface $logger = null;

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

    public static function getLogger(): ?LoggerInterface
    {
        return static::$logger;
    }
    /**
     * 转换为日志消息
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return string 日志消息字符串
     */
    protected static function convert2Message(string $sign, string $msg, array $data): string
    {
        $data = [
            'sign' => $sign,
            'msg' => $msg,
            'data' => $data,
        ];
        return \json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    /**
     * 记录日志
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @param mixed $level 日志级别
     * @return void
     */
    public static function log(string $sign, string $msg, array $data, $level): void
    {
        static::getLogger()?->log($level, static::convert2Message($sign, $msg, $data));
    }
    /**
     * 紧急日志
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function emergency(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->emergency(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 立即采取行动
     *
     * 示例：整个网站宕机、数据库不可用等。这应该触发短信警报并叫醒你。
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function alert(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->alert(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 严重状况
     *
     * 示例：应用程序组件不可用、意外异常等
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function critical(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->critical(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 运行时错误，不需要立即处理但通常应记录和监控
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function error(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->error(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 非错误的异常情况
     *
     * 示例：使用已弃用的API、API使用不当、不理想但不一定错误的事情
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function warning(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->warning(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 正常但重要的事件
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function notice(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->notice(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 有趣的事件
     *
     * 示例：用户登录、SQL日志等
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function info(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->info(static::convert2Message($sign, $msg, $data));
    }

    /**
     * 详细的调试信息
     *
     * @param string $sign 日志标识
     * @param string $msg 日志消息
     * @param array $data 日志数据
     * @return void
     */
    public static function debug(string $sign, string $msg, array $data = []): void
    {
        static::getLogger()?->debug(static::convert2Message($sign, $msg, $data));
    }
}
