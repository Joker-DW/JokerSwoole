<?php

namespace Joker\Swoole\Libs\Tools;

use Joker\Swoole\Share\System;

class Log
{
    static public function debug(string $str){
        if (System::isDebug()){
            echo $str. "\n";
        }
    }

    static public function info(string $str){
        echo '【INFO】 '. $str. "\n";
    }
}