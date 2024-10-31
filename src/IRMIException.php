<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

use hongshanhealth\irmi\struct\JsonTable;
use Throwable;

/**
 * 异常类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMIException extends \Exception
{
    /**
     * 异常附加信息
     *
     * @var mixed
     */
    protected $data = null;
    /**
     * 错误码
     *
     * @var integer
     */
    protected int $state = 1;

    /**
     * 重写构造函数
     *
     * @param string|JsonTable $msg 错误信息
     * @param integer $state 错误码
     * @param array $data 附加信息
     * @param object $previous 牵制对象
     */
    public function __construct(string | JsonTable $msg, int $state = 1, ?mixed $data = null, ?\Throwable $previous = null)
    {
        if ($msg instanceof JsonTable) {
            $previous = $previous ?: $msg->getProperty('exception');
            parent::__construct($msg->msg, $msg->state, $previous);
            $this->data = $msg->data;
            $this->state = $msg->state;
        } else {
            parent::__construct($msg, $state, $previous);
            $this->data = $data;
            $this->state = $state;
        }
    }
    /**
     * 获取附带信息
     *
     * @return mixed
     */
    final public function getData(): mixed
    {
        return $this->data;
    }
    /**
     * 获取错误码
     *
     * @return integer
     */
    final public function getState(): int
    {
        return $this->state;
    }
}
