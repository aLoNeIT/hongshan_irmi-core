<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

/**
 * 超标准收费处理器
 */
class OverStandardCharge extends Base implements IDetectInsuranceProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
                case 1:
                    $jResult = $this->detectOverNum($medicalRecord, $rule);
                    break;
                case 2:
                    $jResult = $this->detectMultiItemDiscount($medicalRecord, $rule);
                    break;
                default:
                    $jResult = $this->jsonTable->success();
                    break;
            }
            return $jResult;
        } catch (IRMIException $ex) {
            return $this->jsonTable->error($ex->getMessage(), 1, $ex->getTrace());
        }
    }
    /**
     * 当前项目计费量超过指定量  
     * 可选配置如下  
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
        // 检查科室排除列表
        $result = $this->checkIncludedBranch($medicalRecord, $rule);
        if (true !== $result) {
            $errors = [
                ...$errors,
                ...$result
            ];
        }
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        /** @var MedicalInsuranceItem[] $miItem */
        $miItem = $this->filterMIItemByDateRange($miItemSet[$rule->itemCode], $rule);
        switch ($rule->options['unit_type'] ?? '') {
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
        \array_walk($miItem, function (MedicalInsuranceItem $item) use (&$itemData, $detectType, $varName, $rule) {
            $key = 1 == $detectType ? $item->date : 'all';
            $totalPrice = \bcmul((string)$item->price, (string)$item->num);
            $itemData[$key] = [
                'total_num' => ($itemData[$key]['total_num'] ?? 0) + $item->$varName,
                'total_cash' => \bcadd((string)($itemData[$key]['total_cash'] ?? 0), (string)$item->totalCash),
                'total_price' => \bcadd((string)($itemData[$key]['total_price'] ?? 0), (string)$totalPrice),
            ];
        });

        // 合并项目的计算数据
        $cmItemData = [];
        if (isset($rule->options['combine_items'])) {
            // 存在合并计数的项目
            \array_walk($rule->options['combine_items'], function ($code) use (&$cmItemData, $detectType, $varName, $miItemSet, $rule) {
                // 拥有每个项目编码，从集合中取出对应项目数据
                $cbItem = $this->filterMIItemByDateRange($miItemSet[$code] ?? [], $rule);
                /** @var MedicalInsuranceItem $item */
                foreach ($cbItem as $item) {
                    $key = 1 == $detectType ? $item->date : 'all';
                    // 暂时不考虑其他单价、金额之类，若后续需要，可增加combine_type，按位运算，1、2、4、8
                    $cmItemData[$key] = [
                        'total_num' => ($cmItemData[$key]['total_num'] ?? 0) + $item->$varName
                    ];
                }
            });
        }

        // 循环判断是否存在某一天/全部数据不符合要求
        foreach ($itemData as $date => $item) {
            // 获取规则中配置的数量
            $ruleNum = $this->getRuleOptionNum($medicalRecord, $rule);

            // 根据配置确定当前计算总量是否需要加上合并项目的数量
            $totalNum = $item['total_num'] + (isset($rule->options['combine_items']) ? ($cmItemData[$date]['total_num'] ?? 0) : 0);
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
                    $overCash = bcmul((string)$overPrice, (string)$ratio);
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
                        'rule' => $this->getRuleInfo($rule),
                        'date' => $date,
                        'item' => $miItem,
                    ]
                ];
            }
        }
        return $this->getResult(201, '超标准收费', $errors);
    }
    /**
     * 检测多项目同时存在的折扣费用
     *
     * @param MedicalRecord $medicalRecord 医保记录
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectMultiItemDiscount(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 处理时间后的待检测的数据
        $detectCurrItems = [];
        // 循环当前项目数据，循环过程中若按天检测，则数据归属到日期的key下，如果为所有数据，则归到all下面
        \array_walk($miItemSet[$rule->itemCode], function (MedicalInsuranceItem $miItem) use ($rule, &$detectCurrItems) {
            if ($this->checkDateRange($miItem->date, $rule)) {
                $key = 1 == $rule->options['detect_type'] ? $miItem->date : 'all';
                $detectCurrItems[$key][] = $miItem;
            };
        });
        // 整体检测出来有问题的数据，最后一步再进行费率检测
        // 循环待检测数据，根据key对折扣项目数据做处理
        /** @var MedicalInsuranceItem[] $currItem */
        foreach ($detectCurrItems as $date => $currItems) {
            // 每个元素都是一个对象，包含ratio和data
            $detectDisItems = [];
            $discountItems = $rule->options['discount_items'] ?? [];
            if ('all' == $date) {
                // 对范围内数据进行对比
                foreach ($discountItems as $code => $config) {
                    // 获取折扣项目相关数据
                    $disItems = $this->filterMIItemByDateRange($miItemSet[$code] ?? [], $rule);
                    if (!empty($disItems)) {
                        $detectDisItems[] = [
                            'ratio' => $config['ratio'],
                            'data' => $disItems
                        ];
                    }
                }
            } else {
                // 按天进行对比
                // key是项目编码，value是项目具体值
                $miDateItems = $medicalRecord->medicalInsuranceSet[$date];
                foreach ($discountItems as $code => $config) {
                    // 判断当天数据中是否存在相关项目
                    if (isset($miDateItems[$code])) {
                        $detectDisItems[] = [
                            'ratio' => $config['ratio'],
                            'data' => $miDateItems[$code]
                        ];
                    }
                }
            }
            // 判断同时存在的项目是否为空，非空则开始进行折扣计算
            if (!empty($detectDisItems)) {
                // 最后再对$ratioItems进行遍历，本次根据规则中的折扣目标来计算谁应该打折
                if (2 == $rule->options['discount_target']) {
                    // 自己打折
                    $ratio = $rule->options['ratio'];
                    \array_walk($currItems, function (MedicalInsuranceItem $item) use (&$errors, $ratio, $rule) {
                        // 规则中应该收的费用
                        $ruleCash = \bcmul((string)$item->price, (string)$ratio);
                        if ($item->cash > $ruleCash) {
                            // 实收费用大于折扣后费用，则认为超收
                            $percent = bcmul((string)$ratio, '100');
                            $errors[] = [
                                'msg' => "当前项目[{$item->name}]应按[{$percent}%]收取费用[{$ruleCash}]，实收费用[{$item->cash}]",
                                'data' => [
                                    'rule' => $this->getRuleInfo($rule),
                                    'item' => $item,
                                    'rule_cash' => $ruleCash,
                                    'cash' => $item->cash,
                                ]
                            ];
                        }
                    });
                } else {
                    // 1，其他项目打折
                    \array_walk($detectDisItems, function (array $disItem) use (&$errors, $rule) {
                        $ratio = $disItem['ratio'];
                        // 获取指定项目数据集合，
                        /** @var MedicalInsuranceItem[] $items */
                        $items = $disItem['data'];
                        // 遍历集合中每一个元素，进行折扣计算
                        foreach ($items as $item) {
                            // 规则中应该收的费用
                            $ruleCash = \bcmul((string)$item->price, (string)$ratio);
                            if ($item->cash > $ruleCash) {
                                // 实收费用大于折扣后费用，则认为超收
                                $percent = bcmul((string)$ratio, '100');
                                $errors[] = [
                                    'msg' => "当前项目[{$item->name}]应按[{$percent}%]收取费用[{$ruleCash}]，实收费用[{$item->cash}]",
                                    'data' => [
                                        'rule' => $this->getRuleInfo($rule),
                                        'item' => $item,
                                        'rule_cash' => $ruleCash,
                                        'cash' => $item->cash,
                                    ]
                                ];
                            }
                        }
                    });
                }
            }
        }
        return $this->getResult(202, '超标准收费', $errors);
    }
}
