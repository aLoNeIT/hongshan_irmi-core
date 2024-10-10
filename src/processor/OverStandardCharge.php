<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

class OverStandardCharge extends Base implements IDetectProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
                case 1:
                    $jResult = $this->detectOverHospitalizationDays($medicalRecord, $rule);
                    break;
                case 2:
                    $jResult = $this->detectOverDailyCharge($medicalRecord, $rule);
                    break;
                case 3:
                    $jResult = $this->detectOverDailyChargeWithOtherItem($medicalRecord, $rule);
                    break;
                case 4:
                    $jResult = $this->detectOverChargeWithOtherItem($medicalRecord, $rule);
                    break;
                default:
                    $jResult = $this->jsonTable->success();
                    break;
            }
            return $jResult;
        } catch (IRMIException $ex) {
            return $this->jsonTable->error($ex->getMessage(), 1);
        }
    }
    /**
     * 检测超过住院天数
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectOverHospitalizationDays(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 先检查是否是住院数据
        if (2 != $medicalRecord->visitType) {
            return $this->jsonTable->success();
        }
        // 检查是否有排除的科室
        $excludeBranch = $rule->options['exclude_branch'] ?? [];
        if (\in_array($medicalRecord->branchCode, $excludeBranch)) {
            return $this->jsonTable->success();
        }
        // 获取该规则指定的医保项目数据，计算数量之和
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        /** @var MedicalInsuranceItem[] $miItem */
        $miItem = $miItemSet[$rule->itemCode];
        $totalNum = \array_reduce(
            $miItem,
            function ($total, MedicalInsuranceItem $item) use ($rule) {
                // 检查该项目收费时间是否在规则的有效期内
                return $total + $this->checkDateRange($item->time, $rule) ? $item->num : 0;
            }
        );
        // 计算系数
        $coefficient = $rule->options['coefficient'] ?? 1;
        if ($totalNum > $medicalRecord->inDays * $coefficient) {
            // 超标准收费
            $errors[] = [
                'msg' => "当前项目[{$rule->itemName}]超过住院天数，系数[{$coefficient}]",
                'data' => [
                    'item' => $miItem
                ]
            ];
        }
        return empty($errors) ? $this->jsonTable->success()
            : $this->jsonTable->error("超标准收费", 201, [
                'rule' => $this->getRuleInfo($rule),
                'errors' => $errors
            ]);
    }

    /**
     * 检测当前项目当日收费超过X（元、次）
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectOverDailyCharge(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $miItem = $miItemSet[$rule->itemCode];
        $varName = 'cash';
        $unit = '元';
        switch ($rule->options['unit']) {
            case 'num':
                $varName = 'num';
                $unit = '次';
                break;
            default:
                $varName = 'cash';
                $unit = '元';
                break;
        }
        // 遍历该项目下每日数据，根据单位进行数据汇总
        $dailyData = [];
        \array_walk($miItem, function (MedicalInsuranceItem $item) use (&$dailyData, $varName, $rule) {
            $dailyData[$item->time] = ($dailyData[$item->time] ?? 0) + $this->checkDateRange($item->time, $rule) ? $item->$varName : 0;
        });
        // 循环判断是否存在某一天数据不符合要求
        $errors = [];
        foreach ($dailyData as $date => $num) {
            $dateStr = date('Y-m-d', $date);
            if ($num > $rule->options['num']) {
                $errors[] = [
                    'msg' => "[{$dateStr}]当日，当前项目[{$rule->itemName}]当日收费超过[{$num}{$unit}]",
                    'data' => [
                        'date' => $date,
                        'item' => $miItem,
                    ]

                ];
            }
        }
        return empty($errors) ? $this->jsonTable->success()
            : $this->jsonTable->error("超标准收费", 202, [
                'rule' => $this->getRuleInfo($rule),
                'errors' => $errors
            ]);
    }

    /**
     * 检测与其他项目当天同时检测，其他项目未按X%收费
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectOverDailyChargeWithOtherItem(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 先获取临时数据，找到该项目所有日期数据
        /** @var MedicalInsuranceItem[] $tmpMiItemSet */
        $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        /** @var MedicalInsuranceItem[] $miItem */
        $tmpMiItem = $tmpMiItemSet[$rule->itemCode];
        // 获取医保项目集合，key是date时间戳
        $miItemSet = $medicalRecord->medicalInsuranceSet;
        // 获取规则中的折扣配置
        $discountItems = $rule->options['discount_items'] ?? [];
        // 遍历该数据
        foreach ($tmpMiItem as $tmpItem) {
            // 按照该项目当前日期，在病历信息的项目集合中查询当天所有的项目数据
            // code 是项目编码，val是项目数据
            /** @var MedicalInsuranceItem $val */
            foreach ($miItemSet[$tmpItem->date] as $code => $val) {
                // 判断code编码是否是规则中的配置项
                if (isset($discountItems[$code])) {
                    $ratio = (string)$discountItems[$code]['ratio'];
                    // 计算第二个项目是否按照指定折扣收费
                    if (bcmul((string)$val->price, (string) $ratio) != $val->cash) {
                        $percent = bcmul($ratio, '100');
                        $dateStr = date('Y-m-d', $tmpItem->date);
                        // 写入错误信息
                        $errors[] = [
                            'msg' => "[{$dateStr}]当日，当前项目[{$rule->itemName}]与其他项目[{$val->name}]同时收费，其他项目[{$val->name}]未按[{$percent}]%收费",
                            'data' => [
                                'date' => $tmpItem->date,
                                'item' => $val,
                            ]
                        ];
                    }
                }
            }
        }
        return empty($errors) ? $this->jsonTable->success()
            : $this->jsonTable->error('超标准收费', 203, [
                'rule' => $this->getRuleInfo($rule),
                'errors' => $errors
            ]);
    }
    /**
     * 当前项目收费超X次，超出部分未按Y%收费
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable
     */
    protected function detectOverChargeWithOtherItem(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $miItem = $miItemSet[$rule->itemCode];
        // 遍历获取当前规则数据，进行数量和费用汇总
        $totalNum = 0;
        $totalCash = 0;
        $totalPrice = 0;
        \array_walk($miItem, function (MedicalInsuranceItem $item) use (&$totalNum, &$totalCash, &$totalPrice) {
            $totalNum += $item->num;
            $totalCash = bcadd((string)$item->totalCash, (string)$totalCash);
            $totalPrice = bcadd((string)$item->price, (string)$totalPrice);
        });
        // 检查是否超数量
        if ($totalNum > $rule->options['num']) {
            if (isset($rule->options['ratio'])) {
                // 配置了比例，说明超出部分要按照折扣计算
                // 平均价格
                $avgPrice = bcdiv((string)$totalPrice, (string)$totalNum);
                // 超出的数量
                $overNum = $totalNum - $rule->options['num'];
                // 超出的标准价格
                $overPrice = bcmul((string)$overNum, (string)$avgPrice);
                // 超出部分的应收费用
                $overCash = bcmul((string)$overPrice, (string)$rule->options['ratio']);
                // 正常部分的应收费用
                $normalCash = bcmul((string)$avgPrice, (string)$rule->options['num']);
                // 标准应收费用
                $standardCash = bcadd((string)$normalCash, (string)$overCash);
                if (1 == bccomp((string)$totalCash, (string)$standardCash)) {
                    // 实际收费金额大于标准应收费用，则存在超收情况
                    $diffCash = bcsub((string)$totalCash, (string)$standardCash);
                    $percent = bcmul((string)$rule->options['ratio'], '100');
                    $errors[] = [
                        'msg' => "当前项目[{$rule->itemName}]应收费用[{$standardCash}]，实收费用[{$totalCash}]，总计费量[{$totalNum}]，超出部分未按照[{$percent}%]收费，超收费用[{$diffCash}]",
                        'data' => [
                            'rule' => $this->getRuleInfo($rule),
                            'item' => $miItem,
                            'standard_cash' => $standardCash,
                            'total_cash' => $totalCash,
                            'over_cash' => $overCash,
                            'diff_cash' => $diffCash,
                            'over_num' => $overNum,
                            'avg_price' => $avgPrice,
                            'over_price' => $overPrice,
                            'normal_cash' => $normalCash,
                        ]
                    ];
                }
            } else {
                // 未配置比例，则说明只要超出了就超标准收费
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]总计费量[{$totalNum}]，超过限定数量[{$rule->options['num']}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'item' => $miItem,
                    ]
                ];
            }
        }
        return empty($errors) ? $this->jsonTable->success()
            : $this->jsonTable->error('超标准收费', 204, [
                'rule' => $this->getRuleInfo($rule),
                'errors' => $errors
            ]);
    }
    /**
     * 当前项目计费量超过指定量，可选配置如下  
     * - 检测超过指定总价、总数量、住院天数、其他某项目数量；
     * - 合并其他项目数据
     * - 按日检测
     * - 超过次数按折扣计费
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectOverNum(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $miItem = $miItemSet[$rule->itemCode];
        $varName = 'cash';
        $unit = '元';
        switch ($rule->options['unit_type']) {
            case 'cash':
                $varName = 'cash';
                $unit = '元';
                break;
            default:
                $varName = 'num';
                $unit = '次';
                break;
        }
        // 判断该规则是按日，还是周期
        $detectType = $rule->options['detect_type'] ?? 2;
        // 存储待检测的数据，如果是按日检测，key是日期，如果是按周期，key是'all'
        $itemData = [];
        // 遍历当前项目数据，进行汇总
        \array_walk($miItem, function (MedicalInsuranceItem $item) use (&$itemData, $detectType, $varName) {
            $key = 1 == $detectType ? $item->date : 'all';
            $totalPrice = bcmul((string)$item->price, (string)$item->num);
            $itemData[$key] = [
                'total_num' => ($itemData[$key]['total_num'] ?? 0) + $item->$varName,
                'total_cash' => \bcadd((string)($itemData[$key]['total_cash'] ?? 0), (string)$item->totalCash),
                'total_price' => \bcadd((string)($itemData[$key]['total_price'] ?? 0), (string)$totalPrice),
            ];
        });
        $cmItemData = [];
        if (isset($rule->options['combine_items'])) {
            // 存在合并计数的项目
            \array_walk($rule->options['combine_items'], function ($code) use (&$cmItemData, $detectType, $varName, $miItemSet) {
                // 拥有每个项目编码，从集合中取出对应项目数据
                $cbItem = $miItemSet[$code] ?? [];
                \array_walk($cbItem, function (MedicalInsuranceItem $item) use (&$cmItemData, $detectType, $varName) {
                    $key = 1 == $detectType ? $item->date : 'all';
                    // 暂时不考虑其他单价、金额之类，若后续需要，可增加combine_type，按位运算，1、2、4、8
                    $cmItemData[$key] = [
                        'total_num' => ($cmItemData[$key]['total_num'] ?? 0) + $item->$varName
                    ];
                });
            });
        }
        // 循环判断是否存在某一天/全部数据不符合要求
        foreach ($itemData as $date => $item) {
            // 获取规则中配置的数量
            $ruleNum = $this->getRuleOptionNum($medicalRecord, $rule);
            // 根据配置确定当前计算总量是否需要加上合并项目的数量
            $totalNum = $item['total_num'] + isset($rule->options['combine_items']) ? ($cmItemData[$date]['total_num'] ?? 0) : 0;
            if ($totalNum > $ruleNum) {
                // 当前项目总数量大于指定的数量
                if ($item['total_num'] > $ruleNum && isset($rule->options['ratio'])) {
                    // 这里因为要计算的是当前项目的收费比例，所以数量不能使用合并的数量
                    // 若配置了费用比例，则代表需计算超限部分是否合规
                    $ratio = $rule->options['ratio'];
                    // 平均价格
                    $avgPrice = bcdiv((string)$item['total_price'], (string)$item['total_num']);
                    // 超出的数量
                    $overNum = $item['total_num'] - $ruleNum;
                    // 超出的标准价格
                    $overPrice = bcmul((string)$overNum, (string)$avgPrice);
                    // 超出部分的应收费用
                    $overCash = bcmul((string)$overPrice, (string)$rule->options['ratio']);
                    // 正常部分的应收费用
                    $normalCash = bcmul((string)$avgPrice, (string)$ruleNum);
                    // 标准应收费用
                    $standardCash = bcadd((string)$normalCash, (string)$overCash);
                    if (1 == bccomp((string)$item['total_cash'], (string)$standardCash)) {
                        // 实际收费金额大于标准应收费用，则存在超收情况
                        $diffCash = bcsub((string)$item['total_cash'], (string)$standardCash);
                        $percent = bcmul((string)$ratio, '100');
                        $errors[] = [
                            'msg' => "当前项目[{$rule->itemName}]应收费用[{$standardCash}]，实收费用[{$item['total_cash']}]，总计费量[{$item['total_num']}]，超出部分未按照[{$percent}%]收费，超收费用[{$diffCash}]",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'item' => $miItem,
                                'standard_cash' => $standardCash,
                                'total_cash' => $item['total_cash'],
                                'over_cash' => $overCash,
                                'diff_cash' => $diffCash,
                                'over_num' => $overNum,
                                'avg_price' => $avgPrice,
                                'over_price' => $overPrice,
                                'normal_cash' => $normalCash,
                            ]
                        ];
                        // 继续下一条数据计算
                        continue;
                    }
                }
                // 无其他选项，单纯比较超数量要求
                $dateStr = 'all' == $date ? '' : '[' . date('Y-m-d', (int)$date) . ']当日，';
                $errors[] = [
                    'msg' => $dateStr . "当前项目[{$rule->itemName}]的计费数量[{$totalNum}{$unit}]超过[{$ruleNum}{$unit}]",
                    'data' => [
                        'date' => $date,
                        'item' => $miItem,
                    ]
                ];
            }
        }
        return empty($errors) ? $this->jsonTable->success()
            : $this->jsonTable->error("超标准收费", 202, [
                'rule' => $this->getRuleInfo($rule),
                'errors' => $errors
            ]);
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
                    $result = \array_reduce($otherItem, function ($carry, MedicalInsuranceItem $item) use (&$date) {
                        // 汇总计算，如果是计算所有值，则直接汇总，否则只汇总指定日期
                        $carry += 'all' == $date ? $item->num : ($date == $item->date ? $item->num : 0);
                    }, 0);
                    break;
                default: // 默认直接读取value属性
                    $result = $rule->options['num']['value'];
                    break;
            }
        }
        return $result;
    }
}
