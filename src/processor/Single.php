<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\interfaces\IDetectProcessor;
use hongshanhealth\irmi\struct\IRMIRule;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 单人单次处理器
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class Single extends Base implements IDetectProcessor
{
    /** @inheritDoc */
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        return $this->jsonTable->success();
    }
}
