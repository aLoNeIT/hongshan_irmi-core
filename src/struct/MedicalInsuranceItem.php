<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

/**
 * 医保项目
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class MedicalInsuranceItem extends Base
{

    /**
     * 编码
     * 
     * @var string|null 
     */
    public $code = null;

    /**
     * 时间
     * 
     * @var int|string|null 
     */
    public $time = null;

    /**
     * 数量
     *
     * @var integer|null
     */
    public $num = null;

    /**
     * 单价
     * 
     * @var float|null 
     */
    public $price = null;

    /**
     * 总价
     * 
     * @var float|null 
     */
    public $totalPrice = null;
}
