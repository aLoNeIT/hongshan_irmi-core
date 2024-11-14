<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\{Map as MapConst};
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable, MedicalInsuranceItem};
use hongshanhealth\irmi\Util;

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
                    $jResult = $this->detectInsuranceItem($medicalRecord, $rule);
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
            return $this->jsonTable->error($ex->getMessage(), 1, $ex->getTrace());
        }
    }
    /**
     * 检测医保项目同时收费或未同时收费
     *
     * @param MedicalRecord $medicalRecord
     * @param IRMIRule $rule
     * @return JsonTable
     */
    protected function detectInsuranceItem(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        // 检查include、exclude规则
        $result = $this->checkIncludedItems($medicalRecord, $rule);
        if (true !== $result) {
            return $this->getResult(401, '不合理诊疗', $result);
        }
        return $this->jsonTable->success();
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
            $errors = \array_merge($errors, $result);
        }
        // 再检测其他属性
        $propertyOptions = $rule->options['property'] ?? [];
        // 遍历该配置，并且遍历过程中进行检测
        foreach ($propertyOptions as $propertyItem) {
            [
                'name' => $name,
                'operator' => $operator,
                'value' => $value
            ] = $propertyItem;
            $propertyName = Util::camel($name);
            $propertyValue = $medicalRecord->$propertyName;
            // 开始对比结果
            $result = false;
            switch ($operator) {
                case '=':
                    $result = $propertyValue == $value;
                    break;
                case '!=':
                    $result = $propertyValue != $value;
                    break;
                case '<':
                    $result = $propertyValue < $value;
                    break;
                case '<=':
                    $result = $propertyValue <= $value;
                    break;
                case '>':
                    $result = $propertyValue > $value;
                    break;
                case '>=':
                    $result = $propertyValue >= $value;
                    break;
                case 'in':
                    if (\is_array($value)) {
                        // 数组
                        $result = \in_array($propertyValue, $value);
                    } else if (\is_string($value)) {
                        // 字符串则逗号分隔
                        $result = \in_array($propertyValue, \explode(',', $value));
                    }
                    break;
                case 'not in':
                    if (\is_array($value)) {
                        $result = !\in_array($propertyValue, $value);
                    } else if (\is_string($value)) {
                        // 字符串则逗号分隔
                        $result = !\in_array($propertyValue, \explode(',', $value));
                    }
                    break;
                default:
                    throw new IRMIException("不支持的运算符[{$operator}]");
                    break;
            }
            // 判断返回结果
            if (!$result) {
                // 比对失败，则记录错误信息
                $opAlias = MapConst::OPERATOR_ALIAS[$operator];
                $propertyAlias = MapConst::MEDICAL_RECORD_ALIAS[$name];
                $errors[] = [
                    'msg' => "当前项目[{$rule->itemName}]对病历属性[{$propertyAlias}]进行[{$opAlias}]计算未通过",
                    'data' => [
                        'rule' => $this->getRuleInfo($rule)
                    ]
                ];
            }
        }
        return $this->getResult(402, '不合理诊疗', $errors);
    }
}
