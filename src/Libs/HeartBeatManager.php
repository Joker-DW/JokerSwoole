<?php

namespace Joker\Swoole\Libs;

use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Libs\Tools\Response\PingResponse;
use Joker\Swoole\Share\System;
use Swoole\Coroutine;
use Swoole\Table;

class HeartBeatManager
{
    private const tableSize = 1000000;

    /**
     * @var Table
     */
    static private $settingTable;

    /**
     * @var Table
     */
    static private $fdTable;

    /**
     * @var Table[]
     */
    static private $workerFdTables;

    /**
     * @var Table
     */
    static private $pingTable;

    /**
     * @var int ping间隔时间/秒
     */
    static private $pingSecLimit = 10;

    public static function makeTable()
    {
        self::$settingTable= new Table(self::tableSize);
        self::$settingTable->column('v', Table::TYPE_INT);
        self::$settingTable->create();
        self::$settingTable->set('fdIncrNo', ['v' => 0]);
        self::$settingTable->set('executedFdNo', ['v' => 0]);
        self::$settingTable->set('fdTableIndexIncr', ['v' => 0]);

        self::$fdTable= new Table(self::tableSize);
        self::$fdTable->column('fd', Table::TYPE_INT);
        self::$fdTable->create();

        for ($i = 1; $i <= System::getHeartbeatWorkerNum(); $i ++){
            self::$workerFdTables[$i] = new Table(self::tableSize);
            self::$workerFdTables[$i]->column('fd', Table::TYPE_INT);
            self::$workerFdTables[$i]->create();
            self::$workerFdTables[$i]->set('indexIncr', ['fd' => 0]);
        }

        self::$pingTable= new Table(self::tableSize);
        self::$pingTable->column('last_ping_id', Table::TYPE_STRING, 32);
        self::$pingTable->create();
    }

    private static function getFdTable(): Table
    {
        return self::$fdTable;
    }

    private static function getWorkerFdTable(int $workerId): Table
    {
        return self::$workerFdTables[self::_getFdTableIndex($workerId)];
    }

    private static function _getFdTableIndex(int $workerId): int
    {
        if (self::getSettingTable()->exist('fdTableIndex'. $workerId)){
            return self::getSettingTable()->get('fdTableIndex'. $workerId, 'v');
        }

        $fdTableIndex = self::getSettingTable()->incr('fdTableIndexIncr', 'v');
        self::getSettingTable()->set('fdTableIndex'. $workerId, ['v' => $fdTableIndex]);

        return $fdTableIndex;
    }

    private static function getPingTable(): Table
    {
        return self::$pingTable;
    }

    private static function getSettingTable(): Table
    {
        return self::$settingTable;
    }

    private static function createFdIncrNo(): int
    {
        return self::getSettingTable()->incr('fdIncrNo', 'v');
    }

    public static function start(int $workerId)
    {
        Log::info(sprintf('Worker[%d]心跳服务：已启动。', $workerId));
        $count = self::getWorkerFdTable($workerId)->count();
        if ($count > 1){
            Log::info(sprintf('Worker[%d]心跳服务：发现异常关闭的线程%d个。', $workerId, $count - 1));
            $success = 0;
            $indexMax = self::getWorkerFdTable($workerId)->get('indexIncr', 'fd');
            for ($i = 1; $i <= $indexMax; $i ++){
                if (self::getWorkerFdTable($workerId)->exist($i)){
                    $fd = self::getWorkerFdTable($workerId)->get($i, 'fd');
                    self::createPingCoroutine($workerId, $fd, $i);
                    $success ++;
                }
            }
            Log::info(sprintf('Worker[%d]心跳服务：已恢复异常关闭的线程%d个。', $workerId, $success));
        }

        go(function () use ($workerId){
            while (true){
                if(self::getSettingTable()->get('fdIncrNo', 'v') <= self::getSettingTable()->get('executedFdNo', 'v')){
                    Coroutine::sleep(1);
                    continue;
                }

                for ($i = self::getSettingTable()->get('executedFdNo', 'v') + 1; $i <= self::getSettingTable()->get('fdIncrNo', 'v'); $i ++){
                    $fd = self::getFdTable()->get($i, 'fd');
                    if (!self::getFdTable()->del($i)){
                        continue;
                    }

                    $indexIncr = self::getWorkerFdTable($workerId)->incr('indexIncr', 'fd');
                    self::getWorkerFdTable($workerId)->set($indexIncr, ['fd' => $fd]);
                    self::createPingCoroutine($workerId, $fd, $indexIncr);
                    self::getSettingTable()->set('executedFdNo', ['v' => $i]);
                }
            }
        });
    }

    static private function makePingId(int $fd): string
    {
        return md5($fd. '-'. time());
    }

    static private function createPingCoroutine(int $workerId, int $fd, int $indexIncr)
    {
        go(function () use ($workerId, $fd, $indexIncr){
            while (true){
                if (self::getPingTable()->exist($fd)){
                    //no receive pong
                    FdManager::pushWrongMsgAndClose($fd, '未及时回复心跳消息，连接已断开。');
                    self::getPingTable()->del($fd);
                    self::getWorkerFdTable($workerId)->del($indexIncr);
                    Log::debug(sprintf('Fd[%d] can not reply pong.', $fd));
                    break;
                }
                $pingId = self::makePingId($fd);
                self::getPingTable()->set($fd, ['last_ping_id' => $pingId]);
                if (!FdManager::pushLocal($fd, PingResponse::formatJson(['id' => $pingId]))){
                    Log::debug(sprintf('Ping fd[%d] fail.', $fd));
                    self::getPingTable()->del($fd);
                    self::getWorkerFdTable($workerId)->del($indexIncr);
                    break;
                }
                Coroutine::sleep(self::$pingSecLimit);
            }
        });
    }

    public static function ping(int $fd): bool
    {
        $fdIncrNo = self::createFdIncrNo();
        return self::getFdTable()->set($fdIncrNo, ['fd' => $fd]);
    }

    public static function receivePong(int $fd, array $data)
    {
        if (empty($data['id'])){
            FdManager::pushWrongMsgAndClose($fd, '心跳信息结构错误：缺少id');
            return;
        }

        $lastPingId = self::getPingTable()->get($fd, 'last_ping_id');
        if ($lastPingId !== $data['id']){
            return;
        }

        self::getPingTable()->del($fd);
    }
}