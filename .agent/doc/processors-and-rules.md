# 处理器与规则

## 处理器类型映射

处理器类型在 `src/constant/Processor.php` 中定义：

```php
const TYPE_MAP = [
    self::CATEGORY_INSURANCE => [
        1 => '\hongshanhealth\irmi\processor\insurance\DuplicateCharge',
        2 => '\hongshanhealth\irmi\processor\insurance\OverStandardCharge',
        3 => '\hongshanhealth\irmi\processor\insurance\OverInsuranceCharge',
        4 => '\hongshanhealth\irmi\processor\insurance\UnReasonableTreatment',
        5 => '\hongshanhealth\irmi\processor\insurance\SplitCharge',
        9 => '\hongshanhealth\irmi\processor\insurance\Custom',
    ],
    self::CATEGORY_EMR => [
        1 => '\hongshanhealth\irmi\processor\emr\MedicalRecord',
    ],
];
```

## 医保项目处理器

| type | 名称 | sub_type | 说明 |
|------|------|----------|------|
| 1 | DuplicateCharge | 1 | 重复收费 |
| 2 | OverStandardCharge | 1 | 超标准收费 - 数量超限 |
| 2 | OverStandardCharge | 2 | 超标准收费 - 折扣检测 |
| 3 | OverInsuranceCharge | 1 | 超医保费用 |
| 3 | OverInsuranceCharge | 2 | 医保支付范围检测 |
| 4 | UnReasonableTreatment | 1 | 不合理诊疗 |
| 4 | UnReasonableTreatment | 2 | 属性不符合要求 |
| 5 | SplitCharge | 1 | 分解收费 |
| 9 | Custom | 1 | 自定义检测 |

## 电子病历处理器

| type | 名称 | sub_type | 说明 |
|------|------|----------|------|
| 1 | MedicalRecord | 1 | 手术与诊断不符 |
| 1 | MedicalRecord | 2 | 病历属性不合规 |

## 规则数据结构

规则集测试数据支持 `rule` 单规则或 `rules` 多规则：

```json
{
    "code": "规则集编码",
    "name": "规则集名称",
    "rules": [
        {
            "code": "规则编码",
            "name": "规则名称",
            "item_code": "项目编码",
            "item_name": "项目名称",
            "category": 1,
            "type": 1,
            "sub_type": 1,
            "options": {}
        }
    ]
}
```

