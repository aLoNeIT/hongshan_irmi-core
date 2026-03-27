# hongshan_irmi-core 项目约束与架构说明

本文档面向后续参与本仓库开发的 AI / 开发者，目标不是做产品宣传，而是明确当前代码的真实架构、调用链、数据约定、扩展方式和实现边界。

除非明确要做重构，否则后续修改应优先遵守本文档中的现有约定。

## 1. 项目定位

`hongshan_irmi-core` 是一个基于规则驱动的医保智能审核核心库。

核心能力：

- 加载规则集 `IRMIRuleSet`
- 加载病历/就诊记录 `MedicalRecord`
- 根据规则类别与规则类型，把规则路由到对应 `processor`
- 返回统一 `JsonTable` 风格的审核结果数组

当前项目不是典型的 MVC / DDD / ORM 项目，核心组织方式是：

- `IRMI / Driver` 负责调度
- `struct/*` 负责结构化数据
- `processor/*` 负责规则执行
- `constant/*` 负责类别与处理器映射
- `tests/*` 既是测试入口，也是规则 JSON 与病历 JSON 的真实样例来源

## 2. 目录结构

```text
src/
├─ config/                 默认配置
├─ constant/               类别、键名、别名映射
├─ driver/                 驱动实现，当前只有 ShaanXi
├─ interfaces/             处理器接口
├─ processor/
│  ├─ emr/                 病历类规则处理器
│  └─ insurance/           医保项目类规则处理器
├─ struct/                 规则、病历、项目、结果等结构体
├─ Driver.php              驱动基类，核心审核流程在这里
├─ IRMI.php                对外入口，负责实例化 driver
├─ IRMIException.php       统一异常
├─ IRMILog.php             PSR-3 日志包装
└─ Util.php                通用工具方法与公式计算

tests/
├─ IRMI.php                CLI 测试入口
├─ App.php                 批量数据导出示例
└─ data/                   规则样例、病历样例、回归用例
```

## 3. 核心执行链路

标准调用顺序：

1. `IRMI::instance($config)->store('shaanxi')` 获取驱动实例
2. `Driver::load($code, $data)` 加载规则集
3. `(new MedicalRecord())->load($record)` 加载病历
4. `$driver->switch($code)->detectInsurance($medicalRecord, $ruleOption)` 执行审核
5. 返回统一数组结果，结构与 `JsonTable->toArray()` 一致

内部实际执行流程：

1. `IRMI::store()` 根据配置实例化 driver
2. `Driver::load()` 创建/切换 `IRMIRuleSet`
3. `IRMIRuleSet::load()` 把每条规则转成 `IRMIRule`，并建立 `category + itemCode -> ruleCode[]` 索引
4. `MedicalRecord::load()` 把原始 `medical_insurance_set` 转成 `MedicalInsuranceItem` 对象，并生成临时索引 `medical_insurance_item_with_code`
5. `Driver::detectInsurance()` 依次处理：
   - 医保项目类规则 `CATEGORY_INSURANCE`
   - 病历类规则 `CATEGORY_EMR`
6. 对每条命中的规则，根据 `Processor::TYPE_MAP` 找到处理器类
7. 处理器返回 `JsonTable`
8. driver 收集所有失败项，最终返回：
   - 无错误：`state = 0`
   - 有错误：`state = 10`，`data` 为各处理器失败结果数组

注意：

- `Driver::detectInsurance()` 对外返回的是 `array`，不是 `JsonTable` 对象
- 单条规则是否被执行，前提是：
  - `category` 必须可路由
  - `item_code` 必须能在当前病历中命中
  - `type` 必须能在 `Processor::TYPE_MAP` 中找到处理器

## 4. 核心类职责

### 4.1 IRMI

- 统一入口
- 维护 driver 配置和 driver 单例缓存
- 当前默认 driver 为 `shaanxi`

### 4.2 Driver

职责：

- 管理多个规则集实例
- 执行完整审核流程
- 汇总错误码与错误消息

当前 `Driver` 不是空壳，核心业务调度都在这里，新增审核能力时通常不应改 `IRMI`，而应改：

- `constant/Processor.php`
- 对应 `processor/*`
- 必要时补充 `struct/*`

### 4.3 IRMIRuleSet

职责：

- 保存规则集基本信息：`code`、`name`、`dict`
- 把原始规则数组转成 `IRMIRule`
- 建立规则索引
- 按 `category + itemCode` 过滤规则
- 支持白名单 / 黑名单过滤

关键点：

- `dict` 供公式判断与集合展开使用
- `__call()` 会把 `detectInsurance()` 等调用转发给绑定的 `Driver`

