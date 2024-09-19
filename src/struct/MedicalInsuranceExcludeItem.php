<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

/**
 * 医保排除项目结构体
 * 待定
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class MedicalInsuranceExcludeItem extends Base
{
    /**
     * 编码
     * 
     * @var string|null 
     */
    public $code = null;

    /**
     * 时间类型
     * 
     * @var int|null 
     */
    public $timeType = null;

    /**
     * 数量
     *
     * @var integer|null
     */
    public $num = null;
}
