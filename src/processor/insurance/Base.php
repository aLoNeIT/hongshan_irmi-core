<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\processor\Base as BaseProcessor;
use hongshanhealth\irmi\struct\{
    MedicalRecord,
    IRMIRule,
    IRMIRuleSet,
    JsonTable,
    MedicalInsuranceItem
};
use hongshanhealth\irmi\Util;

/**
 * 医保项目计算器基类
 */
class Base extends BaseProcessor
{
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
                !((\is_null($timeRange[0]) || $date >= $timeRange[0])
                    && (\is_null($timeRange[1]) || $date < $timeRange[1]))
            ) {
                // 时间不符合规则要求的范围
                return false;
            }
        }
        return true;
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
                    $result = (int)\bcmul((string)$medicalRecord->$property, (string)$coefficient);
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
        return (int)$result;
    }

    /**
     *  检查就诊科室
     *
     * @param MedicalRecord $medicalRecord 病例对象
     * @param IRMIRule $rule 规则对象
     * @return boolean 在排除列表中返回true，否则返回false
     */
    protected function checkIncludedBranch(MedicalRecord $medicalRecord, IRMIRule $rule): bool
    {
        $errors = [];
        $included = null;
        if (isset($rule->options['include_branch'])) {
            $included = true;
        } else if (isset($rule->options['exclude_branch'])) {
            $included = false;
        }
        if (null === $included) {
            return true;
        }
        $key = $included ? 'include_branch' : 'exclude_branch';
        $branch = 1 == $medicalRecord->visitType ? $medicalRecord->inBranch : $medicalRecord->outBranch;
        $result = \in_array($branch, $rule->options[$key]);
        if ($included && !$result) {
            // 指定科室要求，并且当前科室不在指定范围内
            $errors[] = [
                'msg' => "当前项目[{$rule->itemName}]未由指定包含科室开具",
                'data' => [
                    'rule' => $this->getRuleInfo($rule)
                ]
            ];
        } else if (!$included && $result) {
            $errors[] = [
                'msg' => "当前项目[{$rule->itemName}]不可由指定排除科室开具",
                'data' => [
                    'rule' => $this->getRuleInfo($rule)
                ]
            ];
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * 检查当前病历中的诊疗项目[包含/不包含]
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return boolean|array 检查通过返回true，检查未通过返回错误信息数组
     */
    protected function checkIncludedItems(MedicalRecord $medicalRecord, IRMIRule $rule): bool|array
    {
        $errors = [];
        $included = null;
        if (isset($rule->options['include_items'])) {
            $included = true;
        } else if (isset($rule->options['exclude_items'])) {
            $included = false;
        }
        if (\is_null($included)) {
            // 未配置则直接返回通过
            return true;
        }
        $key = $included ? 'include_items' : 'exclude_items';
        $includedItems = $rule->options[$key];
        $timeType = $includedItems['time_type'] ?? 2;
        $itemCollection = $includedItems['collection'] ?? [];
        // 获取临时数据，同时根据规则有效期进行过滤
        $tmpMiItemSet = \array_map(function (array $items) use ($rule) {
            return $this->filterMIItemByDateRange($items, $rule);
        }, $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE));

        // 循环判断，包含的项目存在 当天或全部 匹配条件
        if (2 === $timeType) {
            // 循环 包含 或 排除 依次判断是否错误
            $existed = null; // 是否找到指定内容
            /** @var MedicalInsuranceItem $miItem */
            foreach ($itemCollection as $code => $config) {
                // 检测指定项目是否存在
                if ($included && isset($tmpMiItemSet[$code])) {
                    // 包含的项目只要任一项目存在即可
                    $existed = true;
                    // 跳出循环
                    break;
                } else if (!$included && isset($tmpMiItemSet[$code])) {
                    // 要求排除的项目存在
                    if (!\is_null($config)) {
                        if (isset($config['combine_items'])) {
                            // 排除项目存在组合项，即同时组合项目也不得存在
                            $intersectItems = \array_intersect_key($tmpMiItemSet, $config['combine_items']);
                            if (!empty($intersectItems)) {
                                // 匹配到组合项目，则跳过
                                continue;
                            }
                        }
                        // 汇总计算数组内指定字段的值
                        $totalNum = \array_reduce($tmpMiItemSet[$code], function (int $sum, MedicalInsuranceItem $miItem) {
                            return $sum + $miItem->num ?: 0;
                        }, 0);
                        if (!isset($config['num']) || $totalNum < $config['num']) {
                            // 收费小于指定数量，则跳过
                            continue;
                        }
                    }
                    $existed[] = $code;
                }
            }
            // 最后统一计算
            if ($included && true !== $existed) {
                // 要求包含的项目不存在
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]未与指定包含项目同时收费",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'include_items' => \array_keys($itemCollection)
                    ]
                ];
            } else if ($included && \is_array($existed) && !empty($existed)) {
                // 未匹配到组合项目，则当前项目重复收费
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]与指定排除项目同时收费",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'exclude_items' => $existed
                    ]
                ];
            }
        } else {
            // 按天匹配
            /** @var MedicalInsuranceItem[] $currItems */
            $currItems = $tmpMiItemSet[$rule->itemCode];
            /** @var MedicalInsuranceItem $miItem */
            foreach ($currItems[$rule->itemCode] as $miItem) {
                $date = $miItem->date;
                /** @var array $dateMiItems */
                $dateMiItems = $medicalRecord->medicalInsuranceSet[$date];
                // 交集计算看当天是否有包含内的项目
                $itemKeys = \array_keys($itemCollection);
                $intersectItems = \array_intersect($itemKeys, \array_keys($dateMiItems));
                $dateStr = date('Y-m-d', $date);
                if ($included && empty($intersectItems)) {
                    // 未匹配到必须包含的项目
                    $errors[] = [
                        'msg' => "当前项目[{$rule->itemName}]在[{$dateStr}]当天未与指定包含项目同时收费",
                        'data' => [
                            'rule' => $this->getRuleInfo($rule),
                            'date' => $date,
                            'include_items' => $itemKeys
                        ]
                    ];
                } else if (!$included && !empty($intersectItems)) {
                    // 此处遍历交集编码，然后到规则配置中查询是否有num配置
                    foreach ($intersectItems as $code) {
                        $config = $itemCollection[$code];
                        if (isset($config['num'])) {
                            // 汇总获取项目数量
                            $totalNum = \array_reduce($dateMiItems[$code], function ($carry, MedicalInsuranceItem $item) {
                                return $carry + $item->num;
                            }, 0);
                            if ($totalNum < $config['num']) {
                                continue;
                            }
                            // 数量未超过限制
                            continue;
                        }
                        // 循环写入错误信息
                        $errors[] = [
                            'msg' => "当前项目[{$rule->itemName}]在[{$dateStr}]当天与指定排除项目同时收费",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'date' => $date,
                                'exclude_item_code' => $code
                            ]
                        ];
                    }
                }
            }
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * 获取最后一天时间
     *
     * @param integer $firstDay 第一天时间戳
     * @param integer $intervalNum 间隔数量
     * @param integer $type 类型
     * @return integer 返回最后一天时间戳
     */
    protected function getLastDay(int $firstDay, int $intervalNum = 1, int $type = 2): int
    {
        $result = $firstDay;
        switch ($type) {
            case 3: // 周
                // 先算出给定日期所处周的第一天，以周一为开始
                $dayOfWeek = date('w', $firstDay) - 1;
                $weekFirstDay = $firstDay - 86400 * $dayOfWeek;
                $result = $weekFirstDay + 86400 * 7 * $intervalNum;
                break;
            case 4: // 月
                // 先算出给定日期所处月的第一天
                $monthFirstDay = strtotime(date('Y-m-01', $firstDay));
                $result = strtotime("+{$intervalNum} month", $monthFirstDay);
                break;
            case 5: // 年
                $yearFirstDay = strtotime(date('Y-01-01', $firstDay));
                $result = strtotime("+{$intervalNum} year", $yearFirstDay);
                break;
            default: // 日
                $result = $firstDay + 86400 * $intervalNum;
                break;
        }
        return $result;
    }
}
