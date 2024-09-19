<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);

class App
{
    public function run()
    {
        try {
            $ruleSet = [
                'code' => '01',
                'name' => '规则集',
                'items' => [
                    [
                        'code' => '01',
                        'name' => '规则1',
                        'item_code' => '001',
                        'item_name' => '项目1',
                        'type' => 1,
                        'options' => [
                            'exclude_items' => [
                                '002' => ['num' => 1, 'time_type' => 1],
                                '003' => ['num' => 1, 'time_type' => 1]
                            ],
                            'time_range' => [null, null],
                            'pathology_check' => ['004', '005']
                        ]
                    ]
                ]
            ];
            echo json_encode($ruleSet, JSON_UNESCAPED_UNICODE), PHP_EOL;
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
