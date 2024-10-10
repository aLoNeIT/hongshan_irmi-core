# hongshan_irmi-core
红杉健康医保智能审核核心类库，Intelligent review of medical insurance

- 错误信息数据格式

```
{
	"state": 200,
	"msg": "错误具体内容",
	"data": {
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
	}
}
```

- IRMIRuleSet集合json格式
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
- options参数说明  
  - time_range：时间范围，第一个为开始时间（大于），第二个为结束时间（小于等于），如果为null，则不限制时间，示例`[1535731200,null]`
  - unit_type：数量单位，如果为`num`，代表数量，取num属性的值，如果为`cash`，取cash属性的值
  - num：如果直接为数字，则代表本身含义，如果是一个对象，其属性定义如下  
      - type：含义为，1-原始数字，2-病例中的某个属性,3-另一个项目的数量；
      - value: 具体数值
      - property: 如果type为2，则有该属性，属性值为病例中的属性名
      - coefficient：计算系数，如果type为2，且设置了该系数，则会将指定属性的值乘以该系数
      - item_code：如果type为3，则有该属性，属性值为另一个项目的编码

### MedicalRecord 相关数据

- 患者诊疗数据  
  `price`是标准价格，`cash`是实收价格
```
{
    "code": "0000001",
    "sex": 1,
    "age": 20,
    "age_day": null,
    "weight": null,
    "birth_weight": null,
    "in_department": "01",
    "in_days": 2,
    "out_type": null,
    "principal_diagnosis": null,
    "secondary_diagnosis": [],
    "major_procedure": null,
    "secondary_procedure": [],
    "visit_type": 2,
    "in_date": 1722470400,
    "out_date": 1722592800,
    "medical_insurance_set": {
        "1722441600": {
            "120300002b": {
                "time": 1722474000,
                "num": 2,
                "price": 19.00,
                "cash": 19.00,
                "total_cash": 38.00
            }
        },
        "1722528000": {
            "120300002b": {
                "time": 1722564000,
                "num": 1,
                "price": 19.00,
                "cash": 19.00,
                "total_cash": 19.00
            }
        }
    }
}
```

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