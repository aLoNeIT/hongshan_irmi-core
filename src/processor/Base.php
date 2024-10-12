<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

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
     * 过滤项目数据，只保留有效期在规则时间范围内的项目
     *
     * @param MedicalInsuranceItem[] $miItems 项目数据集合
     * @param IRMIRule $rule 规则数据
     * @return MedicalInsuranceItem[]
     */
    protected function filterMIItemByDateRange(array $miItems, IRMIRule $rule): array
    {
        return \array_filter($miItems, function (MedicalInsuranceItem $item) use ($rule) {
            return $this->checkDateRange($item->date, $rule);
        });
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

    /**
     * 获取规则中配置的数量
     *
     * @param MedicalRecord $medicalRecord 病历信息
     * @param IRMIRule $rule 规则对象
     * @return integer|null 返回获取到的数量
     */
    protected function getRuleOptionNum(MedicalRecord $medicalRecord, IRMIRule $rule): ?int
    {
        $result = null;
        if (\is_scalar($rule->options['num'])) {
            $result = $rule->options['num'];
        } else {
            // 复杂结构，需要判断
            switch ($rule->options['num']['type']) {
                case 2: // 基于病例项目中的指定属性的值
                    $property = Util::camel($rule->options['num']['property']);
                    // 计算系数
                    $coefficient = $rule->options['num']['coefficient'] ?? 1;
                    $result = \bcmul((string)$medicalRecord->$property, (string)$coefficient);
                    break;
                case 3: // 基于另外一个项目的数量
                    // 继续查询指定项目数据
                    $otherItem = $miItemSet[$rule->options['num']['item_code']] ?? [];
                    $result = \array_reduce(
                        $otherItem,
                        function ($carry, MedicalInsuranceItem $item) use (&$date) {
                            // 汇总计算，如果是计算所有值，则直接汇总，否则只汇总指定日期
                            $carry += 'all' == $date ? $item->num : ($date == $item->date ? $item->num : 0);
                        },
                        0
                    );
                    break;
                default: // 默认直接读取value属性
                    $result = $rule->options['num']['value'];
                    break;
            }
        }
        return $result;
    }
}
