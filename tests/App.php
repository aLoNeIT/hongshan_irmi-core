<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);

use hongshanhealth\irmi\struct\{IRMIRuleSet, MedicalRecord};
use hongshanhealth\irmi\{IRMI, Driver};


class App
{
    public function run()
    {
        try {
            $arr = [
                ['id' => 1, 'name' => '张三'],
                ['id' => 2, 'name' => '李四'],
                ['id' => 3, 'name' => '王五'],
                ['id' => 4, 'name' => '赵六']
            ];
            var_dump(\array_column($arr, 'name'));
            return;
            $arr = [
                'a' => [1, 2, 3],
                'b' => [4, 5, 6]
            ];
            array_walk($arr, function ($item, $code) {
                var_dump($item, $code);
                return;
            });
            bcscale(2);
            var_dump(bcmul('100.5333', '5'));
            return;
            // 判断字符串最后一位是否 / 
            $dir = PATH_SEPARATOR == \substr(__DIR__, -1) ? __DIR__ : __DIR__ . '/';
            $ruleSetStr = file_get_contents($dir . './RuleSet.json');
            $medicalRecordStr = file_get_contents($dir . './MedicalRecord.json');
            // var_dump([
            //     'rule_json' => json_decode($ruleSetStr, true),
            //     'medical_record_json' => json_decode($medicalRecordStr, true)
            // ]);
            $irmi = IRMI::instance()->store('shaanxi');
            $ruleSet = $irmi->switch('01')->load(json_decode($ruleSetStr, true));
            $medicalRecord = (new MedicalRecord())->load(json_decode($medicalRecordStr, true));
            var_dump([
                'rule_set' => $ruleSet,
                'medical_record' => $medicalRecord,
            ]);
            $result = $ruleSet->detect($medicalRecord);
            var_dump($result);
        } catch (\Throwable $ex) {
            var_dump($ex);
        }
    }

    private function echoMsg(string $msg, bool $force = false): void
    {
        if (!$force && !DEBUG) return;
        echo $msg, PHP_EOL;
    }
}

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 应用初始化
(new App())->run();
