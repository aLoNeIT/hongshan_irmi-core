# 核心组件

## IRMI 管理类

```php
use hongshanhealth\irmi\IRMI;

$irmi = IRMI::instance($config);
$driver = $irmi->store('shaanxi');
$result = $driver->detectInsurance($ruleSet, $medicalRecord);
```

## Driver 驱动类

驱动继承自 `hongshanhealth\irmi\Driver`：

```php
namespace hongshanhealth\irmi\driver;

use hongshanhealth\irmi\Driver;

class ShaanXi extends Driver
{
}
```

驱动核心职责：

- 初始化地区配置。
- 加载规则集。
- 按规则类型分发处理器。
- 汇总处理器结果为 `JsonTable` 数组。

## 处理器接口

所有检测处理器必须实现 `IDetectInsuranceProcessor`：

```php
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\processor\Base;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable};

class CustomProcessor extends Base implements IDetectInsuranceProcessor
{
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        return $this->jsonTable->success();
    }
}
```

## 处理器基类

处理器应继承 `hongshanhealth\irmi\processor\Base` 或对应分类下的基类。

常用基类方法：

- `getRuleInfo(IRMIRule $rule): array`
- `getResult(int $errNo, string $errMsg, array $errData): JsonTable`
- `getMedicalItemIdByRule(MedicalRecord $medicalRecord, IRMIRule $rule): array`
- `filterMIItemByDateRange(array $miItems, IRMIRule $rule): array`

## 数据结构

### IRMIRule

```php
$rule = new IRMIRule();
$rule->code = '01-01';
$rule->name = '重复收费';
$rule->itemCode = '120300001b';
$rule->itemName = '持续吸氧';
$rule->category = 1;
$rule->type = 1;
$rule->subType = 1;
$rule->options = [
    'time_range' => [1535731200, null],
    'exclude_items' => [],
];
```

### MedicalRecord

```php
$record = new MedicalRecord();
$record->code = '0000001';
$record->sex = 1;
$record->age = 20;
$record->visitType = 2;
$record->inDate = 1722470400;
$record->outDate = 1722592800;
$record->diagnosis = ['A001', 'B002'];
$record->procedure = ['S001'];
$record->medicalInsuranceSet = [
    '1722441600' => [
        '120300002b' => [
            [
                'code' => '120300002b',
                'name' => 'XX 费用',
                'time' => 1722474000,
                'num' => 2,
                'price' => 19.00,
                'cash' => 19.00,
                'total_cash' => 38.00,
            ],
        ],
    ],
];
```

### JsonTable

```php
$result = $jsonTable->success('检测通过');

$result = $jsonTable->error('检测失败', 100, [
    'errors' => [
        [
            'msg' => '错误描述',
            'data' => [],
        ],
    ],
]);

$array = $result->toArray();
$json = $result->toJson();
```

