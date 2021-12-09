<?php

namespace Joker\Swoole\Libs\Struct;

use Joker\Swoole\Libs\BaseStruct;

final class Socket extends BaseStruct
{
    public $id;

    public $fd;

    public $ip;

    public $pid;
}