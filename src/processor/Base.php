<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\struct\MedicalInsuranceItem;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 处理器基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
abstract class Base
{
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
    protected function initialize(): void {}

    /**
     * 以医保编码获取医保项目信息
     *
     * @param MedicalRecord $medicalRecord 病历对象
     * @return array 返回医保项目信息数组
     */
    protected function getMedicalInsuranceItemWithCode(MedicalRecord $medicalRecord): array
    {
        // 从病历临时数据中获取整理好的医保项目数据
        // 参考数据格式 {"120300002b":[{"date":1726243200,"num":2,"price":19.00,"total_cash":38.00}]}
        $data = $medicalRecord->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        if (\is_null($data)) {
            $data = [];
            foreach ($medicalRecord->medicalInsuranceSet as $date => $items) {
                foreach ($items as $code => $info) {
                    $data[$code][] = (new MedicalInsuranceItem())->load($info);
                }
            }
            $medicalRecord->setTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE, $data);
        }
        return $data ?: [];
    }
}
