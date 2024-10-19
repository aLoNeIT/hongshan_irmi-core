<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\constant;

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
        2 => '\hongshanhealth\irmi\processor\OverStandardCharge'
    ];
    /**
     * 医保项目
     */
    const CATEGORY_INSURANCE = 1;
    /**
     * 电子病历
     */
    const CATEGORY_EMR = 2;
    /**
     * 换着档案
     */
    const CATEGORY_PATIENT = 3;
    /**
     * 医院信息
     */
    const CATEGORY_HOSPITAL = 4;
}
