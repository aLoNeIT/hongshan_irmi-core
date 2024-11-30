<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

use GetOpt\{GetOpt, Option};
use hongshanhealth\irmi\IRMI as IRMIManager;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;
use hongshanhealth\irmi\Util;

/**
 * 医保智能审核测试
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMI
{
    protected ?GetOpt $getOpt = null;
    public function __construct()
    {
        $this->getOpt = new GetOpt();
        // 命令行参数请求构造
        $this->getOpt->addOptions([
            Option::create('p', 'path', GetOpt::REQUIRED_ARGUMENT),
            Option::create('n', 'name', GetOpt::REQUIRED_ARGUMENT),
        ]);
    }

    public function run(): void
    {
        try {
            $argv =  array_filter((array)$_SERVER['argv'], function ($value) {
                return '--' != $value;
            });
            $this->getOpt->process($argv);
            // 获取请求参数
            $path = $this->getOpt->getOption('p');
            $name = $this->getOpt->getOption('n');
            $jResult = new JsonTable();
            $files = $this->getCaseFile($path, $name);
            // 读取规则集合
            $shaanxi = IRMIManager::instance()->store('shaanxi');
            $failNum = 0;
            $totalNum = 0;
            $successNum = 0;
            foreach ($files as $file) {
                if (false === strpos($file, '94057.json')) {
                    // continue;
                }
                echo '正在处理文件：' . $file, PHP_EOL;
                $caseStr = \file_get_contents($file);
                $caseObj = \json_decode($caseStr, true);
                $rule = $caseObj['rule'];
                $medicalRecords = $caseObj['medical_records'];
                // 加载规则
                $shaanxi->load('01', [
                    'code' => '01',
                    'name' => '测试集合',
                    'rules' => [$rule]
                ]);
                // 使用测试用例进行检测
                $mrSuccess = $medicalRecords['success'];
                $mrFail = $medicalRecords['fail'];
                // 先执行成功用例
                foreach ($mrSuccess as $record) {
                    $medicalRecord = (new MedicalRecord())->load($record);
                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);
                    if (!$jResult->setByArray($result)->isSuccess()) {
                        // 失败，记录
                        echo '成功测试用例未通过', PHP_EOL;
                        echo '病历：', (string)$medicalRecord, PHP_EOL;
                        echo '检测结果：', $jResult->toJson(), PHP_EOL;
                        $failNum++;
                    } else {
                        $successNum++;
                    }
                    $totalNum++;
                }
                // 执行失败的用例
                foreach ($mrFail as $record) {
                    $medicalRecord = (new MedicalRecord())->load($record);
                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);
                    if ($jResult->setByArray($result)->isSuccess()) {
                        // 失败，记录
                        echo '失败测试用例未通过', PHP_EOL;
                        echo '病历：', (string)$medicalRecord, PHP_EOL;
                        echo '检测结果：', $jResult->toJson(), PHP_EOL;
                        $failNum++;
                    } else {
                        $successNum++;
                    }
                    $totalNum++;
                }
            }
            echo  "测试用例执行完毕，用例总量：{$totalNum}，成功用例量：{$successNum}，失败用例量：{$failNum}", PHP_EOL;
        } catch (\Throwable $ex) {
            var_dump($ex);
        }
    }
    /**
     * 获取测试用例文件
     *
     * @return string[]
     */
    protected function getCaseFile(?string $dir = null, ?string $file = null): array
    {
        if (!\is_null($dir) && '' !== $dir) {
            $dirs = [$dir];
        } else {
            $dirs = ['medical_record_jcg'];
        }
        $pattern = \is_null($file) ? '/*.json' : "/{$file}.json";
        // 获取指定目录下所有后缀为json的文件
        $files = [];
        foreach ($dirs as $dir) {
            $dirPath = __DIR__ . '/data/' . $dir;
            // 使用glob函数获取指定目录下的文件列表
            $fileList = \glob($dirPath . $pattern);
            // 将文件列表添加到数组中
            $files = \array_merge($files, $fileList);
        }
        return $files;
    }
}

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 应用初始化
(new IRMI())->run();
