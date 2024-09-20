<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\interfaces\IDetectProcessor;
use hongshanhealth\irmi\processor\Processor;
use hongshanhealth\irmi\struct\IRMIRuleSet;
use hongshanhealth\irmi\struct\MedicalRecord;

/**
 * 驱动基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
abstract class Driver
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        'type' => '',
    ];
    /**
     * 错误码
     *
     * @var array
     */
    protected $errCode = [
        '2' => '未加载正确的IMRI配置',
        '10' => '未通过检测',
        // 100以上业务错误
        '100' => '重复收费',
        '101' => '重复收费[无其他病理检查]',
        '102' => '重复收费[其他项目收费达指定数量]',
        '200' => '超标准收费',
    ];

    /**
     * 医保智能审核规则集
     *
     * @var IRMIRuleSet[]
     */
    protected $ruleSets = [];

    /**
     * 构造函数
     * 
     * @param array $config 配置信息
     */
    public function __construct(array $config = [])
    {
        $this->config = \array_merge($this->config, $config);
        $this->initialize();
    }
    /**
     * 初始化函数
     *
     * @return void
     */
    protected function initialize(): void {}
    /**
     * 切换医保智能审核规则集合
     *
     * @param string $code 医保智能审核规则集合编码
     * @return IRMIRuleSet 返回指定集合编码的集合对象
     */
    public function switch(string $code): IRMIRuleSet
    {
        if (!isset($this->ruleSet[$code])) {
            $this->ruleSets[$code] = (new IRMIRuleSet())->setDriver($this);
        }
        return $this->ruleSets[$code];
    }
    /**
     * 检测
     *
     * @param MedicalRecord $record 病历信息
     * @param IRMIRuleSet $ruleSet 规则集合
     * @return array 返回检测结果，JsonTable格式数组
     */
    public function detect(MedicalRecord $record, IRMIRuleSet $ruleSet): array
    {
        // 根据规则集合，提取适用的规则依次进行计算
        $miItemSet = $record->getTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE);
        $itemCodes = \array_keys($miItemSet);
        $rules = $ruleSet->getRulesByItemCode($itemCodes);
        $errors = [];
        foreach ($rules as $rule) {
            // 根据规则类型创建对应的处理器
            $class = Processor::TYPE_MAP[$rule->type];
            /** @var IDetectProcessor $processor */
            $processor = new $class();
            // 执行处理器
            if (!$processor instanceof IDetectProcessor) {
                continue;
            }
            $jResult = $processor->detect($record, $rule);
            // 获取错误信息
            if (!$jResult->isSuccess()) {
                // 记录该次对比错误内容，每个元素都是一个JsonTable的数组类型
                $errors[] = $jResult->toArray();
            }
        }
        return empty($errors) ? Util::jsuccess() : $this->jcode(10, null, $errors);
    }

    /**
     * 加载数据
     *
     * @param string $code 集合编码
     * @param string $name 集合名称
     * @param array $data 规则集合内容
     * @return static 返回当前驱动实例
     */
    public function load(string $code, string $name, array $data): self
    {
        $ruleSet = $this->switch($code);
        $ruleSet->load($data);
        return $this;
    }

    /**
     * 根据状态码获取JsonTable格式信息
     *
     * @param integer $code 状态码
     * @param string $msg 简要信息
     * @param mixed $data 数据
     * @return array
     */
    protected function jcode(int $code, ?string $msg = null, $data = null): array
    {
        if (0 == $code) {
            // 成功
            return Util::jsuccess($msg, $data);
        }
        $errMsg = $this->errCode[(string)$code] ?? '系统异常';
        return Util::jerror(
            $code,
            $errMsg . (!\is_null($msg) && 'failed' != $msg ? "[{$msg}]" : ''),
            $data
        );
    }
}
