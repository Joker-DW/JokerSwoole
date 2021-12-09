<?php

namespace Joker\Swoole\Libs\Tools;

class Security
{
    public static function checkSign(string $s): bool
    {
        if (strlen($s) != 74){
            return false;
        }

        $salt = substr($s, 0, 32);
        $timestamp = substr($s, 32, 10);
        $sign = substr($s, 42);

        if ($salt != md5($timestamp)){
            return false;
        }

        $n = (int)(substr($timestamp, -1, 1) ?: 1);
        $signNew = $timestamp. $salt;
        while ($n -- > 0){
            $signNew = md5($signNew);
        }

        if ($sign != $signNew){
            return false;
        }

        return true;
    }
}