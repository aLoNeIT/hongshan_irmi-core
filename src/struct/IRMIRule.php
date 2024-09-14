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
    public $code = null;

    /**
     * 规则名称
     *
     * @var string|null
     */
    public $name = null;

    /**
     * 项目编码，如医保项目编码、药品编码
     * 
     * @var string|null
     */
    public $itemCode = null;

    /**
     * 项目名称
     *
     * @var string|null
     */
    public $itemName = null;

    /**
     * 规则类型
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
    public $type = null;

    /**
     * 规则选项，kv结构，具体内容各算法内定义
     *
     * @var array
     */
    public $options = [];

    /**
     * 处理维度，位运算
     * - 1：个人单次
     * - 2：个人批量
     * - 4：个人多次
     *
     * @var integer|null
     */
    public $detectDimension = null;

    /**
     * 规则描述
     *
     * @var string|null
     */
    public $description = null;
}
