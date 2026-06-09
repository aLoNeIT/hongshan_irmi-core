<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\processor\Base as BaseProcessor;
use hongshanhealth\irmi\struct\{
    MedicalRecord,
    IRMIRule,
    MedicalInsuranceItem
};
use hongshanhealth\irmi\Util;

/**
 * 医保项目计算器基类
 */
class Base extends BaseProcessor
{

    /**
     * 获取规则中配置的数量
     *
     * @param MedicalRecord $medicalRecord 病历信息
     * @param IRMIRule $rule 规则对象
     * @return array 返回获取到的数量和类型[num,type]
     */
    protected function getRuleOptionNum(
        MedicalRecord $medicalRecord,
        IRMIRule $rule
    ): array {
        $result = null;
        $type = $rule->options['num']['type'] ?? 1;
        if (\is_scalar($rule->options['num'])) {
            $result = $rule->options['num'];
        } else {
            // 复杂结构，需要判断
            switch ($type) {
                case 4:
                case 5:
                case 1:
                    $result = $rule->options['num']['value'];
                    break;
                case 2: // 基于病例项目中的指定属性的值
                    $property = Util::camel($rule->options['num']['property']);
                    // 计算系数
                    $coefficient = $rule->options['num']['coefficient'] ?? 1;
                    $result = (int)\bcmul((string)$medicalRecord->$property, (string)$coefficient);
                    break;
                case 3: // 基于另外一个项目的数量
                    $detectType = $rule->options['detect_type'] ?? 2;
                    $varName = $rule->options['unit_type'] ?? 'num';
                    if (1 == $detectType) {
                        throw new IRMIException('暂不支持基于另一个项目数量按天检测');
                    }
                    // 继续查询指定项目数据
                    $otherItem = $miItemSet[$rule->options['num']['item_code']] ?? [];
                    $result = \array_reduce(
                        $otherItem,
                        function ($carry, MedicalInsuranceItem $item) use ($varName) {
                            // 汇总计算，如果是计算所有值，则直接汇总，否则只汇总指定日期
                            $num = $item->$varName;
                            $carry = \bcadd($carry, (string)($num ?: 0), 4);
                        },
                        '0'
                    );
                    break;
                default: // 默认直接读取value属性
                    throw new IRMIException("无效的规则属性[num.type]配置");
                    break;
            }
        }
        return [\is_null($result) ? null : (\is_array($result) ? $result : (float)$result), $type];
    }
    /**
     * 获取数量的错误字符串
     *
     * @param array|float|integer $num 数量
     * @return string 数量错误字符串
     */
    public function getNumErrorStr(array|float|int $num): string
    {
        if (\is_scalar($num)) {
            return "不超过[{$num}]";
        } else if (\is_array($num)) {
            $begin = $num[0] ?: '不限';
            $end = $num[1] ?: '不限';
            return "在[{$begin}-{$end}]范围内";
        } else {
            return '';
        }
    }
    /**
     * 对比实际数量是否符合规则数量要求
     *
     * @param float $num 项目数量
     * @param array|float $ruleNum 规则数量
     * @return boolean 是否符合规则数量要求
     */
    public function compareNum(float $num, array|float $ruleNum): bool
    {
        $result = false;
        if (\is_array($ruleNum)) {
            // 规则数量是数组，代表between
            [$min, $max] = $ruleNum;
            $result = (\is_null($min) || -1 != \bccomp((string)$num, (string)$min))
                && (\is_null($max) || 1 != \bccomp((string)$num, (string)$max));
        } else {
            $result = 1 !== \bccomp((string)$num, (string)$ruleNum);
        }
        return $result;
    }

    /**
     * 检查规则就诊类型是否匹配
     *
     * 若规则未配置就诊类型（visitType 为 0 或 null），则跳过校验直接返回 true；
     * 否则与病历中的 visitType 进行等值比较。
     *
     * @param MedicalRecord $medicalRecord 病例对象
     * @param IRMIRule $rule 规则对象
     * @param int[] $itemIds 关联项目ID列表
     * @return boolean|array 匹配返回 true，否则返回错误数组
     */
    protected function checkVisitType(MedicalRecord $medicalRecord, IRMIRule $rule, array $itemIds = []): bool|array
    {
        if (empty($rule->visitType)) {
            // 未配置就诊类型，跳过校验
            return true;
        }
        $errors = [];
        if ($medicalRecord->visitType != $rule->visitType) {
            $ruleVisitTypeName = 1 == $rule->visitType ? '门诊' : '住院';
            $visitTypeName = 1 == $medicalRecord->visitType ? '门诊' : '住院';
            $this->addErrors(
                $errors,
                $medicalRecord,
                "当前项目[{$rule->itemName}]适用于[{$ruleVisitTypeName}]，实际[{$visitTypeName}]",
                [
                    'item_ids' => $itemIds,
                ],
                $rule
            );
        }
        return empty($errors) ? true : $errors;
    }

