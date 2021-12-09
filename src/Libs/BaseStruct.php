<?php

namespace Joker\Swoole\Libs;

use Joker\Swoole\Libs\Tools\ArrayHandler;

abstract class BaseStruct
{
    public function toArray(): array
    {
        try{
            $data = [];

            $reflection = new \ReflectionClass(get_called_class());

            foreach ($reflection->getProperties() as $v) {
                $propertyName = $v->name;
                $data[$propertyName] = $this->$propertyName;
            }

        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        return $data;
    }

    public function toJson(): string
    {
        $arr = $this->toArray();

        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    public function import(array $info): bool
    {
        if (empty($info)){
            throw new \Exception('import数组为空.');
        }

        if(!ArrayHandler::isSimple($info)){
            throw new \Exception('import只支持一维数组.');
        }

        foreach ($info as $k =>$v){
            if(!property_exists(get_called_class(), $k)){
                throw new \Exception('不存在字段'. $k);
            }
            $this->$k = $v;
        }

        return true;
    }
}