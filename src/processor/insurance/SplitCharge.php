<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

/**
 * 分解收费处理器
 * 检测项目清单中同时存在指定的N个项目的情况
 */
class SplitCharge extends Base implements IDetectInsuranceProcessor
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
            throw $ex;
        }
    }

    /**
     * 通用检测分解收费
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     * @throws IRMIException
     */
    protected function detectCommon(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        /** @var array<string,MedicalInsuranceItem[]> $miItemSet */
        $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前规则涉及的项目编码集合
        $currItems = $this->filterMIItemByDateRange($tmpMiItemSet[$rule->itemCode] ?? [], $rule);
        $detectType = $rule->options['detect_type'] ?? 1;
        $combineItems = $rule->options['combine_items'] ?? [];
        if (empty($combineItems)) {
            // 非空，则需要检查项目是否存在
            throw new IRMIException('分解收费规则中combine_items不能为空');
        }
        // 添加当前项目到集合中
        $combineItems = [
            $rule->itemCode,
        ];
        // 根据检测方式决定处理逻辑
        if (1 == $detectType) {
            // 按天检测
            foreach ($currItems as $item) {
                $date = $item->date;
                $miItemCodeSet = \array_keys($medicalRecord->medicalInsuranceSet[$date]);
                // 交集计算，必须combine全部包含在miItemCodeSet中
                $diff = \array_diff($combineItems, $miItemCodeSet);
                if (empty($diff)) {
                    // 为空，说明$combineItems全部包含在$miItemCodeSet中
                    $dateStr = date('Y-m-d', $date);
                    $errors[] = [
                        'msg' => "当前项目[{$rule->itemName}]在[{$dateStr}]当天与指定联合项目同时存在",
                        'data' => [
                            'rule' => $this->getRuleInfo($rule),
                            'date' => $date,
                            'combine_items' => $combineItems,
                            'item_ids' => (function ($dateMiItemSet) use ($combineItems) {
                                $result = [];
                                foreach ($combineItems as $itemCode) {
                                    $result = [
                                        ...$result,
                                        ...$this->getMedicalItemId($dateMiItemSet[$itemCode])
                                    ];
                                }
                                return $result;
                            })($medicalRecord->medicalInsuranceSet[$date])
                        ]
                    ];
                }
            }
        } else if (2 == $detectType) {
            // 全部检测
            $miItemCodeSet = \array_keys($tmpMiItemSet);
            // 交集计算，必须combine全部包含在miItemCodeSet中
            $diff = \array_diff($combineItems, $miItemCodeSet);
            if (empty($diff)) {
                // 为空，说明$combineItems全部包含在$miItemCodeSet中
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]与指定联合项目同时存在",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule),
                        'combine_items' => $combineItems,
                        'item_ids' => (function ($miItemSet) use ($combineItems) {
                            $result = [];
                            foreach ($combineItems as $itemCode) {
                                $result = [
                                    ...$result,
                                    ...$this->getMedicalItemId($miItemSet[$itemCode])
                                ];
                            }
                            return $result;
                        })($tmpMiItemSet)
                    ]
                ];
            }
        }
        return $this->getResult(501, '分解收费', $errors);
    }
}
