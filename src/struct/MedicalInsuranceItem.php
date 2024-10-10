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
    public ?string $code = null;

    /**
     * 名称
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * 时间
     * 
     * @var integer|null 
     */
    public ?int $time = null;

    /**
     * 日期
     *
     * @var integer|null
     */
    public ?int $date = null;

    /**
     * 数量
     *
     * @var integer|null
     */
    public ?int $num = null;

    /**
     * 标准单价
     * 
     * @var float|null 
     */
    public ?float $price = null;

    /**
     * 实收单价
     *
     * @var float|null
     */
    public ?float $cash = null;

    /**
     * 总价
     * 
     * @var float|null 
     */
    public ?float $totalCash = null;
}
