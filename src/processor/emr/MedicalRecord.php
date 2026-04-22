<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\emr;

use hongshanhealth\irmi\constant\{Key, Map as MapConst};
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\processor\Base;
use hongshanhealth\irmi\struct\{MedicalRecord as MedicalRecordStruct, IRMIRule, JsonTable};
use hongshanhealth\irmi\Util;

/**
 * 病历检测处理器
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class MedicalRecord extends Base implements IDetectInsuranceProcessor
{
    /**
     * @inheritDoc
     */
    public function detect(MedicalRecordStruct $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
                case 1: // 诊断手术不一致
                    $jResult = $this->detectDiagnosisProcedure($medicalRecord, $rule);
                    break;
                case 2: // 病例属性不一致
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
     * 检测主诊断和主手术不一致
     *
     * @param MedicalRecordStruct $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return JsonTable
     */
    protected function detectDiagnosisProcedure(MedicalRecordStruct $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 当前触发规则的属性类型
        $propertyName = match (true) {
            $medicalRecord->principalDiagnosis == $rule->itemCode => 'principalDiagnosis',
            $medicalRecord->majorProcedure == $rule->itemCode => 'majorProcedure',
            default => null,
        };
        // 是指定的属性触发，才进行检测
        if (!\is_null($propertyName)) {
            $jResult = $this->detectProperty($medicalRecord, $rule);
            $errors = $jResult->data ?? [];
        }
        return $this->getResult(1101, '诊断编码与手术操作编码不符', $errors);
    }
    /**
     * 检测病历属性之间的关系
     *
     * @param MedicalRecordStruct $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return JsonTable
     */
    protected function detectProperty(MedicalRecordStruct $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        $result = Util::detectFormula($medicalRecord, $rule->options['property'] ?? [], $rule->getIRMIRuleSet()->dict);
        // 判断返回结果
        if (!empty($result)) {
            foreach ($result as $item) {
                [
                    'name' => $name,
                    'operator' => $operator,
                    'property_value' => $propertyValue,
                ] = $item;
                // 比对失败，则记录错误信息
                $opAlias = MapConst::OPERATOR_ALIAS[$operator] ?? $operator;
                $propertyAlias = MapConst::MEDICAL_RECORD_ALIAS[$name] ?? $name;
                $this->addErrors(
                    $errors,
                    $medicalRecord,
                    "当前病历属性[{$propertyAlias}]进行[{$opAlias}]计算未通过",
                    [
                        'item_ids' => null,
                        'item_properties' => [
                            $name =>  [
                                'value' => $propertyValue
                            ],
                        ],
                    ],
                    $rule
                );
            }
        }
        return $this->getResult(1102, '病历属性不合规', $errors);
    }
    /**
     * 获取所有项目id集合
     *
     * @param MedicalRecordStruct $medicalRecord 病历对象
     * @return int[] 项目id集合
     */
    protected function getAllItemIds(MedicalRecordStruct $medicalRecord): array
    {
        /** @var array<string, \hongshanhealth\irmi\struct\MedicalInsuranceItem[]> $tmpMiItemSet */
        $tmpMiItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE) ?? [];
        $itemIds = [];
        foreach ($tmpMiItemSet as $itemCode => $items) {
            foreach ($items as $item) {
                $itemIds[] = $item->id;
            }
        }
        return $itemIds;
    }
}
