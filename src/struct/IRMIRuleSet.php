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
 * @method mixed detectInsurance(MedicalRecord $record, IRMIRuleOption $ruleOption = null)
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
     * 原始数据
     *
     * @var array
     */
    protected array $originData = [];

    /**
     * 当前规则集子项
     *
     * @var IRMIRule[]
     */
    protected array $rules = [];

    /**
     * 以项目编码为键，规则编码数组为值的关联数组
     *
     * @var array
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
        $this->originData = $data;
        $rules = $data['rules'] ?? null;
        if (\is_null($rules)) {
            throw new IRMIException('集合中未存在有效的规则数据');
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
     * 过滤规则，生成新的规则集
     *
     * @param IRMIRuleOption $ruleOption 规则选项
     * @param boolean $force 是否强制克隆新对象
     * @return static 返回过滤后的规则集
     */
    public function filter(IRMIRuleOption $ruleOption, bool $force = false): static
    {
        $whiteList = $ruleOption->whiteList;
        $blackList = $ruleOption->blackList;
        // 未设置黑白名单，且不是强制克隆新对象，则直接返回当前对象
        if (empty($whiteList) && empty($blackList)) {
            if (false === $force) {
                return $this;
            } else {
                // 构造新对象并返回
                return (new IRMIRuleSet())->load($this->originData);
            }
        }
        $rules = [];
        $originRules = $this->originData['rules'] ?? [];
        /** @var array $rule */
        foreach ($originRules as $rule) {
            // 白名单
            if (!empty($whiteList)) {
                // 白名单非空，则必须在白名单中才可以添加
                if (\in_array($rule['code'], $whiteList)) {
                    $rules[] = $rule;
                    continue;
                }
            } else if (!empty($blackList)) {
                // 黑名单
                if (!\in_array($rule['code'], $blackList)) {
                    $rules[] = $rule;
                    continue;
                }
            }
        }
        return (new IRMIRuleSet())->load([
            'code' => $this->code,
            'name' => $this->name,
            'rules' => $rules
        ]);
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
        $rules = [];
        if (!empty($itemCodes)) {
            // 先根据项目编码获取到规则集合
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
