# 代码规范

## 基础规范

```php
<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

use hongshanhealth\irmi\struct\{
    IRMIRule,
    JsonTable,
    MedicalRecord
};
```

要求：

- PHP 文件必须声明 `declare(strict_types=1);`。
- 使用 PSR-4 命名空间。
- 多个同命名空间类优先使用 `use` 组导入。
- 普通字符串使用单引号；包含变量的字符串使用双引号。

## 类定义规范

- 类名使用 PascalCase。
- 属性使用 camelCase，并尽量声明类型。
- 方法使用 camelCase，必须声明参数类型和返回类型。
- 接口以 `I` 开头，例如 `IDetectInsuranceProcessor`。
- 公共 API 添加完整 PHPDoc。

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
     * @var string|null
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
        return new ReturnType();
    }
}
```

## 类型声明

```php
public ?int $age = null;
public array $items = [];

public function getData(): mixed
{
    return null;
}

public function getItems(): array
{
    return $this->items;
}

public function setItems(array $items): static
{
    $this->items = $items;
    return $this;
}
```

## 数组操作

使用 PHP 8.0+ 展开运算符：

```php
$result = [
    ...$array1,
    ...$array2,
];

$result = [
    ...$base,
    ...(!\is_null($data) ? ['data' => $data] : []),
];
```

## 匹配表达式

优先使用 `match` 替代复杂 `switch`：

```php
$propertyName = match (true) {
    $medicalRecord->principalDiagnosis == $rule->itemCode => 'principalDiagnosis',
    $medicalRecord->majorProcedure == $rule->itemCode => 'majorProcedure',
    default => null,
};
```

## 空值处理

```php
$value = $data['key'] ?? null;
$data['key'] ??= 'default';
$value = $object?->property?->method();

if (\is_null($value)) {
}

if (!\is_null($value)) {
}
```

## 字符串处理

```php
$message = 'Hello World';
$message = "Hello, {$name}!";

$sql = <<<'SQL'
SELECT * FROM table
WHERE id = 1
SQL;
```

