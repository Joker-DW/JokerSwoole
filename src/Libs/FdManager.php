<?php


namespace Joker\Swoole\Libs;

use Joker\Swoole\Libs\Struct\Socket;
use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Libs\Tools\Response\WrongResponse;
use Joker\Swoole\Share\System;
use Swoole\Table;
use Swoole\WebSocket\Server;

/**
 * Fd管理器
 * Class FdManager
 * @package App\Libs
 */
class FdManager
{
    private const tableSize = 1000000;

    /**
     * @var Server
     */
    static private $server;

    /**
     * @var Table
     */
    static private $fdMapTable;

    /**
     * @var Table
     */
    static private $socketIdMapTable;

    static public function makeTable(){
        self::$fdMapTable = new Table(self::tableSize);
        self::$fdMapTable->column('socket_id', Table::TYPE_INT);
        self::$fdMapTable->create();

        self::$socketIdMapTable = new Table(self::tableSize);
        self::$socketIdMapTable->column('fd', Table::TYPE_INT);
        self::$socketIdMapTable->create();

    }

    static private function getFdMapTable(): Table
    {
        return self::$fdMapTable;
    }

    static private function getSocketIdMapTable(): Table
    {
        return self::$socketIdMapTable;
    }

    static public function setServer(Server $server)
    {
        self::$server = $server;
    }

    static private function getServer(): Server
    {
        return self::$server;
    }

    static public function register(int $fd): int
    {
        $redis = RedisManager::getObj();
        $socketId = $redis->incr(RedisManager::makeKey(['string', 'fd_socket_id_incr']));

        self::getFdMapTable()->set($fd, ['socket_id' => $socketId]);
        self::getSocketIdMapTable()->set($socketId, ['fd' => $fd]);

        $socket = new Socket();
        $socket->import(['id' => $socketId, 'fd' => $fd, 'ip' => System::getIp(), 'pid' => System::getPid()]);

        $redis->hSet(RedisManager::makeKey(['hash', 'fds']), $socketId, $socket->toJson());

        unset($socket);
        unset($redis);
        return $socketId;
    }

    static public function del(int $fd): bool
    {
        $socketId = self::getFdMapTable()->get($fd, 'socket_id');

        $redis = RedisManager::getObj();
        $redis->hDel(RedisManager::makeKey(['hash', 'fds']), $socketId);

        unset($redis);

        self::getFdMapTable()->del($fd);
        self::getSocketIdMapTable()->del($socketId);

        return true;
    }

    static public function getSocketId(int $fd): int
    {
        return self::getFdMapTable()->get($fd, 'socket_id');
    }

    static public function getInfo(int $socketId): Socket
    {
        $redis = RedisManager::getObj();
        $json = $redis->hGet(RedisManager::makeKey(['hash', 'fds']), $socketId);
        unset($redis);

        $info = json_decode($json, true);
        $socket = new Socket();
        if (empty($info)){
            throw new \Exception('不存在此socketId');
        }

        $socket->import($info);

        return $socket;
    }

    static public function push(int $socketId, string $msg): bool
    {
        $fd = self::getSocketIdMapTable()->get($socketId, 'fd');
        if (is_int($fd)){
            //fd在本机
            return self::pushLocal($fd, $msg);
        }else{
            //fd在其他机器
            $redis = RedisManager::getObj();
            try {
                $socket = self::getInfo($socketId);
            }catch (\Exception $exception){
                return false;
            }

            $publishMes = json_encode(['fd' => $socket->fd, 'msg' => $msg]);
            $messageId = MessageManager::getMessageWorkerIdForPush($socketId);
            $channel = RedisManager::makeKey(['channel', 'message', $socket->ip, $socket->pid, $messageId]);

            unset($socket);

            while (true){
                $publishRes = $redis->publish($channel, $publishMes);
                if ($publishRes < 1){
                    //容灾---当redis宕机重启后，目标Server的消息服务可能还没有重启，需等待重启
                    Log::debug(sprintf('publish消息到Channel[%s]失败，尝试重新发送。', $channel));
                    sleep(1);
                    continue;
                }

                unset($redis);
                return true;
            }
        }
    }

    static public function pushLocal(int $fd, string $msg): bool
    {
        if(self::isClosed($fd)){
           return false;
        }

        return self::getServer()->push($fd, $msg);
    }

    static public function pushWrongMsgAndClose(int $fd, string $msg)
    {
        self::pushLocal($fd, WrongResponse::formatJson($msg));
        self::getServer()->close($fd);
    }

    static private function isClosed(int $fd): bool
    {
        $fdInfo = self::getServer()->getClientInfo($fd);
        if ($fdInfo === false){
            return true;
        }

        if (!is_array($fdInfo)){
            return true;
        }

        if (!isset($fdInfo['close_errno'])){
            return true;
        }

        return $fdInfo['close_errno'] !== 0;
    }

    static public function close(int $socketId): bool
    {
        try {
            $socket = self::getInfo($socketId);
            self::del($socket->fd);
            if (self::isClosed($socket->fd)){
                unset($socket);
                return true;
            }

            $res = self::getServer()->close($socket->fd);
            unset($socket);
            return $res;
        }catch (\Exception $exception){
            return true;
        }
    }
}