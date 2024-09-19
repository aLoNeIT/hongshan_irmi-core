<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

use hongshanhealth\irmi\Driver;
use hongshanhealth\irmi\Util;

/**
 * 结构体基类
 * 
 * @author 王阮强 <wangruanqiang@hongshanhis.com>
 */
abstract class Base
{
    /**
     * 驱动类
     *
     * @var Driver
     */
    protected Driver $driver = null;
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->initialize();
    }
    /**
     * 初始化函数
     *
     * @return void
     */
    protected function initialize(): void {}
    /**
     * 设置关联的驱动类
     *
     * @param Driver $driver 驱动实例对象
     * @return static 返回当前结构体
     */
    public function setDriver(Driver $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * 获取当前对象保存的驱动对象
     *
     * @return Driver 返回驱动对象
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }
    /**
     * 当前对象公共属性值转数组
     *
     * @return array 返回仅包含公共属性的数组
     */
    public function toArray(): array
    {
        return Util::getPublicProps($this);
    }

    /**
     * 加载数据
     *
     * @param array $data 数据
     * @return static 返回当前对象
     */
    public function load(array $data): static
    {
        // 获取当前对象的所有公共属性
        $props = Util::getPublicProps($this);
        // 遍历当前对象的所有属性，如果传递进来的数据中存在，则赋值
        foreach ($props as $key => $value) {
            // data中的key是下划线命名法，而当前对象的属性是驼峰命名法，所以需要转换
            $newKey = Util::snake($key);
            if (isset($data[$newKey])) {
                $this->$key = $data[$newKey];
            }
        }
        return $this;
    }
}
