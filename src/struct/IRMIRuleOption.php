<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

/**
 * 医保智能审核规则选项
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class IRMIRuleOption extends Base
{
    /**
     * 规则白名单
     *
     * @var string[]
     */
    public array $whiteList = [];
    /**
     * 规则黑名单
     *
     * @var string[]
     */
    public array $blackList = [];
}
