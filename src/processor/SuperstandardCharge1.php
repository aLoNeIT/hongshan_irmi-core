<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable};

class SuperStandardCharge extends Base implements IDetectProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {

            // 根据子类型调用不同方法检验
            switch ($rule->subType) {
            }
            return $this->jsonTable->success();
        } catch (IRMIException $ex) {
            return $this->jsonTable->error($ex->getMessage(), 1, [
                'code' => $rule->code,
                'name' => $rule->name,
                'item' => $rule->itemCode,
                'item_name' => $rule->itemName
            ]);
        }
    }
    /**
     * 检测超过住院天数
     *
     * @param MedicalRecord $medicalRecord 病历数据
     * @param IRMIRule $rule 规则数据
     * @return JsonTable 返回结果
     */
    protected function detectOverHospitalizationDays(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        // 先检查是否是住院数据
        if (2 != $medicalRecord->visitType) {
            return $this->jsonTable->success();
        }
        // 检查该规则适用的时间范围
        $timeRange = $rule->options['time_range'] ?? null;
        if (!\is_null($timeRange)) {
            if (
                !((\is_null($timeRange[0]) || $medicalRecord->outDate >= $timeRange[0])
                    || (\is_null($timeRange[1]) || $medicalRecord->outDate < $timeRange[1]))
            ) {
                // 时间范围不符合，直接返回
                return $this->jsonTable->success();
            }
        }
        // 检查是否有排除的科室
        $excludeBranch = $rule->options['exclude_branch'] ?? [];
        if (\in_array($medicalRecord->branchCode, $excludeBranch)) {
            return $this->jsonTable->success();
        }
        // 获取该规则指定的医保项目数据，计算数量之和
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        $miItem = $miItemSet[$rule->itemCode];
        $totalNum = \array_reduce(
            $miItem,
            function ($total, $item) {
                return $total + $item->num;
            }
        );
        // 计算系数
        $coefficient = $rule->options['coefficient'] ?? 1;
        if ($totalNum > $medicalRecord->inDays * $coefficient) {
            // 超标准收费
            return $this->jsonTable->error("[超标准收费]", 200, [
                'code' => $rule->code,
                'name' => $rule->name,
                'item' => $rule->itemCode,
                'item_name' => $rule->itemName
            ]);
        }
        return $this->jsonTable->success();
    }

    // 指定项目当日收费超过X（元、次、小时）
    protected function detectOverDailyCharge(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        // 获取医保项目集合
        $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        // 获取当前项目数据集合
        $miItem = $miItemSet[$rule->itemCode];
        $varName = 'price';
        switch ($rule->options['unit']) {
            case 'num':
                $varName = 'num';
                break;
            default:
                $varName = 'price';
                break;
        }
        // 遍历该项目下每日数据，根据单位进行数据汇总
        $dailyData = [];
        \array_walk($miItem, function ($item) use (&$dailyData, $varName) {
            $dailyData[$item->time] = ($dailyData[$item->time] ?? 0) + $item->$varName;
        });
        // 循环判断是否存在某一天数据不符合要求
        foreach ($dailyData as $date => $num) {
            if ($num > $rule->options['max_num']) {
                return $this->jsonTable->error("[超日收费]", 300, [
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'item' => $rule->itemCode,
                    'item_name' => $rule->itemName
                ]);
            }
        }
    }
}
