<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\struct\{
    IRMIRule,
    JsonTable,
};

/**
 * 处理器基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
abstract class Base
{
    /**
     * JsonTable对象结果返回类
     *
     * @var JsonTable|null
     */
    protected ?JsonTable $jsonTable = null;
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->initialize();
    }
    /**
     * 初始化函数
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->jsonTable = new JsonTable();
    }

    /**
     * 获取规则信息
     *
     * @param IRMIRule $rule 规则
     * @return array 返回规则信息
     */
    protected function getRuleInfo(IRMIRule $rule): array
    {
        return [
            'code' => $rule->code,
            'name' => $rule->name,
            'item_code' => $rule->itemCode,
            'item_name' => $rule->itemName
        ];
    }
    /**
     * 获取返回结果
     *
     * @param integer $errNo 错误码
     * @param string $errMsg 错误信息
     * @param array $errData 错误具体数据
     * @return JsonTable 当errData不为空，则返回包含错误信息的JsonTable对象，否则返回包含正确信息JsonTable对象
     */
    protected function getResult(int $errNo, string $errMsg, array $errData): JsonTable
    {
        return empty($errData) ? $this->jsonTable->success()
            : $this->jsonTable->error($errMsg, $errNo, [
                'errors' => $errData
            ]);
    }
}
