# 工具类与测试

## Util 常用方法

```php
use hongshanhealth\irmi\Util;

Util::jsuccess($msg, $data);
Util::jerror($state, $msg, $data);
Util::jdata($data, $msg);
Util::isSuccess($jtable);

Util::snake($string);
Util::camel($string);
Util::studly($string);

Util::getPublicProps($object);
Util::detectFormula($props, $formulas, $dict);

Util::dd(...$vars);
Util::dump(...$vars);
```

## 测试命令

```bash
# 执行全部默认测试
composer irmi

# 执行指定目录下所有文件
composer irmi -- -p alone

# 执行指定目录下指定文件
composer irmi -- -p alone -n case1

# 执行 tests/App.php
composer test
```

## 测试运行环境

项目声明 PHP `>=8.0`，但当前代码使用关联数组展开语法，实际运行建议使用 PHP `8.1+`。

测试依赖 `bcmath` 扩展，因为核心检测流程会调用 `bcscale()`。

可用 Docker 示例：

```bash
docker run --rm -v "${PWD}:/app" -w /app php:cli8.1-debian-dm8-v1 php ./tests/IRMI.php -- -p alone
```

## 测试数据结构

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
    ],
    "medical_records": {
        "success": [],
        "fail": []
    },
    "dict": {}
}
```

失败用例可选 `expected_result`，用于校验规则数量、规则编码或错误数量。

