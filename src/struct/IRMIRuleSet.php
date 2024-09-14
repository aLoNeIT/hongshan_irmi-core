<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

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
    protected $code = null;

    /**
     * 规则集名称
     *
     * @var string|null
     */
    protected $name = null;

    /**
     * 当前规则集子项
     *
     * @var array
     */
    protected $data = [];
}
