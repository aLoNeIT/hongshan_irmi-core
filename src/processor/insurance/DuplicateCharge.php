<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\IRMIRule;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 重复收费处理器
 */
class DuplicateCharge extends Base implements IDetectInsuranceProcessor
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
            return $this->jsonTable->error($ex->getMessage(), 1, $ex->getTrace());
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
        $result = $this->checkIncludedItems($medicalRecord, $rule);
        if (true !== $result) {
            $errors = $result;
        }
        return $this->getResult(100, '重复收费', $errors);
    }
}
