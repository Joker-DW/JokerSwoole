<?php

namespace Joker\Swoole\Share;

class OnFunc
{
    /**
     * @var callable|null function (\Joker\Swoole\Facade\Server $server, int $socketId)
     */
    static public $onOpen;


    /**
     * @var callable|null function (\Joker\Swoole\Facade\Server $server, int $socketId)
     */
    static public $onClose;

    /**
     * @var callable|null function (\Joker\Swoole\Facade\Server $server, int $socketId, array $data)
     */
    static public $onMessage;
}