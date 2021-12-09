<?php


namespace Joker\Swoole\Share;


class System
{
    private static $ip;

    private static $pid;

    private static $workerNum;

    private static $businessWorkerNum;

    private static $messageWorkerNum;

    private const heartbeatWorkerNum = 1;

    private static $enableCheckSign = false;

    private static $debug = false;

    public static function init(int $businessWorkerNum){
        $ips = swoole_get_local_ip();
        self::$ip = array_shift($ips);
        self::$pid = \posix_getpid();

        self::$businessWorkerNum = $businessWorkerNum;

        self::$messageWorkerNum = floor($businessWorkerNum / 3);
        if (self::$messageWorkerNum < 1){
            self::$messageWorkerNum = 1;
        }

        self::$workerNum = self::$businessWorkerNum + self::$messageWorkerNum + System::heartbeatWorkerNum;
    }

    public static function getIp(): string
    {
        if (empty(self::$ip)){
            throw new \Exception('未设置IP');
        }
        return self::$ip;
    }

    public static function getPid(): int
    {
        if (empty(self::$pid)){
            throw new \Exception('未设置pid');
        }
        return self::$pid;
    }

    public static function getWorkerNum(): int
    {
        if (empty(self::$workerNum)){
            throw new \Exception('未设置workerNum');
        }
        return self::$workerNum;
    }

    public static function getBusinessWorkerNum(): int
    {
        if (empty(self::$businessWorkerNum)){
            throw new \Exception('未设置businessWorkerNum');
        }
        return self::$businessWorkerNum;
    }

    public static function getMessageWorkerNum(): int
    {
        if (empty(self::$messageWorkerNum)){
            throw new \Exception('未设置messageWorkerNum');
        }
        return self::$messageWorkerNum;
    }

    public static function getHeartbeatWorkerNum(): int
    {
        return self::heartbeatWorkerNum;
    }

    public static function enableCheckSign(){
        self::$enableCheckSign = true;
    }

    public static function isEnableCheckSign(): bool
    {
        return self::$enableCheckSign;
    }

    public static function enableDebug()
    {
        self::$debug = true;
    }

    public static function isDebug(){
        return self::$debug;
    }
}