<?php

namespace Joker\Swoole\Facade;

use Joker\Swoole\Hook\RedisHook;
use Joker\Swoole\Libs\MysqlManager;
use Joker\Swoole\Libs\RedisManager;

class Facade
{
    private $eventType;

    public function __construct(int $eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * @return \Redis
     */
    public function redis(): RedisHook
    {
        return RedisManager::getObj();
    }

    public function mysql(): \Illuminate\Database\Connection
    {
        return MysqlManager::getObj($this->eventType);
    }
}