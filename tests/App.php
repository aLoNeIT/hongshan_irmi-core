<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);

use hongshanhealth\irmi\struct\{JsonTable, MedicalRecord};
use hongshanhealth\irmi\{IRMI, IRMI as IRMIManager, Util};
use PDO;
use SplFileObject;


class App
{

    /**
     * 项目明细格式化输出
     * @param $decodedArray
     * @return array
     */
    function getMedicalDetailMessage($decodedArray): array
    {
        // 初始化insuranceData数组
        $insuranceData = [];

        // 遍历medical_insurance_set数组
        foreach ($decodedArray['medical_insurance_set'] as $date => $items) {
            // 将每个item数组添加到insuranceData中
            foreach ($items as $itemCode => $itemArray) {
                foreach ($itemArray as $item) {

                    /*
                    array(7) {
                      ["code"]=>
                      string(26) "001204000060000-120400006h"
                      ["name"]=>
                      string(12) "静脉输液"
                      ["time"]=>
                      int(1727484640)
                      ["num"]=>
                      int(2)
                      ["price"]=>
                      int(6)
                      ["cash"]=>
                      int(6)
                      ["total_cash"]=>
                      int(12)
                    }
                    */

                    $item['code'] = '编码:'.$item['code'];
                    $item['name'] = '名称:'.$item['name'];
                    $item['time'] = '时间:'.date('Y-m-d H:i:s', $item['time']);
                    $item['num'] = '数量:'.$item['num'];
                    $item['price'] = '标准价:'.number_format($item['price'], 2);
                    $item['cash'] = '实收价:'.number_format( $item['cash'], 2);
                    $item['total_cash'] = '实收总价:'.number_format($item['total_cash'] , 2);

                    $insuranceData[] =  join(' ' , $item);
                }
            }
        }

        // 返回最下层的数据列表
        return $insuranceData;
    }


    public function run()
    {

        try {

            // 获取测试数据
            define('IRMI_DS', DIRECTORY_SEPARATOR);
            define('MEDICAL_RECORD_DIR', __DIR__ . IRMI_DS . 'data'.IRMI_DS . 'develop');
            define('RULES_DIR', __DIR__ . IRMI_DS . 'data'.IRMI_DS . 'testing');


            $ruleArray = [];
            foreach (glob(RULES_DIR.IRMI_DS.'*.json') as $file){

                $fileContent = file_get_contents($file);
                $fileContentArray = json_decode($fileContent, true);
                $ruleArray[] = $fileContentArray['rule'];

            }

            // 读取规则集合
            $shaanxi = IRMIManager::instance()->store('shaanxi');

            // 加载规则
            $shaanxi->load('01', [
                'code' => '01',
                'name' => '测试集合',
                'rules' => $ruleArray
            ]);


            // 获取预设数据
            $files = [
                MEDICAL_RECORD_DIR.IRMI_DS.'27481028_opt.log',
                MEDICAL_RECORD_DIR.IRMI_DS.'27481028_ipt.log',
                MEDICAL_RECORD_DIR.IRMI_DS.'28996679_opt.log',
                MEDICAL_RECORD_DIR.IRMI_DS.'28996679_ipt.log',
            ];

            foreach ($files as $filepath){

                $file = new SplFileObject($filepath);
                while (!$file->eof()) {

                    $record = json_decode(trim($file->fgets()),true);
                    if(is_null($record)){
                        continue;
                    }


                    $medicalRecord = (new MedicalRecord())->load($record);


                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);

                    $jResult = (new JsonTable())->setByArray($result);

                    if (!$jResult->setByArray($result)->isSuccess()) {

                        $resmsg = "解析成功,用例未通过[{$record['code']}]";
                        // echo "解析成功,用例未通过[{$record['code']}]", PHP_EOL;
                        // echo '病历：', (string)$medicalRecord, PHP_EOL;
                        // echo '检测结果：', $jResult->toJson(), PHP_EOL;

                        $resultToArray = $jResult->toArray();


                        foreach ($resultToArray['data'] as $rdata_item){


                            // var_dump($rdata_item['data']);
                            foreach ($rdata_item['data']['errors'] as $error){


                                $typeMap = [
                                    1 => '重复收费',
                                    2 => '超标准收费',
                                    3 => '超医保费用',
                                    4 => '不合理诊疗'
                                ];

                                $checkRule = $error['data']['rule'];
                                $checkRule['type'] = $typeMap[$checkRule['type']]?? '-';
                                $resmsg.= ' : '.join(/*PHP_EOL*/' # ',$checkRule);

                                // echo $error['msg']."[$resmsg]".PHP_EOL;
                                echo $resmsg.PHP_EOL;
                            }
                        }

                    }
                }

                $file = null;
            }

        } catch (\Throwable $ex) {
            var_dump($ex->getLine());
            var_dump($ex->getFile());
            var_dump($ex->getMessage());
        }
    }

}

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 应用初始化
(new App())->run();