### 4.4 MedicalRecord

职责：

- 承载病历主数据
- 在 `load()` 阶段把原始医保项目数组转成对象
- 生成临时索引供 processor 快速读取

关键临时索引：

- `Key::KEY_MEDICAL_INSURANCE_ITEM_WITH_CODE`
- 值结构：`array<string, MedicalInsuranceItem[]>`
- 这是大多数医保处理器的直接数据源

## 5. 数据结构约定

### 5.1 命名转换规则

结构体内部属性使用 camelCase，例如：

- `itemCode`
- `subType`
- `medicalInsuranceSet`

输入 JSON / 数组必须使用 snake_case，例如：

- `item_code`
- `sub_type`
- `medical_insurance_set`

原因：

- `struct\Base::load()` 会把结构体 public 属性转成 snake_case 后再从输入数组取值

因此新增结构体字段时必须同步遵守：

- PHP 属性名使用 camelCase
- 外部输入字段名使用 snake_case

### 5.2 MedicalRecord 结构

常用字段：

- `code`
- `sex`
- `age`
- `age_day`
- `weight`
- `birth_weight`
- `in_branch`
- `out_branch`
- `in_days`
- `visit_type`
- `in_date`
- `out_date`
- `diagnosis`
- `procedure`
- `insurance_type`
- `hospital_code`
- `hospital_type`
- `hospital_level`
- `hospital_business_type`
- `medical_insurance_set`

内部派生字段：

- `principalDiagnosis` = `diagnosis[0] ?? null`
- `majorProcedure` = `procedure[0] ?? null`

`medical_insurance_set` 输入结构：

```json
{
  "1709222400": {
    "120300002b": [
      {
        "id": 1,
        "code": "120300002b",
        "name": "示例项目",
        "group_code": "RX-001",
        "type": "drug",
        "time": 1709251200,
        "num": 1,
        "price": 10,
        "cash": 10,
        "total_cash": 10,
        "days": 1
      }
    ]
  }
}
```

说明：

- 第一层 key 是日期零点时间戳
- 第二层 key 是项目编码
- 第三层是该项目在该日期下的多条明细
- `id` 虽非强制，但强烈建议提供，否则结果中的 `item_ids` 会缺失

### 5.3 IRMIRuleSet 结构

```json
{
  "code": "RULESET-01",
  "name": "规则集名称",
  "dict": {
    "drug": {
      "group1": ["A001", "A002"]
    }
  },
  "rules": []
}
```

### 5.4 IRMIRule 结构

高频字段：

- `code`
- `name`
- `category`
- `type`
- `sub_type`
- `item_code`
- `item_name`
- `item_type`
- `visit_type`
- `detect_type`
- `description`
- `options`

强约束：

- `category` 必须明确填写
- 当前 driver 只会扫描：
  - `1` 医保项目类
  - `2` 病历类
- 如果 `category` 为空，规则通常无法进入正常路由

## 6. 当前已实现的规则类别与处理器映射

定义位置：`src/constant/Processor.php`

### 6.1 类别

- `1` 医保项目类 `CATEGORY_INSURANCE`
- `2` 病历类 `CATEGORY_EMR`
- `3` 患者类 `CATEGORY_PATIENT`，当前未实现
- `4` 医院类 `CATEGORY_HOSPITAL`，当前未实现

### 6.2 type 映射

医保项目类：

- `1` `processor\insurance\DuplicateCharge`
- `2` `processor\insurance\OverStandardCharge`
- `3` `processor\insurance\OverInsuranceCharge`
- `4` `processor\insurance\UnReasonableTreatment`
- `5` `processor\insurance\SplitCharge`

病历类：

- `1` `processor\emr\MedicalRecord`

## 7. 当前代码真实支持的规则能力

以下内容以 `src/processor/*` 代码为准，不以历史 README 或旧样例为准。

### 7.1 DuplicateCharge

处理器：`processor\insurance\DuplicateCharge`

已实现：

- `type = 1`
- `sub_type = 1`
- 本质依赖 `exclude_items` / `include_items` 的通用集合判断

### 7.2 OverStandardCharge

处理器：`processor\insurance\OverStandardCharge`

已实现：

- `type = 2, sub_type = 1`
  - 当前项目数量/金额超限
  - 支持按天或全量检测
  - 支持 `combine_items`
  - 支持超出部分按比例收费校验 `ratio`
- `type = 2, sub_type = 2`
  - 多项目并存时折扣收费校验
  - 支持 `discount_items`
  - 支持 `discount_target`

说明：

- 代码里只处理 `sub_type = 1/2`
- 历史样例里若出现 `sub_type = 3/4`，当前主代码不会处理

