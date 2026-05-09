<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\tests;

use GetOpt\{GetOpt, Option};
use hongshanhealth\irmi\IRMI as IRMIManager;
use hongshanhealth\irmi\struct\JsonTable;
use hongshanhealth\irmi\struct\MedicalRecord;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

class IRMI
{
    protected ?GetOpt $getOpt = null;

    public function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');
        $this->getOpt = new GetOpt();
        $this->getOpt->addOptions([
            Option::create('p', 'path', GetOpt::REQUIRED_ARGUMENT),
            Option::create('n', 'name', GetOpt::REQUIRED_ARGUMENT),
        ]);
    }

    public function run(): void
    {
        try {
            $argv = array_filter((array) $_SERVER['argv'], function ($value) {
                return '--' !== $value;
            });
            $this->getOpt->process($argv);

            $path = $this->getOpt->getOption('p');
            $name = $this->getOpt->getOption('n');
            $jResult = new JsonTable();
            $files = $this->getCaseFile($path, $name);
            $shaanxi = IRMIManager::instance()->store('shaanxi');

            $failNum = 0;
            $totalNum = 0;
            $successNum = 0;

            foreach ($files as $file) {
                echo '正在处理文件：' . $file, PHP_EOL;

                $caseStr = file_get_contents($file);
                $caseObj = json_decode((string) $caseStr, true, 512, JSON_THROW_ON_ERROR);
                $rules = $this->getCaseRules($caseObj, $file);
                $medicalRecords = $caseObj['medical_records'] ?? [];
                $dict = $caseObj['dict'] ?? [];

                $shaanxi->load('01', [
                    'code' => '01',
                    'name' => '测试集合',
                    'rules' => $rules,
                    'dict' => $dict,
                ]);

                foreach (($medicalRecords['success'] ?? []) as $record) {
                    $medicalRecord = (new MedicalRecord())->load($record);
                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);
                    $isSuccess = $jResult->setByArray($result)->isSuccess();
                    $expectationError = $this->getExpectedResultError($result, $record);

                    if (!$isSuccess || null !== $expectationError) {
                        echo '成功测试用例未通过', PHP_EOL;
                        if (null !== $expectationError) {
                            echo '期望校验失败：' . $expectationError, PHP_EOL;
                        }
                        echo '病历：', (string) $medicalRecord, PHP_EOL;
                        echo '检测结果：', $jResult->toJson(), PHP_EOL;
                        $failNum++;
                    } else {
                        $successNum++;
                    }
                    $totalNum++;
                }

                foreach (($medicalRecords['fail'] ?? []) as $record) {
                    $medicalRecord = (new MedicalRecord())->load($record);
                    $result = $shaanxi->switch('01')->detectInsurance($medicalRecord);
                    $isSuccess = $jResult->setByArray($result)->isSuccess();
                    $expectationError = $isSuccess ? null : $this->getExpectedResultError($result, $record);

                    if ($isSuccess || null !== $expectationError) {
                        echo '失败测试用例未通过', PHP_EOL;
                        if (null !== $expectationError) {
                            echo '期望校验失败：' . $expectationError, PHP_EOL;
                        }
                        echo '病历：', (string) $medicalRecord, PHP_EOL;
                        echo '检测结果：', $jResult->toJson(), PHP_EOL;
                        $failNum++;
                    } else {
                        $successNum++;
                    }
                    $totalNum++;
                }
            }

            echo "测试用例执行完毕，用例总量：{$totalNum}，成功用例量：{$successNum}，失败用例量：{$failNum}", PHP_EOL;
        } catch (\Throwable $ex) {
            var_dump($ex);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getCaseRules(array $caseObj, string $file): array
    {
        $rules = $caseObj['rules'] ?? [];
        if (empty($rules) && isset($caseObj['rule']) && is_array($caseObj['rule'])) {
            $rules = [$caseObj['rule']];
        }
        if (empty($rules)) {
            throw new \InvalidArgumentException("Case file [{$file}] must define rule or rules.");
        }
        return array_values($rules);
    }

    protected function getExpectedResultError(array $result, array $record): ?string
    {
        $expectedResult = $record['expected_result'] ?? null;
        if (!is_array($expectedResult)) {
            return null;
        }

        $actualErrors = $this->getActualErrors($result);
        $actualRuleCodes = $this->getActualRuleCodes($actualErrors);

        if (isset($expectedResult['rule_count'])) {
            $actualRuleCount = count($actualRuleCodes);
            if ($actualRuleCount !== (int) $expectedResult['rule_count']) {
                return "rule_count expected {$expectedResult['rule_count']}, actual {$actualRuleCount}";
            }
        }

        if (isset($expectedResult['rule_codes'])) {
            $expectedRuleCodes = array_values((array) $expectedResult['rule_codes']);
            if ($actualRuleCodes !== $expectedRuleCodes) {
                return sprintf(
                    'rule_codes expected %s, actual %s',
                    json_encode($expectedRuleCodes, JSON_UNESCAPED_UNICODE),
                    json_encode($actualRuleCodes, JSON_UNESCAPED_UNICODE)
                );
            }
        }

        if (isset($expectedResult['rule_item_codes'])) {
            $expectedRuleItemCodes = array_values((array) $expectedResult['rule_item_codes']);
            $actualRuleItemCodes = $this->getActualRuleItemCodes($actualErrors);
            if ($actualRuleItemCodes !== $expectedRuleItemCodes) {
                return sprintf(
                    'rule_item_codes expected %s, actual %s',
                    json_encode($expectedRuleItemCodes, JSON_UNESCAPED_UNICODE),
                    json_encode($actualRuleItemCodes, JSON_UNESCAPED_UNICODE)
                );
            }
        }

        if (isset($expectedResult['error_count'])) {
            $actualErrorCount = count($actualErrors);
            if ($actualErrorCount !== (int) $expectedResult['error_count']) {
                return "error_count expected {$expectedResult['error_count']}, actual {$actualErrorCount}";
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getActualErrors(array $result): array
    {
        $errors = [];
        foreach (($result['data'] ?? []) as $detectResult) {
            if (!is_array($detectResult)) {
                continue;
            }
            foreach (($detectResult['data']['errors'] ?? []) as $error) {
                if (is_array($error)) {
                    $errors[] = $error;
                }
            }
        }
        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @return string[]
     */
    protected function getActualRuleCodes(array $errors): array
    {
        $codes = [];
        $seen = [];
        foreach ($errors as $error) {
            $code = $error['data']['rule']['code'] ?? null;
            if (!is_string($code) || '' === $code || isset($seen[$code])) {
                continue;
            }
            $codes[] = $code;
            $seen[$code] = true;
        }
        return $codes;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @return string[]
     */
    protected function getActualRuleItemCodes(array $errors): array
    {
        $codes = [];
        $seen = [];
        foreach ($errors as $error) {
            $code = $error['data']['rule']['item_code'] ?? null;
            if (!is_string($code) || '' === $code || isset($seen[$code])) {
                continue;
            }
            $codes[] = $code;
            $seen[$code] = true;
        }
        return $codes;
    }

    /**
     * @return string[]
     */
    protected function getCaseFile(?string $dir = null, ?string $file = null): array
    {
        if (!is_null($dir) && '' !== $dir) {
            $dirs = [$dir];
        } else {
            $dirs = ['medical_record_jcg'];
        }

        $pattern = is_null($file) ? '/*.json' : "/{$file}.json";
        $files = [];
        foreach ($dirs as $dir) {
            $dirPath = __DIR__ . '/data/' . $dir;
            $fileList = glob($dirPath . $pattern);
            $files = [
                ...$files,
                ...$fileList,
            ];
        }
        return $files;
    }
}

class TLogger implements LoggerInterface
{
    /**
     * @param mixed[] $context
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        var_dump('EMERGENCY: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        var_dump('ALERT: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        var_dump('CRITICAL: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        var_dump('ERROR: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        var_dump('WARNING: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        var_dump('NOTICE: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        var_dump('INFO: ' . $message, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        var_dump('DEBUG: ' . $message, $context);
    }

    /**
     * @param mixed $level
     * @param mixed[] $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        var_dump("LOG [{$level}]: " . $message, $context);
    }
}

(new IRMI())->run();
