# hongshan_irmi-core
红杉健康医保智能审核核心类库，Intelligent review of medical insurance

## 智能审核规则结构，IRMIRule

- 规则结构参数介绍  

    | 参数名 | 是否必选  | 类型    | 可选值 | 说明 |
    | :----: | :------: | :--:   | :----: | :--: |
    | code   | 是       | string |        | 规则编码 |
    | name   | 是       | string |        | 规则名称 |
    | item_code | 是    | string |        | 项目编码 |
    | item_name | 是    | string |        | 项目名称 |
    | category  | 是    | int    | 1-患者医疗项目；2-电子病历；3-患者档案信息；4-医院信息  | 规则类别 |
    | type   | 是    | int    |  | 规则类型，对应计算器类型取值 |
    | sub_type   | 是 | int |  | 规则子类型，对应计算器内子类型取值 |
    | options | 否    | object |        | 规则具体的配置项 |

  #### options参数说明  
    - visit_type：就诊类型，1-门诊，2-住院；
    - time_range：时间范围，第一个为开始时间（大于等于），第二个为结束时间（小于），如果为null，则不限制时间，示例`[1535731200,null]`
    - unit_type：数量单位，如果为`num`，代表数量，取num属性的值，如果为`cash`，取cash属性的值
    - num：如果直接为数字，则代表本身含义，如果是一个对象，其属性定义如下  
        - type：含义为，1-原始数字，2-病例中的某个属性,3-另一个项目的数量；
        - value: 具体数值
        - property: 如果type为2，则有该属性，属性值为病例中的属性名
        - coefficient：计算系数，如果type为2，且设置了该系数，则会将指定属性的值乘以该系数
        - item_code：如果type为3，则有该属性，属性值为另一个项目的编码
    - detect_type：检测方式，1-按日，2-范围；
    - combine_items：合并计数的项目，数组，每个元素是项目编码，用于将指定编码的数据一同累计数量；
    - exclude_items：排除项目配置
        - time_type：时间类型，1-按日，2-全部
        - collection：排除项目明细集合，是一个对象，每个key都是排除的项目编码，如果为null则代表没有更具体配置
            - combine_items：需要联合其他的项目才不触发
    - discount_target：打折目标，1-其他项目打折，2-自己打折；
    - ratio：折扣比例，自身折扣比例，如果存在num配置，说明要超过指定数量部分才打折；
    - discount_items：打折的其他项目，key是目标项目编码，value是对象
        - ratio：折扣比例
    - period：周期配置项
        - type：周期类型，1-次、2-日、3-周、4-月、5-年
        - num：周期数量，如1次、5日、十周；
        - sub_num：周期内的子数量，比如周期是日，子数量是2
    - age_range：年龄范围，数组，第一个为开始年龄（大于），第二个为结束年龄（小于），如果为null，则不限制年龄，示例`[18,null]`
    - date_interval：日期间隔配置
        - num：间隔数量，如5日、三月
        - type：间隔时间类型，1-日，2-月，3-年
- 智能审核规则计算器
    - insurance：医保相关
        - type=1，DuplicateCharge，重复收费
        - type=2，OverStandardCharge，超标准收费
            - sub_type=1，当前项目计费量超过指定量
            - sub_type=2，检测多项目同时存在的折扣费用
        - type=3，OverInsuranceCharge，超医保费用
        - type=4，UnReasonableTreatment，不合理诊疗
            - sub_type=1，检测医保项目同时收费或未同时收费
            - sub_type=2，属性不符合要求
            
- 规则示例数据,IRMIRuleSet集合json格式
    ```
    {
        "code": "01",
        "name": "规则集",
        "rules": [
            {
                "code": "01-01",
                "name": "重复收费",
                "item_code": "120300001b",
                "item_name": "持续吸氧",
                "type": 1,
                "options": {
                    "exclude_items": {
                        "002": {
                            "time_type": 1
                        },
                        "003/呼吸机辅助呼吸": {
                            "num": 1,
                            "time_type": 1
                        }
                    },
                    "time_range": [
                        969356516,
                        null
                    ],
                    "pathology_check": [
                        "004",
                        "005"
                    ]
                }
            },
            {
                "code": "02-01",
                "name": "超标准收费[指定项目数量超过住院天数]",
                "item_code": "110200005",
                "item_name": "住院诊查费",
                "type": 2,
                "sub_type": 1,
                "options": {
                    "exclude_branch": [
                        "05",
                        "05.01",
                        "05.02",
                        "05.03",
                        "05.04",
                        "05.05",
                        "05.06",
                        "06",
                        "06.01",
                        "06.02",
                        "06.03",
                        "06.04",
                        "06.05",
                        "06.06"
                    ],
                    "coefficient": 1,
                    "time_range": [
                        null,
                        1609430400
                    ]
                }
            },
            {
                "code": "02-02",
                "name": "超标准收费[指定项目当日收费超过X（元、数量）]",
                "item_code": "330100008",
                "item_name": "术后镇痛",
                "type": 2,
                "sub_type": 2,
                "options": {
                    "unit": "price",
                    "num": 5,
                    "time_range": [
                        1535731200,
                        null
                    ]
                }
            },
            {
                "code": "02-03",
                "name": "超标准收费[与指定项目当日收费超过X（元、数量）]",
                "item_code": "330100008",
                "item_name": "术后镇痛",
                "type": 2,
                "sub_type": 3,
                "options": {
                    "unit": "price",
                    "num": 5,
                    "time_range": [
                        1535731200,
                        null
                    ]
                }
            }
        ]
    }
    ```  

