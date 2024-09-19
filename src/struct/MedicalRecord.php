<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

use hongshanhealth\irmi\constant\Key;

/**
 * 病历类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class MedicalRecord extends Base
{

    /**
     * 就诊编码
     *
     * @var string|null
     */
    public $code = null;
    /**
     * 性别，1男，2女
     *
     * @var integer|null
     */
    public $sex = null;
    /**
     * 年龄，周岁
     *
     * @var integer|null
     */
    public $age = null;
    /**
     * 年龄，天数
     *
     * @var integer|null
     */
    public $ageDay = null;
    /**
     * 体重，单位g
     *
     * @var integer|null
     */
    public $weight = null;
    /**
     * 出生体重，单位g
     *
     * @var integer|null
     */
    public $birthWeight = null;
    /**
     * 科室编码
     *
     * @var string|null
     */
    public $inDepartment = null;
    /**
     * 住院天数
     *
     * @var integer|null
     */
    public $inDays = null;
    /**
     * 出院方式
     *
     * @var integer|null
     */
    public $outType = null;
    /**
     * 主要诊断编码
     *
     * @var string|null
     */
    public $principalDiagnosis = null;
    /**
     * 其他诊断编码数组
     *
     * @var array
     */
    public $secondaryDiagnosis = [];
    /**
     * 主要手术及操作编码
     *
     * @var string|null
     */
    public $majorProcedure = null;
    /**
     * 其他手术及操作编码数组
     *
     * @var array
     */
    public $secondaryProcedure = [];

    /**
     * 就诊类型
     * - 1：门诊
     * - 2：住院
     *
     * @var integer|null
     */
    public $visitType = null;

    /**
     * 就诊日期
     *
     * @var integer|null
     */
    public $inDate = null;
    /**
     * 出院日期
     *
     * @var integer|null
     */
    public $outDate = null;

    /**
     * 医保项目集合，多维数组，key是日期，value是医保项目数组  
     * 参考格式：{"1726243200":{"120300002b":{"num":2,"price":19.00,"total_cash":38.00}}}
     *
     * @var array
     */
    public $medicalInsuranceSet = [];

    /**
     * 扩展数据，主要用于在计算过程中存储临时变量
     *
     * @var array
     */
    protected $tmpData = [];

    /**
     * 获取临时数据
     *
     * @param string $key 键名
     * @return mixed 返回临时数据
     */
    public function getTmpData(string $key): mixed
    {
        return $this->tmpData[$key] ?? null;
    }

    /**
     * 设置临时数据
     *
     * @param string $key 键名
     * @param mixed $value 键值
     * @return void
     */
    public function setTmpData(string $key, mixed $value): void
    {
        $this->tmpData[$key] = $value;
    }

    /** @inheritDoc */
    public function load(array $data): static
    {
        parent::load($data);
        $tmpData = [];
        foreach ($this->medicalInsuranceSet as $date => $items) {
            foreach ($items as $itemCode => $item) {
                $tmpData[$itemCode][] = (new MedicalInsuranceItem())->load($item);
            }
        }
        $this->setTmpData(Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE, $tmpData);
        return $this;
    }
}
