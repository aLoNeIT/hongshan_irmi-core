<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\interfaces;

use hongshanhealth\irmi\struct\IRMIRuleSet;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 入组检测处理器接口
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
interface IDetectProcessor
{
    /**
     * 根据病案信息和入组依据数据匹配对应分组
     *
     * @param MedicalRecord $medicalRecord 病历信息
     * @param IRMIRuleSet $ruleSet 规则集合
     * @return boolean 返回匹配成功或失败
     */
    public function detect(MedicalRecord $medicalRecord, IRMIRuleSet $ruleSet): JsonTable;
}
