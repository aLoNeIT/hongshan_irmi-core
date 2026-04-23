# AGENTS.md - hongshan_irmi-core

## 项目总结

`hongshan_irmi-core` 是红杉健康医保智能审核核心类库，用于根据医保规则集检测病历中的重复收费、超标准收费、超医保费用、不合理诊疗、分解收费和病历属性不合规等问题。

项目使用 PHP `>=8.0`，基于 PSR-4 命名空间 `hongshanhealth\irmi`。核心入口是 `IRMI` 管理类，地区能力通过 `Driver` 驱动扩展，规则检测逻辑通过 `processor` 下的处理器按规则类型分发执行，结果使用 `JsonTable` 封装。

## Agent 快速规则

- 修改 PHP 文件时保持 `declare(strict_types=1);`。
- 类名使用 PascalCase，属性和方法使用 camelCase，公共 API 添加 PHPDoc。
- 处理器优先继承对应 `Base` 类，并实现 `IDetectInsuranceProcessor`。
- 规则类型映射集中维护在 `src/constant/Processor.php`。
- 测试优先使用 `composer irmi` 或 `composer irmi -- -p <目录> -n <文件名>`。
- 当前代码运行建议使用 PHP `8.1+` 并启用 `bcmath` 扩展。

## 文档目录

- [项目结构](.agent/doc/project-structure.md): 项目概览、目录结构、核心调用流程。
- [代码规范](.agent/doc/coding-standards.md): PHP 严格类型、命名、类型声明、数组和字符串处理规范。
- [核心组件](.agent/doc/core-components.md): `IRMI`、`Driver`、处理器接口、基类和主要结构体。
- [处理器与规则](.agent/doc/processors-and-rules.md): 处理器类型映射、医保项目规则、电子病历规则、测试规则结构。
- [规则选项与错误码](.agent/doc/rule-options-and-errors.md): 通用选项、项目检测选项、病历属性检测和错误码。
- [工具类与测试](.agent/doc/utilities-and-testing.md): `Util` 常用方法、测试命令、测试数据结构和运行环境。
- [开发注意事项](.agent/doc/development-notes.md): 开发原则、常用命令、版本和依赖信息。

## 常用入口

- 管理类: `src/IRMI.php`
- 驱动基类: `src/Driver.php`
- 处理器映射: `src/constant/Processor.php`
- 医保项目处理器: `src/processor/insurance/`
- 电子病历处理器: `src/processor/emr/`
- 数据结构: `src/struct/`
- 测试入口: `tests/IRMI.php`

