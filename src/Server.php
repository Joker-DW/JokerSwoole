<?php


namespace Joker\Swoole;


use Joker\Swoole\Config\MysqlConfig;
use Joker\Swoole\Config\RedisConfig;
use Joker\Swoole\Libs\FdManager;
use Joker\Swoole\Libs\HeartBeatManager;
use Joker\Swoole\Libs\MessageManager;
use Joker\Swoole\Process\Master;
use Joker\Swoole\Process\Worker;
use Joker\Swoole\Share\OnFunc;
use Joker\Swoole\Share\System;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

final class Server
{
    /**
     * @var SwooleWebSocketServer
     */
    private $server;

    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->initShare();
        $this->initSwoole();
        $this->initTable();
        $this->initRedis();
        $this->initMysql();
    }

    private function initSwoole()
    {
        $master = new Master();
        $this->worker = new Worker();

        $this->server = new SwooleWebSocketServer($this->config->swooleHost, $this->config->swoolePort);

        $this->initServer();

        $this->server->set([
            'worker_num' => System::getWorkerNum(),
            'enable_coroutine' => false,
            'dispatch_func' => [$master, 'dispatchFunc']
        ]);

        $this->server->on('workerStart', [$this->worker, 'onWorkerStart']);
        $this->server->on('open', [$this->worker, 'onOpen']);
        $this->server->on('message', [$this->worker, 'onMessage']);
        $this->server->on('close', [$this->worker, 'onClose']);
    }

    private function initShare(){
        System::init($this->config->swooleWorkerNum);
        if ($this->config->enableCheckSign){
            System::enableCheckSign();
        }

        if ($this->config->debug){
            System::enableDebug();
        }
    }

    private function initTable(){
        FdManager::makeTable();
        HeartBeatManager::makeTable();
        MessageManager::makeTable();
    }

    private function initServer(){
        FdManager::setServer($this->server);
    }

    private function initRedis(){
        RedisConfig::$host = $this->config->redisHost;
        RedisConfig::$port = $this->config->redisPort;
        RedisConfig::$password = $this->config->redisPassword;
        RedisConfig::$db = $this->config->redisDb;

        MessageManager::initRedis();
    }

    private function initMysql(){
        MysqlConfig::$host = $this->config->mysqlHost;
        MysqlConfig::$port = $this->config->mysqlPort;
        MysqlConfig::$database = $this->config->mysqlDatabase;
        MysqlConfig::$username = $this->config->mysqlUsername;
        MysqlConfig::$password = $this->config->mysqlPassword;
        MysqlConfig::$prefix = $this->config->mysqlPrefix;
    }

    /**
     * @param callable $func function (\Joker\Swoole\Facade\Server $server, int $socketId)
     */
    public function onOpen(callable $func){
        OnFunc::$onOpen = $func;
    }

    /**
     * @param callable $func function (\Joker\Swoole\Facade\Server $server, int $socketId, array $data)
     */
    public function onMessage(callable $func){
        OnFunc::$onMessage = $func;
    }

    /**
     * @param callable $func function (\Joker\Swoole\Facade\Server $server, int $socketId)
     */
    public function onClose(callable $func){
        OnFunc::$onClose = $func;
    }

    public function start(){
        $this->server->start();
    }
}