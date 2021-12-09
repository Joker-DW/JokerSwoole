<?php
namespace Joker\Swoole\Process;

use Joker\Swoole\Exception\JokerSwooleFatalException;
use Joker\Swoole\Libs\FdManager;
use Joker\Swoole\Libs\HeartBeatManager;
use Joker\Swoole\Libs\MessageManager;
use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Libs\Tools\Response\PingResponse;
use Joker\Swoole\Libs\Tools\Response\SuccessResponse;
use Joker\Swoole\Libs\Tools\Security;
use Joker\Swoole\Share\Event;
use Joker\Swoole\Share\OnFunc;
use Joker\Swoole\Share\System;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

final class Worker
{
    private $workerId;

    /**
     * @var \Joker\Swoole\Facade\Server
     */
    public $onOpenServer;

    /**
     * @var \Joker\Swoole\Facade\Server
     */
    public $onMessageServer;

    /**
     * @var \Joker\Swoole\Facade\Server
     */
    public $onCloseServer;

    public function __construct()
    {

    }

    public function onWorkerStart(Server $server, int $workerId){
        $this->workerId = $workerId;
        if ($this->workerId < System::getHeartbeatWorkerNum()){
            HeartBeatManager::start($this->workerId);
            return;
        }

        if ($this->workerId < (System::getHeartbeatWorkerNum() + System::getMessageWorkerNum())){
            MessageManager::start($this->workerId);
            return;
        }

        $this->onOpenServer = new \Joker\Swoole\Facade\Server(Event::EVENT_TYPE_OPEN);
        $this->onMessageServer = new \Joker\Swoole\Facade\Server(Event::EVENT_TYPE_MESSAGE);
        $this->onCloseServer = new \Joker\Swoole\Facade\Server(Event::EVENT_TYPE_CLOSE);

    }

    public function onOpen(Server $server, \Swoole\Http\Request $request)
    {
        try{
            HeartBeatManager::ping($request->fd);

            $socketId = FdManager::register($request->fd);

            if(OnFunc::$onOpen !== null){
                (OnFunc::$onOpen)($this->onOpenServer, $socketId);
            }

        }catch(JokerSwooleFatalException $jokerSwooleFatalException){
            Log::debug('系统错误:'. $jokerSwooleFatalException->getMessage());
            FdManager::pushWrongMsgAndClose($request->fd, '未知错误.');
        }catch (\Exception $exception){
            Log::debug('系统错误2:'. $exception->getMessage());
            FdManager::pushWrongMsgAndClose($request->fd, '未知错误.');
        }

        $this->gc($this->onOpenServer);
    }

    public function onMessage(Server $server, Frame $frame)
    {
        try{
            $msg = json_decode($frame->data, true);
            if (!is_array($msg) || !isset($msg['code']) || !isset($msg['data']) || !is_array($msg['data'])){
                return;
            }

            if (System::isEnableCheckSign() &&
                (
                    !isset($msg['s']) || !is_string($msg['s']) || !Security::checkSign($msg['s'])
                )
            ){
                FdManager::pushWrongMsgAndClose($frame->fd, '非法连接');
                return;
            }

            if ($msg['code'] === PingResponse::code){
                HeartBeatManager::receivePong($frame->fd, $msg['data']);
                return;
            }

            if ($msg['code'] === SuccessResponse::code){
                $socketId = FdManager::getSocketId($frame->fd);
                if(OnFunc::$onMessage !== null){
                    (OnFunc::$onMessage)($this->onMessageServer, $socketId, $msg['data']);
                }
            }
        }catch(JokerSwooleFatalException $jokerSwooleFatalException){
            Log::debug('系统错误:'. $jokerSwooleFatalException->getMessage());
            FdManager::pushWrongMsgAndClose($frame->fd, '未知错误.');
        }catch (\Exception $exception){
            Log::debug('系统错误2:'. $exception->getMessage());
            FdManager::pushWrongMsgAndClose($frame->fd, '未知错误.');
        }

        $this->gc($this->onMessageServer);
    }

    public function onClose(Server $server, string $fd)
    {
        try{
            $socketId = FdManager::getSocketId($fd);

            if(OnFunc::$onClose !== null){
                (OnFunc::$onClose)($this->onCloseServer, $socketId);
            }

            FdManager::del($fd);
        }catch(JokerSwooleFatalException $jokerSwooleFatalException){
            Log::debug('系统错误:'. $jokerSwooleFatalException->getMessage());
            FdManager::pushWrongMsgAndClose($fd, '未知错误.');
        }catch (\Exception $exception){
            Log::debug('系统错误2:'. $exception->getMessage());
            FdManager::pushWrongMsgAndClose($frame->fd, '未知错误.');
        }

        $this->gc($this->onCloseServer);
    }

    /**
     * 垃圾回收
     *
     * @throws JokerSwooleFatalException
     * @throws \Throwable
     */
    private function gc(\Joker\Swoole\Facade\Server $server)
    {
        $transactionLevel = $server->facade()->mysql()->transactionLevel();
        if ($transactionLevel > 1){
            throw new JokerSwooleFatalException('请勿使用事务嵌套。');
        }elseif ($transactionLevel == 1){
            $server->facade()->mysql()->rollBack();
        }
    }
}