    /**
     *  检查就诊科室
     *
     * @param MedicalRecord $medicalRecord 病例对象
     * @param IRMIRule $rule 规则对象
     * @return boolean|array 在排除列表中返回true，否则返回false
     */
    protected function checkIncludedBranch(MedicalRecord $medicalRecord, IRMIRule $rule): bool|array
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
            $this->addErrors(
                $errors,
                $medicalRecord,
                "当前项目[{$rule->itemName}]未由指定包含科室开具",
                [
                    'item_ids' => $this->getMedicalItemIdByRule($medicalRecord, $rule)
                ],
                $rule
            );
        } else if (!$included && $result) {
            $this->addErrors(
                $errors,
                $medicalRecord,
                "当前项目[{$rule->itemName}]不可由指定排除科室开具",
                [
                    'item_ids' => $this->getMedicalItemIdByRule($medicalRecord, $rule)
                ],
                $rule
            );
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
        // 获取itemCollection中的项目
        $itemCollectionType = $includedItems['collection_type'] ?? null;
        $itemCollection = $includedItems['collection'] ?? [];
        if (!\is_null($itemCollectionType)) {
            // 类型为null，则说明collection中为字典中的分组号
            $ruleSet = $rule->getIRMIRuleSet();
            $collection = [];
            foreach ($itemCollection as $code => $config) {
                // 获取字典中指定类型及分组编码下的数据
                $collection = [
                    ...$collection,
                    ...\array_fill_keys($ruleSet->getDict($itemCollectionType, (string) $code), $config)
                ];
            }
            // 删除掉当前规则的项目，避免后续计算时候重复处理
            unset($collection[$rule->itemCode]);
            $itemCollection = $collection;
        }
        // 获取临时数据，同时根据规则有效期进行过滤
        $tmpMiItemSet = \array_map(function (array $items) use ($rule) {
            return $this->filterMIItemByDateRange($items, $rule);
        }, $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE));
        // 循环判断，包含的项目存在 当天或全部或其他 匹配条件
        switch ($timeType) {
            case 1: // 按天匹配
                $currItems = $tmpMiItemSet[$rule->itemCode];
                foreach ($currItems as $miItem) {
                    $date = $miItem->date;
                    $dateMiItems = $medicalRecord->medicalInsuranceSet[$date];
                    // 交集计算看当天是否有包含内的项目
                    $itemKeys = \array_keys($itemCollection);
                    $intersectItems = \array_intersect($itemKeys, \array_keys($dateMiItems));
                    $dateStr = date('Y-m-d', $date);
                    if ($included && empty($intersectItems)) {
                        // 未匹配到必须包含的项目
                        $this->addErrors(
                            $errors,
                            $medicalRecord,
                            "当前项目[{$rule->itemName}]在[{$dateStr}]当天未与指定包含项目同时收费",
                            [
                                'date' => $date,
                                'include_items' => $itemKeys,
                                'item_ids' => $this->getMedicalItemId($dateMiItems[$rule->itemCode])
                            ],
                            $rule
                        );
                    } else if (!$included && !empty($intersectItems)) {
                        // 此处遍历交集编码，然后到规则配置中查询是否有num配置
                        foreach ($intersectItems as $code) {
                            $config = $itemCollection[$code];
                            if (isset($config['combine_items'])) {
                                // 排除项目有组合项校验，若组合项中任一项目存在，则跳过
                                $intersectItems = \array_intersect(\array_keys($dateMiItems), $config['combine_items']);
                                if (!empty($intersectItems)) {
                                    // 匹配到组合项目，则跳过
                                    continue;
                                }
                            }
                            if (isset($config['num'])) {
                                // 汇总获取项目数量
                                $totalNum = \array_reduce(
                                    $dateMiItems[$code],
                                    function ($carry, MedicalInsuranceItem $item) {
                                        return \bcadd($carry, (string)($item->num ?: 0));
                                    },
                                    '0'
                                );
                                if ($totalNum < $config['num']) {
                                    continue;
                                }
                            }
                            // 循环写入错误信息
                            $this->addErrors(
                                $errors,
                                $medicalRecord,
                                "当前项目[{$rule->itemName}]在[{$dateStr}]当天与指定排除项目同时收费",
                                [
                                    'date' => $date,
                                    'exclude_item_code' => $code,
                                    'item_ids' => $this->getMedicalItemId($dateMiItems[$rule->itemCode])
                                ],
                                $rule
                            );
                        }
                    }
                }
                break;
            case 2: // 全部
                // 循环 包含 或 排除 依次判断是否错误
                $existed = null; // 是否找到指定内容
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
                                // 排除项目有组合项校验，若组合项中任一项目存在，则跳过
                                $intersectItems = \array_intersect(\array_keys($tmpMiItemSet), $config['combine_items']);
                                if (!empty($intersectItems)) {
                                    // 匹配到组合项目，则跳过
                                    continue;
                                }
                            }
                            // 汇总计算数组内指定字段的值
                            $totalNum = \array_reduce(
                                $tmpMiItemSet[$code],
                                function ($carry, MedicalInsuranceItem $miItem) {
                                    return bcadd($carry, (string)($miItem->num ?: 0));
                                },
                                '0'
                            );
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
                    $this->addErrors(
                        $errors,
                        $medicalRecord,
                        "当前项目[{$rule->itemName}]未与指定包含项目同时收费",
                        [
                            'item_ids' => $this->getMedicalItemIdByRule($medicalRecord, $rule),
                            'include_items' => \array_keys($itemCollection)
                        ],
                        $rule
                    );
                } else if (!$included && \is_array($existed) && !empty($existed)) {
                    // 未匹配到组合项目，则当前项目重复收费
                    $this->addErrors(
                        $errors,
                        $medicalRecord,
                        "当前项目[{$rule->itemName}]与指定排除项目同时收费",
                        [
                            'item_ids' => $this->getMedicalItemIdByRule($medicalRecord, $rule),
                            'exclude_items' => $existed
                        ],
                        $rule
                    );
                }
                break;
            case 10: // 同一小时
                /** @var MedicalInsuranceItem[] $currItems */
                $currItems = $tmpMiItemSet[$rule->itemCode];
                /** @var MedicalInsuranceItem $miItem */
                foreach ($currItems as $miItem) {
                    // 取出当天数据
                    $date = $miItem->date;
                    /** @var array<string,MedicalInsuranceItem[]> $dateMiItems */
                    $dateMiItems = $medicalRecord->medicalInsuranceSet[$date];
                    // 过滤当天数据，只保留与检测项目同一小时的项目数据
                    $hour = \date('H', $miItem->time);
                    $dateMiItems = \array_map(function (array $items) use ($hour) {
                        return \array_filter($items, function (MedicalInsuranceItem $item) use ($hour) {
                            return $hour == \date('H', $item->time);
                        });
                    }, $dateMiItems);
                    // 过滤掉没有数据的项目
                    $dateMiItems = \array_filter($dateMiItems, function (array $items) {
                        return !empty($items);
                    }, ARRAY_FILTER_USE_BOTH);
                    // 交集计算看当天是否有包含内的项目
                    $itemKeys = \array_keys($itemCollection);
                    $intersectItems = \array_intersect($itemKeys, \array_keys($dateMiItems));
                    $dateStr = date('Y-m-d', $date);
                    if ($included && empty($intersectItems)) {
                        // 未匹配到必须包含的项目
                        $this->addErrors(
                            $errors,
                            $medicalRecord,
                            "当前项目[{$rule->itemName}]在[$dateStr]当天的[{$hour}]时同一小时内未与指定包含项目同时收费",
                            [
                                'date' => $date,
                                'include_items' => $itemKeys,
                                'item_ids' => $this->getMedicalItemId([$miItem])
                            ],
                            $rule
                        );
                    } else if (!$included && !empty($intersectItems)) {
                        // 此处遍历交集编码，然后到规则配置中查询是否有num配置
                        foreach ($intersectItems as $code) {
                            $config = $itemCollection[$code];
                            if (isset($config['combine_items'])) {
                                // 排除项目有组合项校验，若组合项中任一项目存在，则跳过
                                $intersectItems = \array_intersect(\array_keys($dateMiItems), $config['combine_items']);
                                if (!empty($intersectItems)) {
                                    // 匹配到组合项目，则跳过
                                    continue;
                                }
                            }
                            if (isset($config['num'])) {
                                // 汇总获取项目数量
                                $totalNum = \array_reduce(
                                    $dateMiItems[$code],
                                    function ($carry, MedicalInsuranceItem $item) {
                                        return \bcadd($carry, (string)($item->num ?: 0));
                                    },
                                    '0'
                                );
                                if ($totalNum < $config['num']) {
                                    continue;
                                }
                            }
                            // 循环写入错误信息
                            $this->addErrors(
                                $errors,
                                $medicalRecord,
                                "当前项目[{$rule->itemName}]在[$dateStr]当天的[{$hour}]时同一小时内与指定排除项目同时收费",
                                [
                                    'date' => $date,
                                    'exclude_item_code' => $code,
                                    'item_ids' => $this->getMedicalItemId([$miItem])
                                ],
                                $rule
                            );
                        }
                    }
                }
                break;
            case 3: // 时间范围
                /** @var MedicalInsuranceItem[] $currItems */
                $currItems = $tmpMiItemSet[$rule->itemCode];
                $timeOffset = $includedItems['time_offset'] ?? [null, null];
                foreach ($currItems as $miItem) {
                    // 根据检索项目，获取其他在指定范围区间的数据
                    // 循环 包含 或 排除 依次判断是否错误
                    $existed = null; // 是否找到指定内容
                    $beginTime = \is_null($timeOffset[0]) ? null : $miItem->time + $timeOffset[0];
                    $endTime = \is_null($timeOffset[1]) ? null : $miItem->time + $timeOffset[1];
                    foreach ($itemCollection as $code => $config) {
                        // 检测指定项目是否在指定时间范围内存在
                        if ($included) {
                            // 遍历关联项目数据
                            foreach ($tmpMiItemSet[$code] ?? [] as $tmpMiItem) {
                                if (!\is_null($beginTime) && $tmpMiItem->time < $beginTime) {
                                    // 起始时间为有效值时，关联项目时间小于起始时间，则跳过    
                                    continue;
                                }
                                if (!\is_null($endTime) && $tmpMiItem->time > $endTime) {
                                    // 结束时间为有效值时，关联项目时间大于结束时间，则跳过
                                    continue;
                                }
                                $existed = true;
                                break;
                            }
                            // 指定时间范围内匹配到了包含项目，则通过校验
                            if ($existed) {
                                break;
                            }
                        } else {
                            $totalNum = 0;
                            // 对当前排除项目进行过滤，筛选有效时间段内的数据
                            $excludeMiItemSet = \array_filter(
                                $tmpMiItemSet[$code] ?? [],
                                function (MedicalInsuranceItem $tmpMiItem) use ($beginTime, $endTime) {
                                    return (\is_null($beginTime) || $tmpMiItem->time >= $beginTime)
                                        && (\is_null($endTime) || $tmpMiItem->time <= $endTime);
                                }
                            );
                            if (!empty($excludeMiItemSet)) {
                                // 不为空，说明存在指定时间段内排除项目数据
                                // 进一步检测排除的组合项目，组合项目存在有效数据，则当前排除规则跳过
                                $combineItemExisted = false;
                                foreach ($config['combine_items'] ?? []  as $combineCode) {
                                    $combineMiItemSet = \array_filter(
                                        $tmpMiItemSet[$combineCode] ?? [],
                                        function (MedicalInsuranceItem $tmpMiItem) use ($beginTime, $endTime) {
                                            return (\is_null($beginTime) || $tmpMiItem->time >= $beginTime)
                                                && (\is_null($endTime) || $tmpMiItem->time <= $endTime);
                                        }
                                    );
                                    if (!empty($combineMiItemSet)) {
                                        // 当前排除项目的存在有效联合项目，当前排除规则跳过
                                        $combineItemExisted = true;
                                        break;
                                    }
                                }
                                if ($combineItemExisted) {
                                    // 当前排除项目存在有效联合项目，当前排除规则跳过    
                                    continue;
                                }
                                if (isset($config['num'])) {
                                    // 汇总获取项目数量
                                    $totalNum = (int)\array_reduce(
                                        $excludeMiItemSet,
                                        function ($carry, MedicalInsuranceItem $item) {
                                            return \bcadd($carry, (string)($item->num ?: 0));
                                        },
                                        '0'
                                    );
                                    if ($totalNum < $config['num']) {
                                        continue;
                                    }
                                }
                                $existed[] = $code;
                            }
                        }
                    }
                    $beginTimeStr = $beginTime ? date('Y-m-d H:i:s', $beginTime) : null;
                    $endTimeStr = $endTime ? date('Y-m-d H:i:s', $endTime) : null;
                    $timeStr = '';
                    if (!\is_null($beginTimeStr) && !\is_null($endTimeStr)) {
                        $timeStr = "在时间范围区间[{$beginTimeStr}]至[{$endTimeStr}]内";
                    } else if (\is_null($beginTimeStr) && !\is_null($endTimeStr)) {
                        $timeStr = "在[{$endTimeStr}]前";
                    } else if (!\is_null($beginTimeStr) && \is_null($endTimeStr)) {
                        $timeStr = "在[{$beginTimeStr}]后";
                    }
                    // 最后统一计算
                    if ($included && true !== $existed) {
                        // 要求包含的项目不存在
                        $this->addErrors(
                            $errors,
                            $medicalRecord,
                            "当前项目[{$rule->itemName}]{$timeStr}未与指定包含项目同时收费",
                            [
                                'item_ids' => $this->getMedicalItemId([$miItem]),
                                'include_items' => \array_keys($itemCollection),
                                'time_range' => [
                                    'begin_time' => $beginTime,
                                    'end_time' => $endTime
                                ]
                            ],
                            $rule
                        );
                    } else if (!$included && \is_array($existed) && !empty($existed)) {
                        // 未匹配到组合项目，则当前项目重复收费
                        $this->addErrors(
                            $errors,
                            $medicalRecord,
                            "当前项目[{$rule->itemName}]{$timeStr}与指定排除项目同时收费",
                            [
                                'item_ids' => $this->getMedicalItemId([$miItem]),
                                'exclude_items' => $existed,
                                'time_range' => [
                                    'begin_time' => $beginTime,
                                    'end_time' => $endTime
                                ]
                            ],
                            $rule
                        );
                    }
                }
                break;
            case 4: // 同一处方
                /** @var MedicalInsuranceItem[] $currItems */
                $currItems = $tmpMiItemSet[$rule->itemCode];
                /** @var MedicalInsuranceItem $miItem */
                foreach ($currItems as $miItem) {
                    // 获取分组号
                    $groupCode = $miItem->groupCode;
                    $find = false;
                    foreach ($itemCollection as $code => $config) {
                        if (!isset($tmpMiItemSet[$code])) {
                            continue;
                        }
                        // 查看是否存在同一分组数据
                        $groupItems = \array_filter($tmpMiItemSet[$code], function (MedicalInsuranceItem $item) use ($groupCode) {
                            return $item->groupCode === $groupCode;
                        });
                        if (!empty($groupItems)) {
                            // 找到同一分组数据的数据，根据是包含还是排除来处理
                            if ($included) {
                                // 包含项有任意一个则符合
                                $find = true;
                                break;
                            } else {
                                // 排除项有任意一个则不符合
                                $this->addErrors(
                                    $errors,
                                    $medicalRecord,
                                    "当前项目[{$rule->itemName}]不能与指定排除项目同时收费",
                                    [
                                        'exclude_item_code' => $code,
                                        'item_ids' => $this->getMedicalItemId([
                                            $miItem
                                        ])
                                    ],
                                    $rule
                                );
                            }
                        }
                    }
                    // 未发现包含项目
                    if ($included && !$find) {
                        $this->addErrors(
                            $errors,
                            $medicalRecord,
                            "当前项目[{$rule->itemName}]必须与指定包含项目同时收费",
                            [
                                'exclude_item_code' => $code,
                                'item_ids' => $this->getMedicalItemId([$miItem])
                            ],
                            $rule
                        );
                    }
                }
                break;
            default:
                throw new IRMIException("不支持的时间类型[{$timeType}]");
                break;
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
                // 获取指定日期所属周的第一天
                $weekFirstDay = strtotime('Monday this week', $firstDay);
                $result = strtotime("+{$intervalNum} week", $weekFirstDay);
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
                // 将日期再格式化为当天0点
                $dayOfZero = strtotime(date('Y-m-d 00:00:00', $firstDay));
                $result = strtotime("+{$intervalNum} day", $dayOfZero);
                break;
        }
        // 最后统一减少1秒
        return $result - 1;
    }
    /**
     * 根据时间类型获取医保项目
     *
     * @param array<string,MedicalInsuranceItem[]> $tmpMiItemSet 临时数据集合
     * @param int $timeType 时间类型
     * @return MedicalInsuranceItem[] 医保项目集合
     */
    protected function getMIItemByTimeType(array $tmpMiItemSet, int $timeType): array
    {
        // 获取以项目编码为key，value为该项目数据集合的临时数据
        // $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        $result = [];
        /** @var MedicalInsuranceItem[] $collection */
        foreach ($tmpMiItemSet as $code => $collection) {
            $result[$code] = \array_filter($collection, function (MedicalInsuranceItem $item) use ($timeType) {
                return 1 == $timeType;
            });
        }
        return $result;
    }
}
