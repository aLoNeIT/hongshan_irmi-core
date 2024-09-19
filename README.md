# hongshan_irmi-core
红杉健康医保智能审核核心类库，Intelligent review of medical insurance


- IRMIRuleSet集合json格式
```
{
	"code": "01",
	"name": "规则集",
	"items": [{
		"code": "01",
		"name": "规则1",
		"item_code": "001",
		"item_name": "项目1",
		"type": 1,
		"options": {
			"exclude_items": {
				"002": {
					"num": 1,
					"time_type": 1
				},
				"003": {
					"num": 1,
					"time_type": 1
				}
			},
			"time_range": [null, null],
			"pathology_check": ["004", "005"]
		}
	}]
}
```

### MedicalRecord 中的临时数据

- 键名为`medical_insurance_item_with_code`的临时数据格式
```
{
	"120300002b": [{
		"date": 1726243200,
		"num": 2,
		"price": 19.00,
		"total_cash": 38.00
	}]
}
```