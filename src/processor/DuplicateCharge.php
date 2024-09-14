<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\struct\IRMIRule;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalInsuranceItem;
use hongshanhealth\irmi\struct\MedicalRecord;


/**
 * 重复收费处理器
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class DuplicateCharge extends Base
{
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        $errors = [];
        // 读取规则内容
        // 获取医保项目集合，以项目编码为key
        $miItemSet = $this->getMedicalInsuranceItemWithCode($medicalRecord);
        // 选项1，判断是否存在指定时间范围内的数据才进行匹配的
        if (isset($rule->options['time_range'])) {
            // time_range内是多个数组，用于匹配时间范围，每个元素的格式为 [开始时间, 结束时间]，若为null，则不比对
            $timeRange = $rule->options['time_range'];
            \array_walk($miItemSet[$rule->code], function (MedicalInsuranceItem $item) use (&$miItem, $timeRange) {
                if (
                    (!\is_null($timeRange[0]) && $item->time >= $timeRange[0])
                    || (!\is_null($timeRange[1]) && $item->time < $timeRange[1])
                ) {
                    // 时间范围符合，且存在指定项目
                    $miItem[] = $item;
                }
            });
        } else {
            $miItem = $miItemSet[$rule->code];
        }
        // 获取排除项目列表，病例中存在该列表内的项目则触发重复收费
        // 该数据参考格式：{"001":{"num":1,"time_type":1}}
        // time_type为时间类型，1是当天
        $excludeItems = $rule->options['exclude_items'] ?? [];
        $miItemCodes = \array_keys($miItemSet);
        // 计算交集，获取重复项目
        $dcItems = \array_intersect($miItemCodes, $excludeItems);
        // 空数组则没有重复项目
        $result = empty(\array_intersect($miItemCodes, $excludeItems));
        if (!$result) {
            // 存在重复项目
            // 选项2，无其他病理检查时
            if (isset($rule->options['pathology_check'])) {
                // 是否存在指定病理检查项目，存在则不算做重复计费
                $result = empty(\array_intersect($miItemCodes, $rule->options['pathology_check']));
            }
        }






        // 根据规则内容从病例数据中提取

        // 检测是否符合要求
        return (new JsonTable())->success();
    }
}
