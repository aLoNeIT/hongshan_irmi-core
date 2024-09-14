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
