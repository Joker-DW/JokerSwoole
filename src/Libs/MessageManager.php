<?php

namespace Joker\Swoole\Libs;

use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Share\System;
use Swoole\Table;

/**
 * 消息服务管理器
 */
class MessageManager
{
    private const tableSize = 1000;

    /**
     * @var Table
     */
    static private $messageWorkerMapTable;

    static public function makeTable(){
        self::$messageWorkerMapTable = new Table(self::tableSize);
        self::$messageWorkerMapTable->column('message_worker_id', Table::TYPE_INT);
        self::$messageWorkerMapTable->create();
    }

    static private function getMessageWorkerMapTable(): Table
    {
        return self::$messageWorkerMapTable;
    }

    static public function start(int $workerId){
        $messageWorkerId = self::register($workerId);
        Log::info(sprintf('Worker[%d]，MessageWorkerId[%d]通信服务开始启动。', $workerId, $messageWorkerId));
        $redis = RedisManager::getObj();
        $channel = RedisManager::makeKey(['channel', 'message', System::getIp(), System::getPid(), $messageWorkerId]);
        Log::info(sprintf('Worker[%d]通信服务启动成功！', $workerId));
        $redis->subscribe([$channel], ['Joker\Swoole\Libs\MessageManager', 'subscribe']);
    }

    static public function subscribe(\Redis $redis, string $channel, string $msg){
        $msgArr = json_decode($msg, true);
        if (empty($msgArr)){
            return;
        }
        FdManager::pushLocal($msgArr['fd'], $msgArr['msg']);
    }

    static public function initRedis(){
        RedisManager::getObj()->del(RedisManager::makeKey(['hash', 'messageWorker', System::getIp(), System::getPid()]));
    }

    /**
     * @return int $messageWorkerId 消息服务id
     */
    static private function register(int $workerId): int
    {
        if(self::getMessageWorkerMapTable()->exist($workerId)){
            return self::getMessageWorkerMapTable()->get($workerId, 'message_worker_id');
        }

        $hashTable = RedisManager::makeKey(['hash', 'messageWorker', System::getIp(), System::getPid()]);
        $redis = RedisManager::getObj();
        $messageWorkerId = $redis->hIncrBy($hashTable, 'messageWorkerTotal', 1);
        self::getMessageWorkerMapTable()->set($workerId, ['message_worker_id' => $messageWorkerId]);

        unset($redis);
        return $messageWorkerId;
    }

    /**
     * @param int $socketId socketId
     * @return int $messageWorkerId 消息服务id
     */
    static public function getMessageWorkerIdForPush(int $socketId): int
    {
        $socket = FdManager::getInfo($socketId);

        $hashTable = RedisManager::makeKey(['hash', 'messageWorker', $socket->ip, $socket->pid]);
        $redis = RedisManager::getObj();
        $messageWorkerTotal = $redis->hGet($hashTable, 'messageWorkerTotal');
        if ($messageWorkerTotal === false || !is_numeric($messageWorkerTotal) || $messageWorkerTotal < 1){
            throw new \Exception('没有message服务订阅');
        }

        $messageId = $redis->hIncrBy($hashTable, 'messageIdIncr', 1);
        $messageWorkerId = $messageId % $messageWorkerTotal + 1;

        unset($socket);
        unset($redis);
        return $messageWorkerId;
    }
}