### 7.3 OverInsuranceCharge

处理器：`processor\insurance\OverInsuranceCharge`

已实现：

- `type = 3, sub_type = 1`
  - 就诊类型限制 `visit_type`
  - 年龄范围限制 `age_range`
  - 总天数限制 `total_days`
  - 周期限制 `period`
  - 包含/排除项目校验 `include_items` / `exclude_items`
- `type = 3, sub_type = 2`
  - 数量类超限
  - 支持按 `unit_type`
  - 支持 `num.type = 1/2/3/4/5`
  - 支持按组统计 `group_code`

### 7.4 UnReasonableTreatment

处理器：`processor\insurance\UnReasonableTreatment`

已实现：

- `type = 4, sub_type = 1`
  - 包含/排除项目关系校验
  - 可对 `days` 做数量比对
- `type = 4, sub_type = 2`
  - 基于病历属性公式校验 `property`
  - 可叠加科室限制 `include_branch` / `exclude_branch`

### 7.5 SplitCharge

处理器：`processor\insurance\SplitCharge`

已实现：

- `type = 5, sub_type = 1`
  - 检测当前项目与组合项目是否同时存在
  - 支持按天或全量判断 `detect_type`

注意：

- `combine_items` 在这里是必填
- 当前实现里会把当前 `item_code` 和组合项目一起判断

### 7.6 EMR MedicalRecord

处理器：`processor\emr\MedicalRecord`

已实现：

- `category = 2, type = 1, sub_type = 1`
  - 诊断/手术触发的病历属性校验
- `category = 2, type = 1, sub_type = 2`
  - 直接病历属性校验

核心能力：

- 使用 `Util::detectFormula()` 执行 `property` 公式
- 错误项会返回全病历相关 `item_ids`

## 8. options 字段支持范围

### 8.1 通用时间控制

- `time_range: [beginTimestamp|null, endTimestamp|null]`

行为：

- 在 processor 中按“项目所属日期 `item->date`”过滤
- 判断逻辑是：
  - `date >= begin`
  - `date < end`

因此结束时间是左闭右开。

### 8.2 include_items / exclude_items

代码依赖的标准结构：

```json
{
  "time_type": 2,
  "time_offset": [null, null],
  "collection_type": null,
  "collection": {
    "ITEM001": null,
    "ITEM002": {
      "num": 2,
      "combine_items": ["ITEM003"]
    }
  }
}
```

支持的 `time_type`：

- `1` 按天
- `2` 全量
- `3` 时间偏移区间
- `4` 同一处分组 / 处方组（按 `group_code`）
- `10` 同一小时

补充说明：

- `collection_type` 不为空时，会从规则集 `dict` 中展开集合
- `collection` 的 key 才是最终比对项目编码
- 历史样例里出现的 `code_set` 不是当前主代码读取字段，新增规则不要再用

### 8.3 num

`num` 可以是标量，也可以是对象。

标量示例：

```json
{
  "num": 24
}
```

对象示例：

```json
{
  "num": {
    "type": 2,
    "property": "in_days",
    "coefficient": 24
  }
}
```

当前代码支持：

- `type = 1` 直接使用 `value`
- `type = 2` 使用病历属性值，可乘 `coefficient`
- `type = 3` 使用其他项目的累计值
- `type = 4` 组内项目种类数
- `type = 5` 组内项目累计数量

注意：

- `num.type = 2` 时读取的是病历属性，字段名需写 snake_case，例如 `in_days`
- `num.type = 3` 时当前实现依赖其他项目统计，使用前需确保规则和数据结构匹配

### 8.4 property

用于病历属性公式判断，格式：

```json
[
  {
    "name": "sex",
    "operator": "=",
    "value": 2
  }
]
```

`Util::detectFormula()` 当前支持的运算符：

- `=`
- `!=`
- `<`
- `<=`
- `>`
- `>=`
- `in`
- `not in`
- `in dict`
- `not in dict`
- `between`
- `regex`

支持 `condition` 前置条件，例如：

```json
[
  {
    "name": "age",
    "operator": "<=",
    "value": 12,
    "condition": [
      {
        "name": "visit_type",
        "operator": "=",
        "value": 2
      }
    ]
  }
]
```

### 8.5 其他常用 options

- `unit_type`
  - 常见值：`num`、`cash`、`days`
- `detect_type`
  - 常见值：`1` 按天，`2` 全量
- `combine_items`
  - 组合项目编码数组
- `discount_items`
  - 折扣目标项目集合
- `discount_target`
  - `1` 其他项目打折
  - `2` 当前项目自己打折