## 病历结构，MedicalRecord 

- 病历结构参数介绍  

    | 参数名 | 是否必选  | 类型    | 可选值 | 说明 |
    | :----: | :------: | :--:   | :------------: | :--: |
    | code   | 是       | string |        | 病历编码 |
    | sex   | 是       | integer | 1-男；2-女 | 性别 |
    | age | 是    | integer |        | 年龄 |
    | age_day | 否    | integer |        | 不足一岁时的年龄天数 |
    | weight  | 否    | integer |   | 体重，单位g |
    | birth_weight   | 否    | integer    |  | 出生体重，单位g |
    | in_branch | 是    | string |        | 入院/门诊科室编码 |
    | out_branch | [是]    | string |        | 出院科室编码，住院时必填 |
    | visit_type   | 是    | integer | 1-门诊；2-住院 | 就诊类型 |
    | in_date   | 是    | integer |  | 入院/门诊日期 |
    | out_date   | [是]    | integer |  | 出院日期 |
    | hospital_code | 否    | integer |  | 医院编码 |
    | hospital_type | 否    | string |  | 医院类型，如综合、精神、牙科，等待查询标准编码 |
    | hospital_level | 否    | integer |  | 医院级别 |
    | hospital_bussiness_type | 否    | integer | 1-公立；2-民营 | 医院经营类型 |
    | medical_insurance_set | 是 | object |        | 医保项目集合，kv结构，key是日期，value是该日期下的项目数组 |

    - medical_insurance_set参数中每个项目结构说明
        - key：时间戳，每天0点，代表日期
        - value：该日期下所有项目数据，kv结构如下
            - key：项目编码
            - value：该项目每次开单的数据，数组，每个元素结构如下
                - code：项目编码
                - name：项目名称
                - time：项目发生时间
                - num：项目数量
                - price：项目标准价格
                - cash：项目实收价格
                - total_cash：项目总实收价格

- 病历示例数据,MedicalRecort的json格式
    ```
    {
        "code": "0000001",
        "sex": 1,
        "age": 20,
        "age_day": null,
        "weight": null,
        "birth_weight": null,
        "in_branch": "01",
        "out_branch": "02",
        "in_days": 2,
        "visit_type": 2,
        "in_date": 1722470400,
        "out_date": 1722592800,
        "hospital_code": "79314258",
        "hospital_type": "精神病",
        "hospital_level": "医院级别",
        "hospital_bussiness_type": 1,
        "medical_insurance_set": {
            "1722441600": {
                "120300002b": [
                    {
                        "code": "120300002b",
                        "name": "XX费用",
                        "time": 1722474000,
                        "num": 2,
                        "price": 19.00,
                        "cash": 19.00,
                        "total_cash": 38.00
                    }
                ]
            },
            "1722528000": {
                "120300002b": [
                    {
                        "code": "120300002b",
                        "name": "XX费用",
                        "time": 1722564000,
                        "num": 1,
                        "price": 19.00,
                        "cash": 19.00,
                        "total_cash": 19.00
                    }
                ]
            }
        }
    }
    ```

## 临时数据结构 
- 键名为`medical_insurance_item_with_code`的临时数据格式
    ```
    {
        "120300002b": [{
            "date": 1726243200,
            "time": 1726275600,
            "num": 2,
            "price": 19.00,
            "cash": 19.00,
            "total_cash": 38.00
        }]
    }
    ```

### 错误信息数据格式

```
{
	"state": 200,
	"msg": "错误具体内容",
	"data": [{
		"rule": {
			"code": "01-02",
			"name": "重复收费",
			"item_code": "对应项目编码",
			"item_name": "对应项目名称"
		},
		"item": {
			"name": "对比的项目名称",
			"code": "对比的项目编码"
		}
	}]
}
```