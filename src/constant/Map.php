<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\constant;

/**
 * 常量映射
 */
class Map
{

    /**
     * 操作符别名映射
     */
    const OPERATOR_ALIAS = [
        '=' => '等于',
        '<' => '小于',
        '>' => '大于',
        '<=' => '小于等于',
        '>=' => '大于等于',
        '!=' => '不等于',
        'in' => '在列表中',
        'not in' => '不在列表中'
    ];
    /**
     * 病例信息别名映射
     */
    const MEDICAL_RECORD_ALIAS = [
        'code' => '就诊号',
        'sex' => '性别',
        'age' => '年龄',
        'age_day' => '年龄(天)',
        'weight' => '体重(克)',
        'birth_weight' => '出生体重(克)',
        'in_branch' => '科室',
        'out_branch' => '出院科室',
        'in_days' => '住院天数',
        'visit_type' => '就诊类型',
        'in_date' => '就诊时间',
        'out_date' => '出院时间',
        'hospital_code' => '医院编码',
        'hospital_type' => '医院类型',
        'hospital_level' => '医院级别',
        'hospital_bussiness_type' => '医院经营类型',
    ];
}
