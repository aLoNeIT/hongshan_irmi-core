<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

use hongshanhealth\irmi\struct\IRMIRuleSet;

/**
 * 驱动基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class Driver
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
        '2' => '未加载正确的DRG配置',
        '10' => '未匹配到符合要求的DRG分组',
        '11' => '不符合当前MDC入组规则',
        '12' => '未匹配到ADRG分组，进入歧义组',
        '13' => '次要诊断不存在于MCC或CC数据中',
        '14' => '主要诊断未通过MCC或CC排除表校验',
        '15' => '不符合当前ADRG入组规则',
        '16' => '未匹配到任意MDC分组',
        // 100以上业务错误
        '100' => '住院天数极端值病例暂不纳入DRG付费'
    ];

    /**
     * 医保智能审核结构体集合，kv结构
     *
     * @var array
     */
    protected $irmiSet = [];

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
     * 切换DRG集合
     *
     * @param string $code drgSet集合编码
     * @return ChsDrgSet 返回切换后的DRG集合
     */
    public function switch(string $code): IRMIRuleSet
    {
        if (!isset($this->chsDrgSet[$code])) {
            $this->irmiSet[$code] = new IRMIRuleSet();
        }
        return $this->irmiSet[$code];
    }
}
