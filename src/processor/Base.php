<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\struct\{
    IRMIRule,
    JsonTable,
    MedicalRecord,
    MedicalInsuranceItem
};
use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\IRMILog;

/**
 * 处理器基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
abstract class Base
{
    /**
     * JsonTable对象结果返回类
     *
     * @var JsonTable|null
     */
    protected ?JsonTable $jsonTable = null;
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->initialize();
    }
    /**
     * 初始化函数
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->jsonTable = new JsonTable();
    }

    /**
     * 获取规则信息
     *
     * @param IRMIRule $rule 规则
     * @return array 返回规则信息
     */
    protected function getRuleInfo(IRMIRule $rule): array
    {
        return [
            'category' => $rule->category,
            'type' => $rule->type,
            'sub_type' => $rule->subType,
            'code' => $rule->code,
            'name' => $rule->name,
            'item_code' => $rule->itemCode,
            'item_name' => $rule->itemName,
            'item_class' => $rule->itemClass,
            'item_type' => $rule->itemType,
            'group' => $rule->group,
        ];
    }
    /**
     * 获取返回结果
     *
     * @param integer $errNo 错误码
     * @param string $errMsg 错误信息
     * @param array $errData 错误具体数据
     * @return JsonTable 当errData不为空，则返回包含错误信息的JsonTable对象，否则返回包含正确信息JsonTable对象
     */
    protected function getResult(int $errNo, string $errMsg, array $errData): JsonTable
    {
        return empty($errData) ? $this->jsonTable->success()
            : $this->jsonTable->error($errMsg, $errNo, [
                'errors' => $errData
            ]);
    }
    /**
     * 添加错误数据到错误数组
     *
     * @param array &$errors 错误数组
     * @param MedicalRecord $medicalRecord 病历数据
     * @param string $msg 错误信息
     * @param array $data 错误具体数据
     * @param IRMIRule $rule 规则数据
     * @return void
     */
    protected function addErrors(array &$errors, MedicalRecord $medicalRecord, string $msg, array $data, IRMIRule $rule): void
    {
        $group = $rule->group;
        if (!\is_null($group) && '' !== $group) {
            $groupSet = $medicalRecord->getTmpData(Key::KEY_ERROR_RULE_GROUP_SET) ?? [];
            if (\in_array($group, $groupSet, true)) {
                return;
            }
            $groupSet[] = $group;
            $medicalRecord->setTmpData(Key::KEY_ERROR_RULE_GROUP_SET, $groupSet);
        }
        $error = [
            'msg' => $msg,
            'data' => [
                'rule' => $this->getRuleInfo($rule),
                ...$data,
            ]
        ];
        IRMILog::debug(static::class, __FUNCTION__, $error);
        $errors[] = $error;
    }
    /**
     * 根据规则获取医疗项目数据
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return MedicalInsuranceItem[] 返回医疗项目集合
     */
    protected function getMedicalItemByRule(MedicalRecord $medicalRecord, IRMIRule $rule): array
    {
        // 先获取所有项目数据
        $miItemSet = 1 == $rule->itemClass
            ? ($medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE) ?? [])
            : ($medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CLASS) ?? []);

        // 获取临时数据，同时根据规则有效期进行过滤
        $currMiItemSet = $this->filterMIItemByDateRange($miItemSet[$rule->itemCode], $rule);
        return $currMiItemSet;
    }

    /**
     * 根据规则获取病历中的项目id
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @param IRMIRule $rule 规则对象
     * @return integer[] 返回项目id集合
     */
    protected function getMedicalItemIdByRule(MedicalRecord $medicalRecord, IRMIRule $rule): array
    {
        $currMiItemSet = $this->getMedicalItemByRule($medicalRecord, $rule);
        // 获取诊疗项目id
        return $this->getMedicalItemId($currMiItemSet);
    }
    /**
     * 获取医疗项目id
     *
     * @param MedicalInsuranceItem[] $miItemSet 医疗项目集合
     * @return integer[] 医疗项目id集合
     */
    protected function getMedicalItemId(array $miItemSet): array
    {
        // 获取诊疗项目id
        $ids = \array_map(function (MedicalInsuranceItem $miItem) {
            return $miItem->id;
        }, $miItemSet);
        // 过滤null数据
        return \array_filter($ids, function ($id) {
            return !\is_null($id);
        });
    }
    /**
     * 过滤项目数据，只保留有效期在规则时间范围内的项目
     *
     * @param MedicalInsuranceItem[] $miItems 项目数据集合
     * @param IRMIRule $rule 规则数据
     * @return MedicalInsuranceItem[]
     */
    protected function filterMIItemByDateRange(array $miItems, IRMIRule $rule): array
    {
        return \array_filter($miItems, function (MedicalInsuranceItem $item) use ($rule) {
            return $this->checkDateRange($item->date, $rule);
        });
    }
    /**
     * 检查规则适用的时间范围
     *
     * @param integer $date 日期时间戳
     * @param IRMIRule $rule 规则数据
     * @return boolean 返回是否在时间范围内，true为命中时间范围，false为未命中
     */
    protected function checkDateRange(int $date, IRMIRule $rule): bool
    {
        // 检查该规则适用的时间范围
        $timeRange = $rule->options['time_range'] ?? null;
        if (!\is_null($timeRange)) {
            if (
                !((\is_null($timeRange[0]) || $date >= $timeRange[0])
                    && (\is_null($timeRange[1]) || $date < $timeRange[1]))
            ) {
                // 时间不符合规则要求的范围
                return false;
            }
        }
        return true;
    }
}
