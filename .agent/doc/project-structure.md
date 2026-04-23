# 项目结构

## 项目概述

**项目名称**: `hongshan_irmi-core`

**描述**: 红杉健康医保智能审核核心类库（Intelligent review of medical insurance）

**PHP 版本**: `>= 8.0`

**许可证**: MulanPSL-2.0

## 目录结构

```text
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

## 架构概览

项目采用驱动模式和处理器映射执行医保规则检测：

```text
IRMI
└── Driver
    └── driver/ShaanXi
        └── processor/*
```

核心流程：

1. 使用 `IRMI::instance($config)` 获取管理类实例。
2. 使用 `$irmi->store('shaanxi')` 获取地区驱动。
3. 使用 `$driver->load($code, $ruleSetArray)` 加载规则集。
4. 使用 `(new MedicalRecord())->load($data)` 载入病历。
5. 使用 `$driver->switch($code)->detectInsurance($medicalRecord)` 执行检测。
6. 使用 `JsonTable` 解析返回结果。

