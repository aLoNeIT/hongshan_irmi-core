# AGENTS.md - 红杉健康医保智能审核核心类库开发指南

## 项目概述

**项目名称**: hongshan_irmi-core  
**描述**: 红杉健康医保智能审核核心类库（Intelligent review of medical insurance）  
**PHP 版本**: >= 8.0  
**许可证**: MulanPSL-2.0  

## 项目结构

```
hongshan_irmi-core/
├── src/
│   ├── IRMI.php              # 主管理类
│   ├── Driver.php            # 驱动基类
│   ├── IRMIException.php     # 异常类
│   ├── IRMILog.php           # 日志类
│   ├── Util.php              # 工具类
│   ├── config/               # 配置文件
│   ├── constant/             # 常量定义
│   ├── driver/               # 具体驱动实现
│   ├── interfaces/           # 接口定义
│   ├── processor/            # 处理器
│   │   ├── Base.php          # 处理器基类
│   │   ├── insurance/        # 医保项目处理器
│   │   ├── emr/              # 电子病历处理器
│   │   ├── patient/          # 患者档案处理器
│   │   └── hospital/         # 医院信息处理器
│   ├── rule/                 # 规则相关
│   └── struct/               # 数据结构
│       ├── Base.php          # 结构体基类
│       ├── IRMIRule.php      # 审核规则
│       ├── IRMIRuleSet.php   # 规则集合
│       ├── MedicalRecord.php # 病历结构
│       ├── MedicalInsuranceItem.php  # 医保项目
│       └── JsonTable.php     # 结果封装
├── tests/                    # 测试文件
├── composer.json             # 依赖配置
└── README.md                 # 项目说明
```

## 代码规范

### 1. 基础规范

```php
<?php

declare(strict_types=1);  // 必须声明严格类型

namespace hongshanhealth\irmi;  // 使用 PSR-4 命名空间

use hongshanhealth\irmi\struct\{
    IRMIRule,
    JsonTable,
    MedicalRecord
};  // 多个类使用 use 组导入
```

### 2. 类定义规范

- **类名**: 使用 PascalCase（大驼峰）命名
- **属性**: 使用 camelCase（小驼峰）命名，必须有类型声明
- **方法**: 使用 camelCase 命名，必须有返回类型声明
- **接口**: 以 `I` 开头，如 `IDetectInsuranceProcessor`

```php
/**
 * 类描述
 * 
 * @author 作者名 <email>
 */
class ClassName extends BaseClass implements InterfaceName
{
    /**
     * 属性描述
     *
     * @var 类型|null
     */
    public ?string $propertyName = null;
    
    /**
     * 方法描述
     *
     * @param Type $param 参数描述
     * @return ReturnType 返回值描述
     */
    public function methodName(Type $param): ReturnType
    {
        // 方法实现
    }
}
```

### 3. 类型声明

- 必须使用严格类型模式 `declare(strict_types=1);`
- 所有属性和方法参数必须有类型声明
- 可空类型使用 `?Type` 语法
- 混合类型使用 `mixed`
- 数组类型使用 `array` 或具体类型如 `string[]`

```php
public ?int $age = null;
public array $items = [];
public function getData(): mixed {}
public function getItems(): array {}
public function setItems(array $items): static {}
```

### 4. 数组操作

使用 PHP 8.0+ 的展开运算符：

```php
// 数组合并
$result = [
    ...$array1,
    ...$array2,
];

// 条件添加
$result = [
    ...$base,
    ...(!\is_null($data) ? ['data' => $data] : [])
];
```

### 5. 匹配表达式

使用 `match` 替代 `switch`：

```php
$propertyName = match (true) {
    $medicalRecord->principalDiagnosis == $rule->itemCode => 'principalDiagnosis',
    $medicalRecord->majorProcedure == $rule->itemCode => 'majorProcedure',
    default => null,
};
```

### 6. 空值处理

```php
// 使用空合并运算符
$value = $data['key'] ?? null;

// 使用空合并赋值运算符
$data['key'] ??= 'default';

// 使用 nullsafe 运算符
$value = $object?->property?->method();

// 类型检查
if (\is_null($value)) {}
if (!\is_null($value)) {}
```

### 7. 字符串处理

