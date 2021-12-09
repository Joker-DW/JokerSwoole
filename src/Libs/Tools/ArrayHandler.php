<?php

namespace Joker\Swoole\Libs\Tools;

class ArrayHandler
{
    /**
     * 是否是一维数组
     */
    static public function isSimple(array $arr): bool
    {
        $accessVType = ['integer', 'float', 'double', 'string', 'NULL', 'boolean'];

        if (empty($arr)){
            return true;
        }

        foreach ($arr as $k => $v){
            $type = gettype($v);
            if (!in_array($type, $accessVType)){
                return false;
            }
        }

        return true;
    }
}