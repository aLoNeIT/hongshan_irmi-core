<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};

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
     * @var JsonTable
     */
    protected JsonTable $jsonTable = new JsonTable();
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
    protected function initialize(): void {}
    /**
     * 检查规则适用的时间范围
     *
     * @param integer $date 日期时间戳
     * @param IRMIRule $rule 规则数据
     * @return boolean 返回是否在时间范围内，true为命中时间范围，false为未命中
     */
    protected function checkDateRange(int $date, IRMIRule $rule): bool
    {
        // 检查该规则适用的时间范围
        $timeRange = $rule->options['time_range'] ?? null;
        if (!\is_null($timeRange)) {
            if (
                ((\is_null($timeRange[0]) || $date >= $timeRange[0])
                    && (\is_null($timeRange[1]) || $date < $timeRange[1]))
            ) {
                // 时间符合规则要求的范围
                return true;
            }
        }
        return false;
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
            'item' => $rule->itemCode,
            'item_name' => $rule->itemName
        ];
    }
}
