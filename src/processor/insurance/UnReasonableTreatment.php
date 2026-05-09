<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\{Map as MapConst};
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;
use hongshanhealth\irmi\constant\Key;

/**
 * 不合理诊疗处理器
 */
class UnReasonableTreatment extends Base implements IDetectInsuranceProcessor
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
                case 2:
                    $jResult = $this->detectProperty($medicalRecord, $rule);
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
     * @param MedicalRecord $medicalRecord
     * @param IRMIRule $rule
     * @return JsonTable
     */
    protected function detectCommon(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 检查include、exclude规则
        $result = $this->checkIncludedItems($medicalRecord, $rule);
        if (true !== $result) {
            $errors = [
                ...$errors,
                /** @var array $result */
                ...$result
            ];
        }
        if (isset($rule->options['unit_type'])) {
            // 获取医保项目集合
            $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
            // 获取当前项目数据集合
            /** @var MedicalInsuranceItem[] $miItems */
            $miItems = $this->filterMIItemByDateRange($miItemSet[$rule->itemCode], $rule);
            $unitType = $rule->options['unit_type'];
            switch ($unitType) {
                case 'days':
                    $varName = 'days';
                    $unit = '天';
                    break;
                default:
                    throw new IRMIException("不合理诊疗计算器不支持[{$unitType}]单位配置");
                    break;
            }
            // 遍历计算当前项目的总天数
            $totalDays = \array_reduce($miItems, function ($carray, MedicalInsuranceItem $item) use ($varName) {
                return \bcadd((string)$carray, (string)($item->$varName ?: 0));
            });
            list($ruleNum, $ruleNumType) = $this->getRuleOptionNum($medicalRecord, $rule);
            if (!$this->compareNum((float)$totalDays, $ruleNum)) {
                // 优化处理超过期限的信息
                $itemIds = [];
                [$min, $max] = \is_array($ruleNum) ? $ruleNum : [0, $ruleNum];
                if ($totalDays < $min) {
                    $itemIds = $this->getMedicalItemId($miItems);
                } else if ($totalDays > $max) {
                    $num = 0;
                    foreach ($miItems as $miItem) {
                        $num = \bcadd((string)$num, (string)($miItem->$varName ?: 0));
                        if ($num > $max && !\is_null($miItem->id)) {
                            $itemIds[] = $miItem->id;
                        }
                    }
                }
                // 超过用药天数
                $this->addErrors(
                    $errors,
                    $medicalRecord,
                    "当前项目[{$rule->itemName}]合计[{$totalDays}{$unit}]超过[{$ruleNum}{$unit}]",
                    [
                        'item' => $miItems,
                        'total_days' => $totalDays,
                        'item_ids' => $itemIds
                    ],
                    $rule
                );
            }
        }
        return $this->getResult(401, '不合理诊疗', $errors);
    }
    /**
     * 检测病历属性
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 诊疗规则对象
     * @return JsonTable
     */
    protected function detectProperty(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 先校验科室要求
        $result = $this->checkIncludedBranch($medicalRecord, $rule);
        if (true !== $result) {
            $errors = [
                ...$errors,
                ...$result
            ];
        }
        $result = Util::detectFormula($medicalRecord, $rule->options['property'] ?? [], $rule->getIRMIRuleSet()->dict);
        // 判断返回结果
        foreach ($result as $item) {
            [
                'name' => $name,
                'operator' => $operator,
            ] = $item;
            // 比对失败，则记录错误信息
            $opAlias = MapConst::OPERATOR_ALIAS[$operator] ?? $operator;
            $propertyAlias = MapConst::MEDICAL_RECORD_ALIAS[$name] ?? $name;
            $this->addErrors(
                $errors,
                $medicalRecord,
                "当前项目[{$rule->itemName}]对病历属性[{$propertyAlias}]进行[{$opAlias}]计算未通过",
                [
                    'item_ids' => $this->getMedicalItemIdByRule($medicalRecord, $rule)
                ],
                $rule
            );
        }
        return $this->getResult(402, '不合理诊疗', $errors);
    }
}
