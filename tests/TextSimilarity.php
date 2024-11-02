<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

use alonetech\simhash\comparator\DefaultComparator;
use alonetech\simhash\SimHash;

error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);


class TextSimilarity
{

    public function run()
    {
        try {
            $simhash = new SimHash();
            $content1 = file_get_contents(__DIR__ . '/' . 'emr1.txt');
            $content2 = file_get_contents(__DIR__ . '/' . 'emr2.txt');
            $content3 = file_get_contents(__DIR__ . '/' . 'emr3.txt');

            $fp1 = $simhash->hash($content1);
            $fp2 = $simhash->hash($content2);
            $fp3 = $simhash->hash($content3);
            var_dump((string)$fp1, (string)$fp2, (string)$fp3);
            $comparator = new DefaultComparator();
            var_dump(
                $comparator->compare($fp1, $fp2),
                $comparator->compare($fp1, $fp3),
                $comparator->compare($fp2, $fp3)
            );
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