```php
// 使用单引号定义普通字符串
$message = 'Hello World';

// 使用双引号定义包含变量的字符串
$message = "Hello, {$name}!";

// 多行字符串使用 nowdoc/heredoc
$sql = <<<'SQL'
SELECT * FROM table
WHERE id = 1
SQL;
```

## 核心组件

### 1. IRMI 管理类

```php
use hongshanhealth\irmi\IRMI;

$irmi = IRMI::instance($config);
$driver = $irmi->store('shaanxi');  // 获取驱动
$result = $driver->detectInsurance($ruleSet, $medicalRecord);
```

### 2. Driver 驱动类

驱动继承自 `hongshanhealth\irmi\Driver` 基类：

```php
namespace hongshanhealth\irmi\driver;

use hongshanhealth\irmi\Driver;

class ShaanXi extends Driver 
{
    // 自定义实现
}
```

### 3. 处理器接口

所有处理器必须实现 `IDetectInsuranceProcessor` 接口：

```php
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;
use hongshanhealth\irmi\struct\{MedicalRecord, IRMIRule, JsonTable};

class CustomProcessor extends Base implements IDetectInsuranceProcessor
{
    public function detect(MedicalRecord $medicalRecord, IRMIRule $rule): JsonTable
    {
        // 实现检测逻辑
        return $this->jsonTable->success();
    }
}
```

### 4. 处理器基类

处理器应继承 `hongshanhealth\irmi\processor\Base`：

```php
namespace hongshanhealth\irmi\processor\insurance;

use hongshanhealth\irmi\processor\Base;
use hongshanhealth\irmi\interfaces\IDetectInsuranceProcessor;

class CustomProcessor extends Base implements IDetectInsuranceProcessor
{
    // 可用的基类方法：
    // - getRuleInfo(IRMIRule $rule): array
    // - getResult(int $errNo, string $errMsg, array $errData): JsonTable
    // - getMedicalItemIdByRule(MedicalRecord $medicalRecord, IRMIRule $rule): array
    // - filterMIItemByDateRange(array $miItems, IRMIRule $rule): array
}
```

### 5. 数据结构

#### IRMIRule - 审核规则

```php
$rule = new IRMIRule();
$rule->code = '01-01';
$rule->name = '重复收费';
$rule->itemCode = '120300001b';
$rule->itemName = '持续吸氧';
$rule->category = 1;  // 1-医疗项目，2-病历，3-医疗机构，4-患者档案
$rule->type = 1;      // 规则类型
$rule->subType = 1;   // 子类型
$rule->options = [    // 规则选项
    'time_range' => [1535731200, null],
    'exclude_items' => [...],
];
```

#### MedicalRecord - 病历结构

```php
$record = new MedicalRecord();
$record->code = '0000001';
$record->sex = 1;  // 1-男，2-女
$record->age = 20;
$record->visitType = 2;  // 1-门诊，2-住院
$record->inDate = 1722470400;
$record->outDate = 1722592800;
$record->diagnosis = ['A001', 'B002'];
$record->procedure = ['S001'];
$record->medicalInsuranceSet = [
    '1722441600' => [  // 日期戳
        '120300002b' => [  // 项目编码
            [
                'code' => '120300002b',
                'name' => 'XX 费用',
                'time' => 1722474000,
                'num' => 2,
                'price' => 19.00,
                'cash' => 19.00,
                'total_cash' => 38.00
            ]
        ]
    ]
];
```

#### JsonTable - 结果封装

```php
// 成功结果
$result = $jsonTable->success('检测通过');

// 错误结果
$result = $jsonTable->error('检测失败', 100, [
    'errors' => [
        [
            'msg' => '错误描述',
            'data' => [...]
        ]
    ]
]);

// 转换为数组
$array = $result->toArray();

// 转换为 JSON
$json = $result->toJson();
```

## 处理器类型映射

处理器类型在 `src/constant/Processor.php` 中定义：