- `ratio`
  - 折扣比例或超限部分收费比例
- `visit_type`
  - `1` 门诊
  - `2` 住院
- `age_range`
  - `[min|null, max|null]`
- `period`
  - `type` 周期类型
  - `num` 周期长度
  - `sub_num` 周期内允许数量
- `include_branch` / `exclude_branch`
  - 科室编码数组
- `item_type`
  - 主要用于组内项目统计时按项目类型过滤

## 9. 结果格式约定

顶层返回格式：

```json
{
  "state": 0,
  "msg": "success"
}
```

有错误时：

```json
{
  "state": 10,
  "msg": "未通过检测",
  "data": [
    {
      "state": 201,
      "msg": "超标准收费",
      "data": {
        "errors": [
          {
            "msg": "具体错误描述",
            "data": {
              "rule": {
                "category": 1,
                "type": 2,
                "sub_type": 1,
                "code": "RULE-001",
                "name": "规则名称",
                "item_code": "ITEM001",
                "item_name": "项目名称"
              },
              "item_ids": [1, 2, 3]
            }
          }
        ]
      }
    }
  ]
}
```

注意：

- 一个规则处理器返回一个失败块
- 每个失败块里可能有多个 `errors`
- 最终用户真正要消费的通常是 `data[*].data.errors[*]`

## 10. 扩展方式

### 10.1 新增地区/政策驱动

方式：

- 新建 `src/driver/YourDriver.php`
- 继承 `Driver`
- 在 `IRMI` 配置中注册 `stores`

适用场景：

- 不同地区需要不同默认配置
- 需要覆写加载逻辑或审核入口逻辑

### 10.2 新增规则类型

标准做法：

1. 在 `src/processor/...` 下新增处理器
2. 实现 `IDetectInsuranceProcessor`
3. 在 `src/constant/Processor.php` 中补充 `TYPE_MAP`
4. 定义清楚新的 `type / sub_type / options` 约定
5. 增加对应测试样例

不建议：

- 直接在 `Driver::detectInsurance()` 中写大量分支
- 把多个规则类型硬塞进同一个已有处理器而不区分 `sub_type`

### 10.3 新增数据结构

方式：

- 继承 `struct\Base`
- 使用 public 属性
- 输入字段坚持 snake_case
- 若需要临时计算缓存，可参考 `MedicalRecord::$tmpData`

## 11. 后续 AI 修改代码时必须遵守的约束

### 11.1 结构体约束

- 新增结构体字段时，PHP 属性名必须是 camelCase
- 外部 JSON 字段名必须是 snake_case
- 尽量只使用 public 属性让 `Base::load()` 自动装配

### 11.2 规则约束

- 新规则 JSON 必须显式提供 `category`
- `item_code` 必须能在病历命中，否则规则不会执行
- 新增 option 前先确认 processor 是否真的读取该字段

### 11.3 处理器约束

- 返回值必须是 `JsonTable`
- 错误信息统一通过 `getResult()` 返回
- 尽量保留 `rule` 与 `item_ids`，便于追溯

### 11.4 测试约束

- 新能力至少补充一个 `success` 用例和一个 `fail` 用例
- 优先沿用 `tests/data/*` 现有 JSON 组织方式

## 12. 当前实现边界与注意事项

这些内容对后续 AI 很重要，避免误判现状：

- 当前只有 `ShaanXi` driver，且本身没有覆写业务逻辑，核心逻辑都在 `Driver`
- `CATEGORY_PATIENT` 与 `CATEGORY_HOSPITAL` 仅预留，未实现
- `Processor::TYPE_MAP` 决定实际可路由能力，文档和样例不能替代它
- 历史样例中存在旧字段命名，不应直接当作现行规范
- `MedicalRecord::load()` 会重建 `medicalInsuranceSet`，后续处理器默认依赖这个结构和临时索引
- 大部分规则是“命中 item_code 后再判断附加条件”，不是先全局扫描
- `dict` 目前主要用于：
  - `Util::detectFormula()` 的 `in dict / not in dict`
  - `include_items / exclude_items` 的 `collection_type` 展开

## 13. 推荐的开发心智模型

理解本项目最有效的方式是记住下面这条主线：

`规则先按 category + item_code 命中 -> 再按 type 找处理器 -> 再读取 options 做细分判断 -> 返回统一错误块`

后续新增功能时，优先问自己四个问题：

1. 新规则属于哪个 `category`？
2. 新规则应该复用现有 `type/sub_type` 还是新增处理器？
3. 规则命中的起点 `item_code` 是什么？
4. 新增的 `options` 字段是否已有处理器真正消费？

如果这四点不清楚，就不要先写代码。
