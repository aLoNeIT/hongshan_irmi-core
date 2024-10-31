<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

use Fukuball\Jieba\Finalseg;
use Fukuball\Jieba\Jieba;



error_reporting(E_ALL);

defined('DEBUG') || define('DEBUG', true);


class SimHash
{
    protected $text1 = <<<EOT
George Headley (1909–1983) was a West Indian cricketer who played 22 Test matches, mostly before the Second World War.
Considered one of the best batsmen to play for West Indies and one of the greatest cricketers of all time, he also
represented Jamaica and played professionally in England. Headley was born in Panama but raised in Jamaica where he
quickly established a cricketing reputation as a batsman. West Indies had a weak cricket team through most of Headley's
career; as their one world-class player, he carried a heavy responsibility, and they depended on his batting. He batted
at number three, scoring 2,190 runs in Tests at an average of 60.83, and 9,921 runs in all first-class matches at an
average of 69.86. He was chosen as one of the Wisden Cricketers of the Year in 1934.
EOT;

    protected $text2 = <<<EOT
George Headley was a West Indian cricketer who played 22 Test matches, mostly before the Second World War.
Considered one of the best batsmen to play for West Indies and one of the greatest cricketers of all time, he also
represented Jamaica and played professionally in England. Headley was born in Panama but raised in Jamaica where he
quickly established a cricketing reputation as a batsman. West Indies had a weak cricket team through most of Headley's
career; as their one world-class player, he carried a heavy responsibility, and they depended on his batting. He batted
at number three, scoring 2,190 runs in tests at an average of 60.83, and 9,921 runs in all first-class matches at an
average of 69.86. He was chosen as one of the Wisden Cricketers of the Year.
EOT;

    public function run()
    {
        try {
            Jieba::init();
            Finalseg::init();

            $simhash = new \Tga\SimHash\SimHash();
            $extractor = new \Tga\SimHash\Extractor\SimpleTextExtractor();
            $comparator = new \Tga\SimHash\Comparator\GaussianComparator(3);
            // 分词处理
            $files = ['emr1.txt', 'emr2.txt', 'emr3.txt'];
            $contents = [];
            $extracts = [];
            $fingerprints = [];
            foreach ($files as $file) {
                $contents[] = file_get_contents(__DIR__ . '/' . $file);
                $extracts[] = $extractor->extract($contents[count($contents) - 1]);
                $fingerprints[] = $simhash->hash($extracts[count($extracts) - 1], \Tga\SimHash\SimHash::SIMHASH_64);
            }
            // 0-1
            var_dump([
                '0-1' => $comparator->compare($fingerprints[0], $fingerprints[1]),
            ]);
            // 0-2
            var_dump([
                '0-2' => $comparator->compare($fingerprints[0], $fingerprints[2]),
            ]);
            // 1-2
            var_dump([
                '1-2' => $comparator->compare($fingerprints[1], $fingerprints[2]),
            ]);
            return;

            $extractText1 = $extractor->extract($this->text1);
            $extractText2 = $extractor->extract($this->text2);
            var_dump($extractText1);
            var_dump($extractText2);
            $fp1 = $simhash->hash($extractText1, \Tga\SimHash\SimHash::SIMHASH_64);
            $fp2 = $simhash->hash($extractText2, \Tga\SimHash\SimHash::SIMHASH_64);
            var_dump($fp1->getBinary());
            var_dump($fp2->getBinary());

            // Index between 0 and 1 : 0.80073740291681
            var_dump($comparator->compare($fp1, $fp2));
        } catch (\Throwable $ex) {
            var_dump($ex);
        }
    }
}

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 应用初始化
(new SimHash())->run();