```php
const TYPE_MAP = [
    self::CATEGORY_INSURANCE => [
        1 => '\hongshanhealth\irmi\processor\insurance\DuplicateCharge',      // 重复收费
        2 => '\hongshanhealth\irmi\processor\insurance\OverStandardCharge',   // 超标准收费
        3 => '\hongshanhealth\irmi\processor\insurance\OverInsuranceCharge',  // 超医保费用
        4 => '\hongshanhealth\irmi\processor\insurance\UnReasonableTreatment',// 不合理诊疗
        5 => '\hongshanhealth\irmi\processor\insurance\SplitCharge',          // 分解收费
        9 => '\hongshanhealth\irmi\processor\insurance\Custom',               // 自定义
    ],
    self::CATEGORY_EMR => [
        1 => '\hongshanhealth\irmi\processor\emr\MedicalRecord',              // 病历检测
    ],
];
```

## 规则类型说明

### 医保项目处理器 (CATEGORY_INSURANCE)

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

### 电子病历处理器 (CATEGORY_EMR)

| type | 名称 | sub_type | 说明 |
|------|------|----------|------|
| 1 | MedicalRecord | 1 | 手术与诊断不符 |
| 1 | MedicalRecord | 2 | 病历属性不合规 |

## 规则选项配置

### 通用选项

```php
'options' => [
    // 时间范围 [开始时间戳，结束时间戳]，null 表示不限制
    'time_range' => [1535731200, null],
    
    // 就诊类型：1-门诊，2-住院
    'visit_type' => 2,
    
    // 年龄范围 [开始年龄，结束年龄]
    'age_range' => [18, null],
]
```

### 项目检测选项

```php
'options' => [
    // 包含的项目
    'include_items' => ['item1', 'item2'],
    
    // 排除的项目
    'exclude_items' => [
        'item_code' => [
            'time_type' => 1,  // 1-按日，2-全部
        ]
    ],
    
    // 联合项目
    'combine_items' => ['item1', 'item2'],
    
    // 检测类型：1-按日，2-全部
    'detect_type' => 1,
]
```

### 病历属性检测

```php
'options' => [
    'property' => [
        [
            'name' => 'age',
            'operator' => '>',  // =, !=, <, <=, >, >=, in, not in, regex, between
            'value' => 18,
            'condition' => [...]  // 前置条件（可选）
        ]
    ]
]
```

## 错误码规范

```php
protected $errCode = [
    '2' => '未加载正确的 IMRI 配置',
    '10' => '未通过检测',
    // 100 以上业务错误
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

## 工具类方法

### Util 类常用方法

```php
use hongshanhealth\irmi\Util;

// JsonTable 相关
Util::jsuccess($msg, $data);           // 成功结果
Util::jerror($state, $msg, $data);     // 错误结果
Util::jdata($data, $msg);              // 带数据的成功结果
Util::isSuccess($jtable);              // 判断是否成功

// 字符串转换
Util::snake($string);                  // 驼峰转下划线
Util::camel($string);                  // 下划线转驼峰（首字母小写）
Util::studly($string);                 // 下划线转驼峰（首字母大写）

// 属性获取
Util::getPublicProps($object);         // 获取对象公共属性

// 公式检测
Util::detectFormula($props, $formulas, $dict);  // 根据公式检测属性

// 调试输出
Util::dd(...$vars);                    // 输出并终止
Util::dump(...$vars);                  // 输出
```

## 测试规范

### 执行测试

```bash
# 执行全部测试
composer irmi

# 执行指定目录下所有文件
composer irmi -- -p alone

# 执行指定目录下指定文件
composer irmi -- -p alone -n case1
```

### 测试数据结构

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

## 开发注意事项

1. **类型安全**: 始终使用严格类型声明，避免类型相关的 bug
2. **空值处理**: 使用可空类型和空合并运算符安全处理空值
3. **错误处理**: 使用 `IRMILog` 记录异常，使用 `JsonTable` 封装返回结果
4. **性能优化**: 使用缓存、延迟加载等技术优化性能
5. **代码复用**: 提取公共逻辑到基类或工具方法
6. **文档注释**: 为所有公共 API 添加完整的 PHPDoc 注释

## 常用命令

```bash
# 安装依赖
composer install

# 运行测试
composer test
composer irmi

# 代码风格检查
# (如果配置了 PHPStan/PHPCS)
```

## 版本信息

- **当前版本**: 1.0.0
- **PHP 要求**: >= 8.0
- **依赖**:
  - alonetech/simhash: ~0.0
  - ulrichsg/getopt-php: ^4.0
  - psr/log: ^3.0

## Powered By Cline && Qwen3.5-plus