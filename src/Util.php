<?php

declare(strict_types=1);

namespace hongshanhealth\irmi;

/**
 * 工具类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
class Util
{
    /**
     * 获取JsonTable格式数组中的state值
     *
     * @param array $jtable JsonTable格式的数组
     * @return integer 返回state值
     */
    public static function getJState(array $jtable): int
    {
        return $jtable['state'];
    }
    /**
     * 获取JsonTable格式数组中的msg值
     *
     * @param array $jtable JsonTable格式的数组
     * @return string 返回msg值
     */
    public static function getJMsg(array $jtable): string
    {
        return $jtable['msg'];
    }
    /**
     * 获取JsonTable格式数组中的data值
     *
     * @param array $jtable JsonTable格式的数组
     * @return mixed 返回data值
     */
    public static function getJData(array $jtable): mixed
    {
        return $jtable['data'] ?? null;
    }

    /**
     * 判断是否成功
     *
     * @param array $jtable JsonTable格式的数组
     * @return boolean 是否成功
     */
    public static function isSuccess(array $jtable): bool
    {
        return isset($jtable['state']) && 0 === $jtable['state'];
    }
    /**
     * 获取一个JsonTable格式的数组
     *
     * @param integer $state 状态码
     * @param mixed $msg 简要消息
     * @param mixed $data 扩展数据
     * @return array 返回一个JsonTable格式的数组
     */
    public static function jecho(int $state, mixed $msg, mixed $data = null): array
    {
        $result = [
            ...[
                'state' => $state,
                'msg' => $msg
            ],
            ...(!\is_null($data) ? [
                'data' => $data
            ] : [])
        ];
        return $result;
    }
    /**
     * 获取一个成功的JsonTable格式的数组
     *
     * @param mixed $msg 简要消息
     * @param mixed $data 扩展数据
     * @return array 返回一个JsonTable格式的数组
     */
    public static function jsuccess(mixed $msg = 'success', mixed $data = null): array
    {
        return self::jecho(0, $msg ?: 'success', $data);
    }
    /**
     * 获取一个失败的JsonTable格式的数组
     *
     * @param integer $state 状态码
     * @param mixed $msg 简要消息
     * @param mixed $data 扩展数据
     * @return array 返回一个JsonTable格式的数组
     */
    public static function jerror(int $state = 1, mixed $msg = 'failed', mixed $data = null): array
    {
        return self::jecho($state, $msg, $data);
    }
    /**
     * 获取一个异常的JsonTable格式的数组
     *
     * @param mixed $msg 简要消息
     * @param mixed $data 扩展数据
     * @param integer $state 错误码
     * @return array 返回一个JsonTable格式的数组
     */
    public static function jexception(mixed $msg = 'exception', mixed $data = null, int $state = 1): array
    {
        return self::jecho($state, $msg, $data);
    }
    /**
     * 获取一个成功的且带有data节点的JsonTable格式的数组
     *
     * @param mixed $data 扩展数据
     * @param mixed $msg 简要消息
     * @return array 返回一个JsonTable格式的数组
     */
    public static function jdata(mixed $data, mixed $msg = 'success'): array
    {
        return self::jecho(0, $msg, $data);
    }

    /**
     * 驼峰转下划线
     *
     * @param  string $value 待转换的驼峰字符串
     * @param  string $delimiter 分隔符
     * @return string 转换后的下划线字符串
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        static $cache = [];
        $key = $value;

        if (isset($cache[$key][$delimiter])) {
            return $cache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = self::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return $cache[$key][$delimiter] = $value;
    }

    /**
     * 下划线转驼峰(首字母小写)
     *
     * @param  string $value 待转换的下划线字符串
     * @return string 转换后的驼峰字符串
     */
    public static function camel(string $value): string
    {
        static $cache = [];
        if (isset($cache[$value])) {
            return $cache[$value];
        }

        return $cache[$value] = lcfirst(self::studly($value));
    }

    /**
     * 下划线转驼峰(首字母大写)
     *
     * @param  string $value 待转换的下划线字符串
     * @return string 转换后的驼峰字符串
     */
    public static function studly(string $value): string
    {
        static $cache = [];
        $key = $value;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return $cache[$key] = str_replace(' ', '', $value);
    }

    /**
     * 字符串转小写
     *
     * @param  string $value 待转换的字符串
     * @return string 转换后的小写字符串
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * 字符串转大写
     *
     * @param  string $value 待转换的字符串
     * @return string 转换后的大写字符串
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * 获取当前类的公共属性
     *
     * @param object $object 待处理的对象
     * @param boolean $origined 是否返回属性原始值，默认true，为false返回ReflectionProperty对象，且snaked和ignoreNull不生效
     * @param boolean $snaked 是否转换为下划线风格，默认false
     * @param boolean $ignoreNull 是否忽略null值，默认false
     * @return array 包含属性名和属性值的kv数组
     */
    public static function getPublicProps(object $object, bool $origined = true, bool $snaked = false, bool $ignoreNull = false): array
    {
        $props = [];
        $reflection = new \ReflectionObject($object);
        foreach ($reflection->getProperties() as $key => $value) {
            if ($value->isPublic()) {
                // 只有public类型的才需要记录
                if ($origined) {
                    $v = $value->getValue($object);
                    if ($ignoreNull && \is_null($v)) {
                        continue;
                    }
                } else {
                    $v = $value;
                }
                $k = $snaked ? self::snake($value->getName()) : $value->getName();
                $props[$k] = $v;
            }
        }
        return $props;
    }
    /**
     * 根据公式做匹配检测
     *
     * @param array|object $props 属性值，如果是oebject类型，则会自动获取其公共属性
     * @param array<string,mixed>[] $formulas 公式数组，格式为：[['name'=>属性名,'operator'=>操作符,'value'=>值]]
     * @param array<string,array<string,mixed>> $dict 字典数组，格式为：['字典类型'=>[ '类别编码'=>['1','2'] ]]
     * @return array 返回不匹配的公式数组
     */
    public static function detectFormula(array|object $props, array $formulas, array $dict = []): array
    {
        $errors = [];
        // 如果是对象，则获取对象内的公共属性原始值，且转换为下划线风格索引数组
        if (\is_object($props)) {
            $props = self::getPublicProps($props, true, true);
        }
        // 遍历每个公式
        foreach ($formulas as $formula) {
            // 解构参数
            [
                'name' => $name,
                'operator' => $operator,
                'value' => $value
            ] = $formula;
            $propertyValue = $props[$name] ?? null;
            if (\is_null($propertyValue)) {
                // 当前属性不存在，或值为null，则返回true
                continue;
            }
            $condition = $formula['condition'] ?? null;
            if (!\is_null($condition)) {
                // 存在条件，需要先判断条件是否符合
                $result = self::detectFormula($props, $condition, $dict);
                if (!empty($result)) {
                    // 前置条件不符合，无需比对
                    continue;
                }
            }
            // 开始对比结果
            $result = false;
            switch ($operator) {
                case '=':
                    $result = $propertyValue == $value;
                    break;
                case '!=':
                    $result = $propertyValue != $value;
                    break;
                case '<':
                    $result = $propertyValue < $value;
                    break;
                case '<=':
                    $result = $propertyValue <= $value;
                    break;
                case '>':
                    $result = $propertyValue > $value;
                    break;
                case '>=':
                    $result = $propertyValue >= $value;
                    break;
                case 'in':
                case 'in dict':
                    if ('in dict' == $operator) {
                        // 此时value值为字符串，格式 type.subtype
                        $types = \explode('.', $value);
                        $type = $types[0] ?? '';
                        $subtype = $types[1] ?? '';
                        $value = $dict[$type][$subtype] ?? [];
                    } else {
                        // 处理规则中的数据，转为数组
                        $value = \is_array($value) ? $value : \explode(',', (string)$value);
                    }
                    // 如果病例中的属性是数组类型，则使用交集计算，否则使用in计算
                    $result = \is_array($propertyValue)
                        ? !empty(\array_intersect($propertyValue, $value))
                        : \in_array($propertyValue, $value);
                    break;
                case 'not in':
                case 'not in dict':
                    if ('not in dict' == $operator) {
                        // 此时value值为字符串，格式 type.subtype
                        $types = \explode('.', $value);
                        $type = $types[0] ?? '';
                        $subtype = $types[1] ?? '';
                        $value = $dict[$type][$subtype] ?? [];
                    } else {
                        // 处理规则中的数据，转为数组
                        $value = \is_array($value) ? $value : \explode(',', (string)$value);
                    }
                    // 如果病例中的属性是数组类型，则使用交集计算，否则使用in计算
                    $result = \is_array($propertyValue)
                        ? empty(\array_intersect($propertyValue, $value))
                        : !\in_array($propertyValue, $value);
                    break;
                case 'between':
                    $value = \is_array($value) ? $value : \explode(',', (string)$value);
                    $result = $propertyValue >= $value[0] && $propertyValue <= $value[1];
                    break;
                case 'regex':
                    $propertyValue = \is_array($propertyValue) ? $propertyValue : [$propertyValue];
                    $elements = array_filter($propertyValue, function ($item) use ($value) {
                        return preg_match($value, $item);
                    });
                    $result = !empty($elements);
                    break;
                default:
                    throw new IRMIException("不支持的运算符[{$operator}]");
                    break;
            }
            if (!$result) {
                // 检测出问题，记录下来
                $errors[] = $formula;
            }
        }
        return $errors;
    }

    /**
     *获取一个类里所有用到的trait，包括父类的
     *
     * @param mixed $class 类名
     * @return array
     */
    public static function classUsesRecursive(mixed $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        $classes = [
            ...[$class => $class],
            ...class_parents($class)
        ];
        foreach ($classes as $class) {
            $results += static::traitUsesRecursive($class);
        }

        return array_unique($results);
    }

    /**
     * 获取一个trait里所有引用到的trait
     *
     * @param string $trait Trait
     * @return array
     */
    public static function traitUsesRecursive(string $trait): array
    {
        $traits = class_uses($trait);
        foreach ($traits as $trait) {
            $traits += static::traitUsesRecursive($trait);
        }

        return $traits;
    }

    /**
     * 浏览器友好的变量输出
     * @param mixed $vars 要输出的变量
     * @return void
     */
    public static function dd(...$vars): void
    {
        var_dump(...$vars);
        exit(1);
    }

    /**
     * 浏览器友好的变量输出
     * @param mixed $vars 要输出的变量
     * @return void
     */
    public static function dump(...$vars): void
    {
        ob_start();
        var_dump(...$vars);
        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (PHP_SAPI == 'cli') {
            $output = PHP_EOL . $output . PHP_EOL;
        } else {
            $output = '<pre>' . $output . '</pre>';
        }

        echo $output;
    }

    /**
     * 金额数字小数点处理
     *
     * @param string $value 数字
     * @return float 处理后的数字
     */
    public static function amountFormat(string $value): float
    {
        // 切分数字，整数部分和小数部分
        $arr = \explode('.', $value);
        // 小数部分右侧去掉0
        $decimal = \rtrim($arr[1] ?? '', '0');
        $cash = $arr[0] . ('' != $decimal ? ".{$decimal}"  : '');
        return (float)$cash;
    }
}
