<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\constant\Key;
use hongshanhealth\irmi\IRMIException;
use hongshanhealth\irmi\processor\Base as BaseProcessor;
use hongshanhealth\irmi\struct\{
    MedicalRecord,
    IRMIRule,
    MedicalInsuranceItem
};
use hongshanhealth\irmi\Util;

/**
 * 电子病历计算器基类
 */
class Base extends BaseProcessor {}
