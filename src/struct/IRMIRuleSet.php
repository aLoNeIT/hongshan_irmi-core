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
            $rule = (new IRMIRule())->load($rule);
            $this->rules[$rule->code] = $rule;
            $this->itemRules[$rule->itemCode][] = $rule->code;
        }
        return $this;
    }
    /**
     * 过滤规则，生成新的规则集
     *
     * @param IRMIRuleOption|null $ruleOption 规则选项
     * @return static 返回过滤后的规则集对象
     */
    public function filter(?IRMIRuleOption $ruleOption = null): static
    {
        $rules = [];
        // 根据黑白名单构建处理函数，优先白名单
        $whiteList = $ruleOption?->whiteList ?: [];
        $blackList = $ruleOption?->blackList ?: [];
        $fnFilter = function (string $code) {
            return true;
        };
        if (!empty($whiteList)) {
            $fnFilter = function (string $code) use ($whiteList) {
                return \in_array($code, $whiteList);
            };
        } else if (!empty($blackList)) {
            $fnFilter = function (string $code) use ($blackList) {
                return !\in_array($code, $blackList);
            };
        }
        // 过滤
        $originRules = $this->originData['rules'] ?? [];
        /** @var array $rule */
        foreach ($originRules as $rule) {
            if ($fnFilter($rule['code'])) {
                $rules[] = $rule;
            }
        }
        return (new static())->load(
            [
                'code' => $this->code,
                'name' => $this->name,
                'rules' => $rules
            ]
        );
    }

    /**
     * 通过项目编码获取匹配的规则
     *
     * @param string[] $itemCodes 项目编码集合
     * @param IRMIRuleOption|null $ruleOption 规则选项
     * @return IRMIRule[] 返回规则对象
     */
    public function getRulesByItemCode(array $itemCodes, ?IRMIRuleOption $ruleOption = null): array
    {
        // 获取规则集中包含指定项目编码的规则的编码交集
        $itemCodes = \array_intersect(\array_keys($this->itemRules), $itemCodes);
        $rules = [];
        // 根据黑白名单构建处理函数，优先白名单
        $whiteList = $ruleOption?->whiteList ?: [];
        $blackList = $ruleOption?->blackList ?: [];
        $fnFilter = function (string $code) {
            return true;
        };
        if (!empty($whiteList)) {
            $fnFilter = function (string $code) use ($whiteList) {
                return \in_array($code, $whiteList);
            };
        } else if (!empty($blackList)) {
            $fnFilter = function (string $code) use ($blackList) {
                return !\in_array($code, $blackList);
            };
        }
        if (!empty($itemCodes)) {
            // 先根据项目编码获取到规则集合
            foreach ($itemCodes as $itemCode) {
                // 再通过规则集合中的规则编码获取规则对象
                foreach ($this->itemRules[$itemCode] as $code) {
                    // 过滤处理
                    if ($fnFilter($code)) {
                        $rules[] = $this->rules[$code];
                    }
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
