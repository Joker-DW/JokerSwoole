<?php
namespace Joker\Swoole\Process;

use Joker\Swoole\Libs\Tools\Response\PingResponse;
use Joker\Swoole\Share\System;
use Swoole\WebSocket\Server;

final class Master
{
    private const TYPE_MESSAGE = 0;

    public function __construct()
    {

    }

    public function dispatchFunc(Server $server, int $fd, int $type, string $data = ''){
        if ($type == self::TYPE_MESSAGE){
            $arr = json_decode($data, true);
            if (is_array($arr) &&
                isset($arr['code']) &&
                is_numeric($arr['code']) &&
                $arr['code'] == PingResponse::code
            ){
                return $fd % System::getHeartbeatWorkerNum();
            }
        }
        return $fd % System::getBusinessWorkerNum() + System::getWorkerNum() - System::getBusinessWorkerNum();
    }
}

