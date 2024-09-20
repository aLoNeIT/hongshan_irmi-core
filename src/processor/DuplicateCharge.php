<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectProcessor;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\IRMIRule;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalInsuranceItem;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 重复收费处理器
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class DuplicateCharge extends Base implements IDetectProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        try {
            // 读取规则内容
            // 获取医保项目集合，以项目编码为key
            $miItemSet = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
            // 当前规则和病例不匹配，直接退出不报错
            if (!isset($miItemSet[$rule->itemCode])) {
                return $this->jsonTable->success();
            }
            /** @var MedicalInsuranceItem[] $miItem */
            $miItem = $miItemSet[$rule->itemCode];
            // 选项1，判断是否存在指定时间范围内的数据才进行匹配的
            if (isset($rule->options['time_range'])) {
                // 待删除的项目
                $validItem = [];
                // time_range内是多个数组，用于匹配时间范围，每个元素的格式为 [开始时间, 结束时间]，若为null，则不比对
                $timeRange = $rule->options['time_range'];
                \array_walk($miItem, function (MedicalInsuranceItem $item) use (&$validItem, $timeRange) {
                    if (
                        (\is_null($timeRange[0]) || $item->time >= $timeRange[0])
                        && (\is_null($timeRange[1]) || $item->time < $timeRange[1])
                    ) {
                        // 时间范围符合，且存在指定项目
                        $validItem[] = $item;
                    }
                });
                $miItem = $validItem;
            }
            // 获取排除项目列表，病例中存在该列表内的项目则触发重复收费
            // 该数据参考格式：{"001":{"num":1,"time_type":1}}
            // time_type为时间类型，1是当天
            $excludeItems = $rule->options['exclude_items'] ?? [];
            // 当前病历中所有项目编码集合
            $miItemCodes = \array_keys($miItemSet);
            // 排除项目编码集合
            $excludeItemCodes = \array_keys($excludeItems);
            // 计算交集，获取重复项目
            $dcItems = \array_intersect($miItemCodes, $excludeItemCodes);
            // 非空数组则有重复项目
            if (!empty($dcItems)) {
                // 选项2，无其他病理检查时，当前项目和排除项目同时存在则为重复收费
                // 是否存在其他病理检查项目
                $pcExisted = false;
                if (isset($rule->options['pathology_check'])) {
                    // 是否存在指定病理检查项目，存在则不算做重复计费
                    $pcExisted = !empty(\array_intersect($miItemCodes, $rule->options['pathology_check']));
                    if ($pcExisted) {
                        return $this->jsonTable->success();
                    }
                }
                // 选项3，其他项目超出指定数量
                $errors = [];
                foreach ($dcItems as $key) {
                    // 查看每个$key的项目限定数量是多少，如果未超过则不属于重复收费
                    // 这里默认是当天，即time_type为1，后续根据需求优化
                    if (
                        isset($excludeItemCodes[$key]['num'])
                        && $excludeItems[$key]['num'] > $miItemSet[$key]->num
                    ) {
                        continue;
                    }
                    $errors[] = $miItemSet[$key];
                }

                // 确定错误内容
                $msg = ($pcExisted ?  '[无其他病理检查]' : (count($errors) > 0 ? '[其他项目超出限定数量]' : ''));
                $this->jsonTable->error("{$msg}", 100, [
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'item_code' => $rule->itemCode,
                    'item_name' => $rule->itemName
                ]);
            }
            // 一切正常，返回
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
}
