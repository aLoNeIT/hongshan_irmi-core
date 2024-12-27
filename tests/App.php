<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);

// 获取测试数据
define('IRMI_DS', DIRECTORY_SEPARATOR);
define('MEDICAL_RECORD_DIR', __DIR__ . IRMI_DS . 'data' . IRMI_DS . 'develop');
define('RULES_DIR', __DIR__ . IRMI_DS . 'data' . IRMI_DS . 'testing');


use hongshanhealth\irmi\struct\{JsonTable, MedicalRecord};
use hongshanhealth\irmi\{IRMI, IRMI as IRMIManager, Util};
use PDO;
use SplFileObject;


class App
{
    /**
     * @var string[]
     */
    protected array $typeMap = [
        1 => '重复收费',
        2 => '超标准收费',
        3 => '超医保费用',
        4 => '不合理诊疗',
    ];
    /**
     * @var string[]
     */
    protected array $visitTypeMap = [
        1 => '门诊',
        2 => '住院',
    ];

    /**
     * 导出CSV文件
     * @param array $data
     * @param string $filename
     * @param string|null $localPath
     * @return void
     */
    function exportCsv(array $data, string $filename = 'export.csv', string $localPath = null): void
    {
        $output = @fopen($localPath . $filename, 'w');
        if (!$output) {
            die("无法打开本地文件进行写入，请检查文件路径及权限");
        }

        foreach ($data as $row) {
            $encodedRow = array_map(
                'iconv',
                array_fill(0, count($row), 'UTF-8'),
                array_fill(0, count($row), 'GBK'),
                $row
            );
            fputcsv($output, $encodedRow, ',', '"');
        }

        // 关闭输出流
        fclose($output);
    }


    public function run($hospitalCode): void
    {

        try {
            $ruleArray = [];
            foreach (glob(RULES_DIR . IRMI_DS . '*.json') as $file) {
                $fileContent = file_get_contents($file);
                $fileContentArray = json_decode($fileContent, true);
                $ruleArray[] = $fileContentArray['rule'];
            }


            // 读取规则集合
            $shaanxi = IRMIManager::instance()->store('shaanxi');

            // 加载规则
            $shaanxi->load('01', [
                'code'  => '01',
                'name'  => '测试集合',
                'rules' => $ruleArray,
            ]);


            $files = [
                MEDICAL_RECORD_DIR . IRMI_DS . $hospitalCode . '_opt.log',
                MEDICAL_RECORD_DIR . IRMI_DS . $hospitalCode . '_ipt.log',
            ];



            $checkResultArray = [];
            $checkResultArray[] = [
                '医院名称',
                '就诊类型',
                '姓名',
                '就诊日期',
                '年龄',
                '就诊号',
                '审核规则',
                '规则名称',
                '规则项目名称',
                '规则项目编码',
                '审核结果',
            ];
            foreach ($files as $filepath) {

                $file = new SplFileObject($filepath);
                while (!$file->eof()) {

                    $record = json_decode(trim($file->fgets()), true);
                    if (is_null($record)) {
                        continue;
                    }


                    $medicalRecord = (new MedicalRecord())->load($record);

                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);

                    $jResult = (new JsonTable())->setByArray($result);

                    if (!$jResult->setByArray($result)->isSuccess()) {

                        $resultToArray = $jResult->toArray();

                        foreach ($resultToArray['data'] as $rdata_item) {


                            foreach ($rdata_item['data']['errors'] as $error) {


                                $checkRule = $error['data']['rule'];


                                // 就诊类型
                                $medicalRecordInfo = explode('-', $record['code'] ?? '');
                                $visitType = $this->visitTypeMap[$medicalRecordInfo[1]] ?? '';

                                $checkResultArray[] = [
                                    $record['hosp_name'],
                                    $visitType,
                                    $record['realname'],
                                    "\t".date('Y-m-d H:i:s',$record['in_date']),
                                    $record['age'],
                                    "\t".$medicalRecordInfo[2],
                                        $this->typeMap[$checkRule['type']] ?? '-',
                                        $checkRule['name'],
                                        $checkRule['item_name'],
                                        $checkRule['item_code'],
                                        $error['msg'],
                                ];


                            }
                        }

                    }
                }

                $file = null;

                Util::dump('-------------------------------------');
                Util::dump($checkResultArray);

                if(count($checkResultArray) > 1){

                    var_dump($checkResultArray[1]);

                    $hospitalname = $checkResultArray[1]['0'];
                    $this->exportCsv($checkResultArray, $hospitalname.'_'.$hospitalCode.'_irmi_check_result.csv', './');
                }
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


// 获取医院编码
$hospitalCodeArray = [];
foreach (glob(MEDICAL_RECORD_DIR.'/*.log') as $filePath) {
    $basename = basename($filePath);
    $basename = explode('_', $basename);
    $hospitalCodeArray[] = $basename[0];
}
$hospitalCodeArray = array_unique($hospitalCodeArray);


// 循环执行
foreach ($hospitalCodeArray as $hospitalCode) {
    // 应用执行
    (new App())->run($hospitalCode);
}

