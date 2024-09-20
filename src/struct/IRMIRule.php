<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

/**
 * 医保智能审核规则类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMIRule extends Base
{
    /**
     * 规则编码
     *
     * @var string|null
     */
    public ?string $code = null;

    /**
     * 规则名称
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * 项目编码，如医保项目编码、药品编码
     * 
     * @var string|null
     */
    public ?string $itemCode = null;

    /**
     * 项目名称
     *
     * @var string|null
     */
    public ?string $itemName = null;

    /**
     * 规则类型
     * 
     * - 1：重复收费
     * - 2：超标准收费
     * - 3：超医保支付范围
     * - 4：不合理诊疗
     * - 5：自立项目收费
     * - 6：串换收费
     * - 7：分解收费
     * - 8：过度诊疗
     * - 9：过度检查
     * - 10：资质不符
     * - 11：虚构医药服务项目
     * - 12：分解住院
     * - 13：低标准入院
     * - 9999: 自定义
     * 
     * @var integer|null
     */
    public ?int $type = null;

    /**
     * 子类型  
     * 
     * 重复收费  
     * - 1：该项目于制定项目同事收费
     * 
     * 超标准收费  
     * - 1：指定项目数量之和超过住院天数
     * - 2：指定项目数量之和超过另一个指定项目
     * - 3：指定项目当日收费超过X（元、次、小时）
     * - 4：与指定项目当天同时检测，第二项未按X%收费
     * - 5：当前项目收费超24（小时）
     * 
     * 超医保支付范围
     * - 1：
     * - 2：
     *
     * @var integer|null
     */
    public ?int $subType = null;

    /**
     * 规则选项，kv结构，具体内容各算法内定义
     *
     * @var array
     */
    public array $options = [];

    /**
     * 处理维度
     * 
     * 位运算
     * - 1：个人单次
     * - 2：个人批量
     * - 4：个人多次
     *
     * @var integer|null
     */
    public ?int $detectDimension = null;
    /**
     * 就诊类型
     * 
     * - null: 不限制
     * - 1：门诊
     * - 2：住院
     *
     * @var integer|null
     */
    public ?int $visitType = null;
    /**
     * 检测类型
     * 
     * - null: 不限制
     * - 1：单日
     * - 2：区间
     *
     * @var integer|null
     */
    public ?int $detectType = null;
    /**
     * 规则描述
     *
     * @var string|null
     */
    public ?string $description = null;
}
