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
     */
    protected function detectCommon(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前规则涉及的项目编码集合
        $includeItems = $rule->options['include_items'] ?? [];
        if (!empty($includeItems)) {
            // 非空，则需要检查项目是否存在
        }

        return $this->getResult(501, '分解收费', $errors);
    }
}
