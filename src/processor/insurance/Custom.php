<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};

/**
 * 自定义检测处理器
 */
class Custom extends Base implements IDetectInsuranceProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 统一校验就诊类型
            $visitTypeResult = $this->checkVisitType($medicalRecord, $rule);
            if (true !== $visitTypeResult) {
                return $this->getResult(901, '其他违规', $visitTypeResult);
            }
            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
                case 1:
                    $jResult = $this->detectUnit($medicalRecord, $rule);
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
     * 单位检测处理器
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     * @throws IRMIException
     */
    protected function detectUnit(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        /** @var array<string,MedicalInsuranceItem[]> $miItemSet */
        $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前规则涉及的项目编码集合
        $currItems = $this->filterMIItemByDateRange($tmpMiItemSet[$rule->itemCode] ?? [], $rule);
        $itemUnit = $rule->options['item_unit'] ?? [];
        $itemUnit = \is_array($itemUnit) ? $itemUnit : [$itemUnit];
        // 依次遍历每个项目，检测他的单位是否在允许的单位集合中
        $ids = [];
        foreach ($currItems as $item) {
            // 单位为null则跳过检测
            if (!\is_null($item->unit) && !in_array($item->unit, $itemUnit)) {
                $ids[] = $item->id;
            }
        }
        if (!empty($ids)) {
            $this->addErrors(
                $errors,
                $medicalRecord,
                "当前项目[{$rule->itemName}]的单位不在允许的集合中",
                [
                    'item_ids' => $ids
                ],
                $rule
            );
        }
        return $this->getResult(901, '其他违规', $errors);
    }
}
