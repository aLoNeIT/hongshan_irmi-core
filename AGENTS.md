# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## 项目概述

红杉健康医保智能审核核心类库（PHP 8.0+），用于检测医保项目中的重复收费、超标准收费、超医保费用和不合理诊疗问题。

## 常用命令

```bash
# 安装依赖
composer install

# 执行默认测试目录（medical_record_jcg）的所有测试
composer irmi

# 执行指定目录下所有测试文件
composer irmi -- -p alone
composer irmi -- -p sky
composer irmi -- -p medical_record_jcg

# 执行指定目录下的单个测试文件（不需要 .json 后缀）
composer irmi -- -p alone -n case1
composer irmi -- -p alone -n BHLZL-DAYS

# 执行所有测试（tests/App.php）
composer test

# 文本相似性测试
composer similary
```

## 架构

采用**驱动模式 + 工厂模式**，分四层：

```
IRMI (单例管理层)
  └── Driver (驱动基类)
        └── driver/ShaanXi (陕西驱动实现)
              └── processor/insurance/* (规则处理器，按 type 动态加载)
```

**核心流程：**

1. `IRMI::instance()->store('shaanxi')` 获取驱动
2. `$driver->load($code, $ruleSetArray)` 加载规则集
3. `(new MedicalRecord())->load($data)` 解析病历
4. `$driver->switch($code)->detectInsurance($medicalRecord)` 执行检测
5. 通过 `JsonTable` 解析结果（state=0 表示通过）

**规则类型 → 处理器映射**（`src/constant/Processor.php`）：

| type | 处理器 | 含义 |
|------|--------|------|
| 1 | DuplicateCharge | 重复收费 |
| 2 | OverStandardCharge | 超标准收费 |
| 3 | OverInsuranceCharge | 超医保费用 |
| 4 | UnReasonableTreatment | 不合理诊疗 |

## 关键文件

| 文件 | 作用 |
|------|------|
| `src/IRMI.php` | 单例入口，管理驱动实例 |
| `src/Driver.php` | 驱动基类，核心 API（load/switch/detectInsurance） |
| `src/processor/insurance/Base.php` | 处理器基类（700+ 行），提取医保项目数据的核心逻辑 |
| `src/struct/MedicalRecord.php` | 病历数据结构 |
| `src/struct/IRMIRule.php` | 规则结构 |
| `src/constant/Processor.php` | type → 处理器类的映射表 |
| `src/config/irmi.php` | 驱动配置（默认驱动为 shaanxi） |
| `tests/IRMI.php` | 命令行测试工具入口 |

## 测试用例格式

测试文件位于 `tests/data/<目录>/`，JSON 格式：

```json
{
  "rule": {
    "code": "规则编码",
    "name": "规则名称",
    "item_code": "项目编码",
    "type": 4,
    "sub_type": 2,
    "options": {}
  },
  "medical_records": {
    "success": [/* 应通过检测的病历数组 */],
    "fail":    [/* 应触发规则的病历数组 */]
  },
  "dict": {}
}
```

成功用例期望返回 `state=0`，失败用例期望返回 `state≠0`。

## IRMIRule options 重要配置项

- `time_range`：时间范围，`[起始时间戳, 结束时间戳]`，null 表示不限
- `visit_type`：就诊类型，1-门诊，2-住院
- `unit_type`：数量单位，`num`（数量）或 `cash`（金额）
- `detect_type`：检测方式，1-按日，2-范围
- `include_items` / `exclude_items`：包含/排除项目配置，`time_type` 为 1-按日、2-全部、3-时间区间、4-同一处方、10-同一小时
- `period`：周期配置，type=1-次、2-日、3-周、4-月、5-年
- `age_range`：年龄范围，`[最小年龄, 最大年龄]`，null 表示不限
- `combine_items`：合并计数的项目编码数组
- `property`：属性验证规则数组，operator 支持 `>`、`<`、`=`、`>=`、`<=`、`!=`、`in`、`not in`、`regex`

## 病历核心字段

- `visit_type`：1-门诊，2-住院
- `medical_insurance_set`：医保项目集合，`{ "时间戳(每日0点)": { "项目编码": [项目数组] } }`
- 每个项目包含：`code`、`name`、`group_code`、`type`、`time`、`num`、`price`、`cash`、`total_cash`、`days`

## 扩展驱动

在 `src/driver/` 中新增驱动类（继承 `Driver`），并在 `src/config/irmi.php` 的 `stores` 中注册即可支持新地区。
