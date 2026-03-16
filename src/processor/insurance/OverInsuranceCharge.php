<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

/**
 * 超医保支付范围处理器
 */
class OverInsuranceCharge extends Base implements IDetectInsuranceProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
                case 1:
                    $jResult = $this->detectCommon($medicalRecord, $rule);
                    break;
                case 2: // 超数量
                    $jResult = $this->detectOverNum($medicalRecord, $rule);
                    break;
                default:
                    $jResult = $this->jsonTable->success();
                    break;
            }
            return $jResult;
        } catch (IRMIException $ex) {
            throw $ex;
        }
    }
    /**
     * 通用检测
     *
     * @param MedicalRecord $medicalRecord 病例对象
     * @param IRMIRule $rule 规则对象
     * @return JsonTable 结果对象
     */
    protected function detectCommon(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $currItems = $this->filterMIItemByDateRange($miItemSet[$rule->itemCode], $rule);
        // 限定诊疗类型
        if (isset($rule->options['visit_type'])) {
            if ($medicalRecord->visitType != $rule->options['visit_type']) {
                // 就诊类型不匹配
                $ruleVisitTypeName =  1 == $rule->options['visit_type']  ? '门诊' : '住院';
                $visitTypeName = 1 == $medicalRecord->visitType ? '门诊' : '住院';
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]适用于[{$ruleVisitTypeName}]，实际[{$visitTypeName}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'item_ids' => $this->getMedicalItemId($currItems)
                    ]
                ];
            }
        }
        // 年龄限定
        if (isset($rule->options['age_range'])) {
            [$ageMin, $ageMax] = $rule->options['age_range'];
            if (
                (!\is_null($ageMin) && $medicalRecord->age < $ageMin ||
                    !\is_null($ageMax) && $medicalRecord->age > $ageMax)
            ) {
                // 年龄不符合要求
                $ageMinStr = \is_null($ageMin) ? '不限' : $ageMin;
                $ageMaxStr = \is_null($ageMax) ? '不限' : $ageMax;
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]限定年龄未在[{$ageMinStr},{$ageMaxStr}]范围内，实际年龄[{$medicalRecord->age}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'item_ids' => $this->getMedicalItemId($currItems)
                    ],
                ];
            }
        }

        // 配置了总天数
        if (isset($rule->options['total_days'])) {
            $totalDays = $rule->options['total_days'];
            $dates = [];
            \array_walk($currItems, function (MedicalInsuranceItem $item) use (&$dates) {
                $dates[$item->date] = 1;
            });
            $days = \count($dates);
            if ($days > $totalDays) {
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]总天数应不超过[{$totalDays}]天，实际[{$days}]天",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'total_days' => $totalDays,
                        'days' => $days,
                        'item_ids' => $this->getMedicalItemId($currItems)
                    ],
                ];
            }
        }
        // 周期类的选项
        if (isset($rule->options['period'])) {
            $periodType = $rule->options['period']['type'] ?? 1;
            $periodNum = $rule->options['period']['num'] ?? 1;
            $periodSubNum = $rule->options['period']['sub_num'] ?? 1;
            if (1 == $periodType) {
                // 次
                // 次为单位，记为单次
                $totalSubNum = (float)\array_reduce(
                    $currItems,
                    function ($carry, MedicalInsuranceItem $item) {
                        return \bcadd($carry, (string)($item->num ?: 0));
                    },
                    '0'
                );

                if (1 === \bccomp((string)$totalSubNum, (string)$periodSubNum)) {
                    $errors[] = [
                        'msg' => "当前项目[{$rule->itemName}]总次数应不超过[{$periodSubNum}]次，实际[{$totalSubNum}]次",
                        'data' => [
                            'rule' => $this->getRuleInfo($rule),
                            'total_sub_num' => $totalSubNum,
                            'num' => $periodSubNum,
                            'item_ids' => $this->getMedicalItemId($currItems)
                        ],
                    ];
                }
            } else {
                // 日为单位，需要考虑整个时间周期内，每30天算一次总数，看是否超过范围
                $sortDate = []; // 进行了排序的日期集合，一维数组，数字下标
                $dateNum = []; // 每个日期内对应的项目次数，kv数组
                \array_walk($currItems, function (MedicalInsuranceItem $item) use (&$sortDate, &$dateNum) {
                    if (!isset($dateNum[$item->date])) {
                        // 不存在于数组中，才需要进行排序数组计算
                        $sortDate[] = $item->date;
                    }
                    $dateNum[$item->date] = Util::amountFormat(\bcadd((string)($dateNum[$item->date] ?? 0), (string)$item->num));
                });
                // 进行排序
                @\sort($sortDate, SORT_NUMERIC);
                // 开始从小到大进行处理，需要双重循环
                $i = 0;
                $j = 0;
                for ($i = 0; $i < count($sortDate); $i++) {
                    $firstDay = $sortDate[$i];
                    $lastDay = $this->getLastDay($firstDay, $periodNum, $periodType);
                    $rangeTotalNum = $dateNum[$firstDay] ?? 0; // 区间内的总数量，累计第一天数量
                    for ($j = $i + 1; $j < count($sortDate); $j++) {
                        if ($sortDate[$j] <= $lastDay) {
                            $rangeTotalNum += $dateNum[$sortDate[$j]];
                        } else {
                            // 时间范围超过了，退出
                            break;
                        }
                    }
                    // 比较下周期内的子数据之和是否超过了指定数量
                    if ($rangeTotalNum > $periodSubNum) {
                        $firstDayStr = date('Y-m-d', $firstDay);
                        $lastDayStr = date('Y-m-d', $lastDay);
                        $errors[] = [
                            'msg' => "当前项目[{$rule->itemName}]在[{$firstDayStr}]至[$lastDayStr]的[{$periodNum}]天内，次数应不超过[{$periodSubNum}]次，实际[{$rangeTotalNum}]次",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'total_sub_num' => $rangeTotalNum,
                                'first_day' => $firstDay,
                                'last_day' => $lastDay,
                                'item_ids' => $this->getMedicalItemId(
                                    \array_filter($currItems, function (MedicalInsuranceItem $item) use ($firstDay, $lastDay) {
                                        // 只获取区间内的数据    
                                        return $item->date >= $firstDay && $item->date <= $lastDay;
                                    })
                                )
                            ],
                        ];
                    }
                }
            }
        }
        // 同时包含校验
        $result = $this->checkIncludedItems($medicalRecord, $rule);
        if (true !== $result) {
            $errors = [
                ...$errors,
                ...$result
            ];
        }
        return $this->getResult(300, '超医保支付范围', $errors);
    }
    /**
     * 检测超数量
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return JsonTable 返回结果
     */
    protected function detectOverNum(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $currItems = $this->filterMIItemByDateRange($tmpMiItemSet[$rule->itemCode], $rule);
        $itemType = $rule->options['item_type'] ?? null;
        // 获取限定的数量
        list($ruleNum, $ruleNumType) = $this->getRuleOptionNum($medicalRecord, $rule);
        // 1-原始数字，2-病历中的某个属性，3-另一个项目的数量，4-群组中项目的个数，5-群组中项目的计费总数；
        switch ($ruleNumType) {
            case 4: // 群组中项目个数
                foreach ($currItems as $currItem) {
                    $num = 0;
                    // 遍历每一个项目数据，根据项目的组号、日期来进行比对
                    $date = $currItem->date;
                    $groupCode = $currItem->groupCode;
                    $dateMiItems = $medicalRecord->medicalInsuranceSet[$date] ?? [];
                    $groupItems = [];
                    foreach ($dateMiItems as $code => $items) {
                        // 根据项目组号、项目类型进行筛选
                        $result = \array_filter($items, function (MedicalInsuranceItem $item) use ($itemType, $groupCode) {
                            return \is_null($itemType)
                                ? true
                                : $item->type == $itemType && $item->groupCode == $groupCode;
                        });
                        // 有符合条件的项目，数量加1
                        if (!empty($result)) {
                            $num++;
                            $groupItems = [...$groupItems, ...$result];
                        }
                    }
                    // 根据最终数量，判断是否处于有效区间内，并提取出有问题的数据id
                    if (!$this->compareNum($num, $ruleNum)) {
                        $itemsIds = [];
                        [$min, $max] = \is_array($ruleNum) ? $ruleNum : [0, $ruleNum];
                        if ($num < $min) {
                            $itemsIds = \array_filter(\array_map(function (MedicalInsuranceItem $item) {
                                return $item->id;
                            }, $groupItems));
                        } else if ($num > $max) {
                            $itemsIds = \array_filter(\array_map(function (MedicalInsuranceItem $item) {
                                return $item->id;
                            }, \array_slice($groupItems, $max)));
                        }
                        // 实际项目数量超过限定数量
                        $errors[] = [
                            'msg' => "当前项目[{$rule->itemName}]在同一分组内，项目类别总数应" . $this->getNumErrorStr($ruleNum) . "，实际[{$num}]",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'item_ids' => $itemsIds,
                            ],
                        ];
                    }
                }
                break;
            case 5: //群组中项目的计费总数
                foreach ($currItems as $currItem) {
                    // 遍历每一个项目数据，根据项目的组号、日期来进行比对
                    $date = $currItem->date;
                    $groupCode = $currItem->groupCode;
                    $dateMiItems = $medicalRecord->medicalInsuranceSet[$date] ?? [];
                    $groupItems = [];
                    $num = 0;
                    foreach ($dateMiItems as $code => $items) {
                        if ($code == $rule->itemCode) {
                            // 当前项目直接跳过
                            continue;
                        }
                        // 根据项目组号、项目类型进行筛选
                        $result = \array_filter($items, function (MedicalInsuranceItem $item) use ($itemType, $groupCode) {
                            return \is_null($itemType)
                                ? true
                                : $item->type == $itemType && $item->groupCode == $groupCode;
                        });
                        $groupItems = [...$groupItems, ...$result];
                        // 计算数量
                        $num = (float)\array_reduce($result, function ($carry, $item) {
                            return \bcadd((string) $carry, (string) $item->num);
                        }, '0');
                    }
                    // 根据最终数量，判断是否处于有效区间内，并提取出有问题的数据id
                    $itemIds = [];
                    if (!$this->compareNum((float)$num, $ruleNum)) {
                        [$min, $max] = \is_array($ruleNum) ? $ruleNum : [0, $ruleNum];
                        if ($num < $min) {
                            // 实际项目数量低于限定数量，则说明所有数据都有问题
                            $itemIds = \array_filter(\array_map(function (MedicalInsuranceItem $item) {
                                return $item->id;
                            }, $groupItems));
                        } else if ($num > $max) {
                            // 获取超出数量范围数据，则提取超出范围的数据id
                            $tmpNum = 0;
                            foreach ($groupItems as $item) {
                                $tmpNum = \bcadd((string) $tmpNum, (string) $item->num);
                                if ($tmpNum > $max) {
                                    // 超出数量范围，则记录当前数据id
                                    $itemIds[] = $item->id;
                                }
                            }
                        }
                        $errors[] = [
                            'msg' => "当前项目[{$rule->itemName}]在同一分组内，计费总数应" . $this->getNumErrorStr($ruleNum) . "，实际[{$num}]",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'item_ids' => $itemIds
                            ],
                        ];
                    }
                }
                break;
            default:
                throw new IRMIException('当前规则不支持此参数[num]配置');
                break;
        }
        return $this->getResult(302, '超医保支付范围', $errors);
    }
}
