<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

use hongshanhealth\irmi\Driver;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRuleOption};

/**
 * 医保智能审核规则集合
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 * 
 * @method array detectInsurance(MedicalRecord $record, IRMIRuleOption $ruleOption = null)
 */
class IRMIRuleSet extends Base
{

    /**
     * 规则集编码
     *
     * @var string|null
     */
    public ?string $code = null;

    /**
     * 规则集名称
     *
     * @var string|null
     */
    public ?string $name = null;
    /**
     * 字典数据
     * 参考：{"drug":{"1",["XJ01","XJ02"]}}
     *
     * @var array<string,array<string,mixed>>
     */
    public array $dict = [];
    /**
     * 原始数据
     *
     * @var array
     */
    protected array $originData = [];

    /**
     * 当前规则集子项
     *
     * @var array<string,array<string,IRMIRule>> 
     */
    protected array $rules = [];

    /**
     * 以项目编码为键，规则编码数组为值的关联数组
     *
     * @var array<string,array<string,string[]>>
     */
    protected array $itemRules = [];

    /**
     * 驱动类
     *
     * @var Driver
     */
    protected ?Driver $driver = null;

    /**
     * 设置关联的驱动类
     *
     * @param Driver $driver 驱动实例对象
     * 
     * @return static 返回当前结构体
     */
    public function setDriver(Driver $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * 获取当前对象保存的驱动对象
     *
     * @return Driver 返回驱动对象
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /** @inheritDoc */
    public function load(array $data): static
    {
        $rules = $data['rules'] ?? null;
        if (\is_null($rules)) {
            throw new IRMIException('集合中未存在有效的规则数据');
        }
        $this->originData = $data;
        unset($data['rules']);
        parent::load($data);
        $this->rules = [];
        $this->itemRules = [];
        foreach ($rules as $rule) {
            $rule = (new IRMIRule())->setIRMIRuleSet($this)->load($rule);
            $this->rules[(string)$rule->category][$rule->code] = $rule;
            $this->itemRules[(string)$rule->category][$rule->itemCode][] = $rule->code;
        }
        return $this;
    }

    /**
     * 通过项目编码获取匹配的规则
     *
     * @param int $category 规则类别
     * @param string[] $itemCodes 项目编码集合
     * @param IRMIRuleOption|null $ruleOption 规则选项
     * @return IRMIRule[] 返回规则对象
     */
    public function getRulesByItemCode(int $category, array $itemCodes, ?IRMIRuleOption $ruleOption = null): array
    {
        $rules = [];
        // 根据黑白名单构建处理函数，优先白名单
        $whiteList = $ruleOption?->whiteList ?: [];
        $blackList = $ruleOption?->blackList ?: [];
        // 白名单，再黑名单，二选一
        if (!empty($whiteList)) {
            $fnFilter = function (string $code) use ($whiteList) {
                return \in_array($code, $whiteList);
            };
        } else if (!empty($blackList)) {
            $fnFilter = function (string $code) use ($blackList) {
                return !\in_array($code, $blackList);
            };
        } else {
            $fnFilter = function (string $code) {
                return true;
            };
        }
        $categoryRules = $this->itemRules[(string)$category] ?? [];
        $matchedItemCodes = \array_intersect(\array_keys($categoryRules), $itemCodes);
        foreach ($matchedItemCodes as $itemCode) {
            // 从指定类别的项目规则中获取规则编码
            foreach ($categoryRules[$itemCode] as $code) {
                // 过滤处理
                if ($fnFilter($code)) {
                    $rules[] = $this->rules[(string)$category][$code];
                }
            }
        }
        return $rules;
    }
    /**
     * 获取字典数据
     * 
     * @param string $type 字典类型
     * @param string $code 字典编码
     * @return array 字典数据
     */
    public function getDict(string $type, string $code): array
    {
        return $this->dict[$type][$code] ?? [];
    }
    /**
     * 调用内置驱动类相关方法
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (
            !\is_null($this->driver)
            && \method_exists($this->driver, $name)
        ) {
            return $this->driver->$name($this, ...$arguments);
        }
        throw new IRMIException('未定义的方法：' . $name);
    }
}
