<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor;

/**
 * 处理器常量
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class Processor
{
    /**
     * 处理器类型映射关系表
     */
    const TYPE_MAP = [
        1 => '\hongshanhealth\irmi\processor\DuplicateCharge',
        2 => '\hongshanhealth\irmi\processor\SuperStandardCharge'
    ];
}
