# 规则选项与错误码

## 通用选项

```php
'options' => [
    'time_range' => [1535731200, null],
    'visit_type' => 2,
    'age_range' => [18, null],
]
```

说明：

- `time_range`: 时间范围 `[开始时间戳, 结束时间戳]`，`null` 表示不限制。
- `visit_type`: 就诊类型，`1` 为门诊，`2` 为住院。
- `age_range`: 年龄范围 `[开始年龄, 结束年龄]`。

## 项目检测选项

```php
'options' => [
    'include_items' => ['item1', 'item2'],
    'exclude_items' => [
        'item_code' => [
            'time_type' => 1,
        ],
    ],
    'combine_items' => ['item1', 'item2'],
    'detect_type' => 1,
]
```

说明：

- `include_items`: 包含的项目。
- `exclude_items`: 排除的项目。
- `combine_items`: 联合项目。
- `detect_type`: 检测类型，`1` 为按日，`2` 为全部。
- `time_type`: 时间类型，常用 `1` 按日，`2` 全部。

## 病历属性检测

```php
'options' => [
    'property' => [
        [
            'name' => 'age',
            'operator' => '>',
            'value' => 18,
            'condition' => [],
        ],
    ],
]
```

`operator` 支持：

- `=`
- `!=`
- `<`
- `<=`
- `>`
- `>=`
- `in`
- `not in`
- `regex`
- `between`

## 错误码规范

```php
protected $errCode = [
    '2' => '未加载正确的 IMRI 配置',
    '10' => '未通过检测',
    '100' => '重复收费',
    '200' => '超标准收费',
    '201' => '超标准收费 [当前项目计费量超过要求]',
    '300' => '超医保支付范围',
    '400' => '不合理诊疗',
    '500' => '分解收费',
    '900' => '其他违规',
    '1100' => '病历属性不合规',
];
```

