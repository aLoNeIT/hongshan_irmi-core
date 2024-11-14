<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};

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
                    'msg' => "当前项目[{$rule->name}]适用于[{$ruleVisitTypeName}]，实际[{$visitTypeName}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule)
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
                    'msg' => "当前项目[{$rule->name}]限定年龄未在[{$ageMinStr},{$ageMaxStr}]范围内，实际年龄[{$medicalRecord->age}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule)
                    ]
                ];
            }
        }

        // 配置了总天数
        if (isset($rule->options['total_days'])) {
            $totalDays = $rule->options['total_days'];
            $dates = [];
            \array_walk($currItems, function (MedicalInsuranceItem $item) use ($dates) {
                $dates[$item->date] = 1;
            });
            $days = \count($dates);
            if ($days > $totalDays) {
                $errors[] = [
                    'msg' => "当前项目[{$rule->name}]总时间应不超过[{$totalDays}]天，实际天数[{$days}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'total_days' => $totalDays,
                        'days' => $days
                    ]
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
                $totalSubNum = \count($currItems);
                if ($totalSubNum > $periodNum) {
                    $errors = [
                        'msg' => "当前项目[{$rule->name}]次数应不超过[{$periodSubNum}]次，实际次数[{$totalSubNum}]",
                        'data' => [
                            'rule' => $this->getRuleInfo($rule),
                            'total_sub_num' => $totalSubNum,
                            'num' => $periodNum
                        ]
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
                    $dateNum[$item->date] = ($dateNum[$item->date] ?? 0) + 1;
                });
                // 进行排序
                if (!\sort($sortDate, SORT_NUMERIC)) {
                    continue;
                };
                // 开始从小到大进行处理，需要双重循环
                $i = 0;
                $j = 0;
                for ($i = 0; $i < count($sortDate); $i++) {
                    $rangeTotalNum = 0; // 区间内的总数量
                    $lastDay = $this->getLastDay($sortDate[$i], $periodNum, $periodType);
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
                        $firstDayStr = date('Y-m-d', $sortDate[$i]);
                        $lastDayStr = date('Y-m-d', $lastDay);
                        $errors[] = [
                            'msg' => "当前项目[{$rule->name}]在[{$firstDayStr}]至[$lastDayStr]的[{$periodNum}]天内，次数应不超过[{$periodSubNum}]次，实际次数[{$rangeTotalNum}]",
                            'data' => [
                                'rule' => $this->getRuleInfo($rule),
                                'total_sub_num' => $rangeTotalNum,
                                'first_day' => $sortDate[$i],
                                'last_day' => $lastDay
                            ]
                        ];
                    }
                }
            }
        }
        // 同时支付校验
        $result = $this->checkIncludedItems($medicalRecord, $rule);
        if (true !== $result) {
            $errors = \array_merge($errors, $result);
        }
        return $this->getResult(300, '超医保支付范围', $errors);
    }
    /**
     * 指定费用收费需要间隔指定天数
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return JsonTable
     * 
     * @deprecated all
     */
    protected function detectInterval(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 配置了间隔天数
        if (isset($rule->options['date_interval'])) {
            // 获取医保项目集合
            $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
            // 获取当前项目数据集合
            /** @var MedicalInsuranceItem[] $miItem */
            $currItems = $this->filterMIItemByDateRange($miItemSet[$rule->itemCode], $rule);
            [
                'num' => $intervalNum,
                'type' => $intervalType
            ] = $rule->options['interval_days'];
            $intervalDays = 0;
            switch ($intervalType) {
                case 2:
                    $intervalDays = $intervalNum * 30;
                    break;
                case 3:
                    $intervalDays = $intervalNum * 365;
                    break;
                default:
                    $intervalDays = $intervalNum;
                    break;
            }
            $firstDay = 0;
            $lastDay = 0;
            \array_walk($currItems, function (MedicalInsuranceItem $item) use (&$firstDay, &$lastDay) {
                $firstDay = \min($firstDay, $item->time);
                $lastDay = \max($lastDay, $item->time);
            });
            // 计算差值
            $diffDays = \bcdiv((string)($lastDay - $firstDay), '86400');
            if ($intervalDays > $diffDays) {
                // 前后间隔时间小于要求时间
                $errors[] = [
                    'msg' => "当前项目[{$rule->name}]两次项目间隔应不短于[{$intervalDays}]天，实际间隔[{$diffDays}]",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'diff_days' => $diffDays
                    ]
                ];
            }
        }
        return $this->jsonTable->success();
    }

    protected function detectNonMedicalInsurance(MedicalInsuranceItem $item, IRMIRule $rule): JsonTable
    {
        $errors = [];
        return $this->getResult(1, '错误', $errors);
    }
}
