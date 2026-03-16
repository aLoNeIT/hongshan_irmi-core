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
            // return $this->jsonTable->error($ex->getMessage(), 1, $ex->getTrace());
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
        $errTmpl = '';
        switch ($ruleNumType) {
            case 1:
            case 2:
            case 3:
                // 1、2、3 需要根据unit_type来确定比对的项目属性，然后不得超过num
                $unitType = $rule->options['unit_type'] ?? 'num';
                $varName = $unitType;
                switch ($unitType) {
                    case 'days':
                        $unitTypeStr = '天';
                        break;
                    case 'cash':
                        $unitTypeStr = '元';
                        break;
                    default:
                        $unitTypeStr = '次';
                        break;
                }
                // 判断该规则是按日，还是周期
                $detectType = $rule->options['detect_type'] ?? 2;
                // 存储待检测的数据，如果是按日检测，key是日期，如果是按周期，key是'all'
                $itemData = [];
                // 遍历当前项目数据，进行汇总
                \array_walk($currItems, function (MedicalInsuranceItem $item) use (&$itemData, $detectType, $varName) {
                    $key = 1 == $detectType ? $item->date : 'all';
                    $itemData[$key] = [
                        'total_num' => \bcadd((string)($itemData[$key]['total_num'] ?? 0), (string)($item->$varName ?: 0)),
                    ];
                });
                // 循环判断是否存在某一天/全部数据不符合要求
                foreach ($itemData as $date => $item) {
                    $errDateStr = 'all' == $date ? '' : '[' . date('Y-m-d', (int)$date) . ']当日，';
                    // 根据配置确定当前计算总量是否需要加上合并项目的数量
                    $totalNum = $item['total_num'];
                    if (!$this->compareNum((float)$totalNum, $ruleNum)) {
                        // 无其他选项，单纯比较超数量要求
                        $errors[] = [
                            'msg' => "{$errDateStr}当前项目[{$rule->itemName}]的计费数量[{$totalNum}{$unitTypeStr}]超过[{$ruleNum}{$unitTypeStr}]",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'date' => $date,
                                'item' => $currItems,
                                'item_ids' => 'all' == $date
                                    ? $this->getMedicalItemId($currItems)
                                    : $this->getMedicalItemId(
                                        \array_filter($currItems, function (MedicalInsuranceItem $item) use ($date) {
                                            return $date == $item->date;
                                        })
                                    )
                            ],
                        ];
                    }
                }
                break;
            case 4: // 群组中项目个数
                $errTmpl = '当前项目[{$ruleItemName}]在同一分组内，项目类别总数应[{$ruleErrorStr}]，实际[{$num}]';
            case 5: //群组中项目的计费总数，后续如果有需要再优化为根据价格之类
                $errTmpl = '当前项目[{$ruleItemName}]在同一分组内，计费总数应[{$ruleErrorStr}]，实际[{$num}]';
                // {"P123123":{"T000700200":[miitem1,miitem2],"T000700201":[miitem3]},"P123124":{"T000700202":[miitem4,miitem5]}}
                /** @var array<string, array<string, MedicalInsuranceItem[]>> $groupInfo */
                $groupInfo = $this->buildGroupInfo($medicalRecord, $currItems, $itemType);
                var_dump($groupInfo);
                // 以上处理完毕，开始进行数量判定
                foreach ($groupInfo as $code => $groupItems) {
                    $num = $this->calculateGroupNum($groupItems, $ruleNum, (int)$ruleNumType);
                    if (!$this->compareNum($num, $ruleNum)) {
                        $itemIds = [];
                        [$min, $max] = \is_array($ruleNum) ? $ruleNum : [0, $ruleNum];
                        if ($num < $min) {
                            // 项目数量少于最小值，则说明所有项目都有问题，直接提取留存的所有数据id
                            /** @var MedicalInsuranceItem[] $items */
                            \array_walk($groupItems, function (array $items, string $itemCode) use (&$itemIds) {
                                // 提取id
                                $ids = \array_map(function (MedicalInsuranceItem $item) {
                                    return $item->id;
                                }, $items);
                                $itemIds = [...$itemIds, ...$ids];
                            });
                        } else if ($num > $max) {
                            /** @var MedicalInsuranceItem[] $sortItems */
                            $sortItems = [];
                            \array_walk($groupItems, function (array $items, string $miCode) use (&$sortItems) {
                                $sortItems = [...$sortItems, ...$items];
                            });
                            // 使用usort，对$sortItems每个元素进行排序，按照时间排序
                            \usort($sortItems, function (MedicalInsuranceItem $a, MedicalInsuranceItem $b) {
                                return $a->time <=> $b->time;
                            });
                            // 项目数量超过最大值，则按照时间排序，提取最后超过部分的项目id
                            $itemIds = \array_filter(\array_map(function (MedicalInsuranceItem $item) {
                                return $item->id;
                            }, \array_slice($sortItems, $max)));
                        }
                        // 实际项目数量超过限定数量
                        $errors[] = [
                            'msg' => \str_replace(
                                ['{$ruleItemName}', '{$ruleErrorStr}', '{$num}'],
                                [$rule->itemName, $this->getNumErrorStr($ruleNum), $num],
                                $errTmpl
                            ),
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'item_ids' => $itemIds,
                            ],
                        ];
                    }
                }
                break;
            default:
                throw new IRMIException('当前规则不支持此参数[num.type]配置');
                break;
        }
        return $this->getResult(302, '超医保支付范围', $errors);
    }
    /**
     * 构建群组信息
     *
     * @param MedicalRecord $medicalRecord 医保记录
     * @param MedicalInsuranceItem[] $currItems 当前规则对应项目
     * @param string|null $itemType 项目类型
     * @return array<string, array<string, MedicalInsuranceItem[]>> 群组信息
     */
    private function buildGroupInfo(MedicalRecord $medicalRecord, array $currItems, string $itemType): array
    {
        // {"P123123":{"T000700200":[miitem1,miitem2],"T000700201":[miitem3]},"P123124":{"T000700202":[miitem4,miitem5]}}
        /** @var array<string, array<string, MedicalInsuranceItem[]>> $groupInfo */
        $groupInfo = [];
        // 根据规则对应的项目所属群组信息，先遍历获取同群组数据，整理到群组信息下
        foreach ($currItems as $currItem) {
            $groupCode = $currItem->groupCode;
            if ($groupCode) {
                // 有效的群组号，现在开始当前项目同天的数据
                // 这里要求同组数据必须在一天内
                $date = $currItem->date;
                $dateMiItems = $medicalRecord->medicalInsuranceSet[$date] ?? [];
                // code是项目编码，miItems是当前项目同天的所有项目数据集合
                foreach ($dateMiItems as $code => $miItems) {
                    // 将当前项目同天的同群组数据筛选出来
                    $groupItems = \array_filter($miItems, function (MedicalInsuranceItem $item) use ($groupCode, $itemType) {
                        return $item->groupCode == $groupCode
                            && (\is_null($itemType) || $item->type == $itemType);
                    });
                    // 过滤后不为空，则添加到群组信息下
                    if (!empty($groupItems)) {
                        // 获取记录的id集合
                        $items = $groupInfo[$groupCode][$code] ?? [];
                        // 将当前分组的项目id添加到集合中
                        $groupInfo[$groupCode][$code] = [
                            ...$items,
                            ...$groupItems
                        ];
                    }
                }
            }
        }
        return $groupInfo;
    }
    /**
     * 计算群组数量
     *
     * @param array<string, \hongshanhealth\irmi\struct\MedicalInsuranceItem[]> $groupItems 群组项目数据
     * @param integer $ruleNumType 规则数量类型
     * @return float 计算得到的数量
     */
    private function calculateGroupNum(array $groupItems, int $ruleNumType): float
    {
        switch ($ruleNumType) {
            case 4: // 群组内类别数
                $num = \count($groupItems);
                break;
            case 5: // 群组内项目总计费数量
                $num = 0.0;
                \array_walk($groupItems, function (array $items, string $code) use (&$num) {
                    $itemNum = \array_reduce($items, function ($carry, $item) {
                        // 累加项目计费数量
                        $carry = \bcadd($carry, (string)$item->num);
                        return $carry;
                    }, '0');
                    // 累加当前项目的计费金额
                    $num = (float)\bcadd((string)$num, $itemNum);
                });
                break;
            default:
                throw new IRMIException('当前规则不支持此参数[num.type]配置');
                break;
        }
        return (float)$num;
    }
}
