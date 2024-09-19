<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\Util;

/**
 * 医保智能审核规则集合
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMIRuleSet extends Base
{

    /**
     * 规则集编码
     *
     * @var string|null
     */
    public $code = null;

    /**
     * 规则集名称
     *
     * @var string|null
     */
    public $name = null;

    /**
     * 当前规则集子项
     *
     * @var IRMIRule[]
     */
    protected $rules = [];

    /**
     * 以项目编码为键，规则编码数组为值的关联数组
     *
     * @var array
     */
    protected $itemRules = [];

    /** @inheritDoc */
    public function load(array $data): static
    {
        $rules = $data['rules'] ?: null;
        if (\is_null($rules)) {
            throw new IRMIException('集合中未存在有效的规则内容');
        }
        unset($data['rules']);
        parent::load($data);
        foreach ($rules as $rule) {
            $rule = (new IRMIRule())->load($rule);
            $this->rules[$rule->code] = $rule;
            $this->itemRules[$rule->itemCode][] = $rule->code;
        }
        return $this;
    }
    /**
     * 通过项目编码获取匹配的规则
     *
     * @param string[] $itemCodes 项目编码集合
     * @return IRMIRule[] 返回规则对象
     */
    public function getRulesByItemCode(array $itemCodes): array
    {
        // 获取规则集中包含指定项目编码的规则的编码交集
        $itemCodes = \array_intersect(\array_keys($this->itemRules), $itemCodes);
        if (!empty($itemCodes)) {
            // 想根据项目编码获取到规则集合
            foreach ($itemCodes as $itemCode) {
                // 再通过规则集合中的规则编码获取规则对象
                foreach ($this->itemRules[$itemCode] as $code) {
                    $rules[] = $this->rules[$code];
                }
            }
        }

        return $rules;
    }
    /**
     * 检测
     *
     * @param MedicalRecord $record 病历信息
     * 
     * @return array 返回检测结果，JsonTable格式数组
     */
    public function detect(MedicalRecord $record): array
    {

        return \is_null($this->driver)
            ? Util::jerror(1, '驱动未加载')
            : $this->driver->detect($record, $this);
    }
}
