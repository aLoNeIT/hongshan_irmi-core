<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

use hongshanhealth\irmi\IRMI as IRMIManager;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 医保智能审核测试
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMI
{
    public function run(): void
    {
        try {
            $jResult = new JsonTable();
            // 加载规则集合
            $ruleSetStr = \file_get_contents(__DIR__ . '/data/RuleSet.json');
            $ruleSet = \json_decode($ruleSetStr, true);
            // 加载并立即和
            $medicalRecordsStr = \file_get_contents(__DIR__ . '/data/MedicalRecords.json');
            $medicalRecords = \json_decode($medicalRecordsStr, true);
            // 读取规则集合
            $irmiManager = IRMIManager::instance()->store('shaanxi');
            $shaanxi = $irmiManager->load($ruleSet['code'], $ruleSet);
            foreach ($medicalRecords as $record) {
                $medicalRecord = (new MedicalRecord())->load($record);
                $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);
                if (!$jResult->setByArray($result)->isSuccess()) {
                    // 失败，记录
                    echo '检测未通过', PHP_EOL;
                    echo $jResult->toJson(), PHP_EOL;
                }
            }
        } catch (\Throwable $ex) {
            var_dump($ex);
        }
    }
}

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 应用初始化
(new TextSimilarity())->run();
