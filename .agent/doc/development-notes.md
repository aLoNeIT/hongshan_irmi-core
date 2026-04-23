# 开发注意事项

## 基本原则

1. 类型安全：始终使用严格类型声明，避免类型相关 bug。
2. 空值处理：使用可空类型、空合并运算符和 nullsafe 运算符。
3. 错误处理：使用 `IRMILog` 记录异常，使用 `JsonTable` 封装返回结果。
4. 性能优化：注意大病历数据处理中的内存占用，优先复用中间结果。
5. 代码复用：公共逻辑提取到基类或工具方法。
6. 文档注释：公共 API 添加 PHPDoc。

## 常用命令

```bash
# 安装依赖
composer install

# 运行测试
composer test
composer irmi

# 执行指定测试目录
composer irmi -- -p alone

# 执行指定测试文件
composer irmi -- -p alone -n case1
```

## 版本信息

- 当前版本: `1.0.0`
- PHP 要求: `>=8.0`
- 依赖:
- `alonetech/simhash`: `~0.0`
- `ulrichsg/getopt-php`: `^4.0`
- `psr/log`: `^3.0`

## 历史标记

Powered By Cline && Qwen3.5-plus

