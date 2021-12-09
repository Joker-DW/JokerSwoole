<?php


namespace Joker\Swoole\Libs;

use Joker\Swoole\Config\MysqlConfig;
use Joker\Swoole\Hook\Mysql\Capsule;

/**
 * Mysql管理器
 * Class RedisManager
 * @package App\Libs
 */
class MysqlManager
{
    /**
     * @var \Illuminate\Database\Connection[]
     */
    static private $instance = [];

    static public function getObj(int $eventType): \Illuminate\Database\Connection
    {
        if (!isset(self::$instance[$eventType])){
            self::$instance[$eventType] = self::makeConnection();
        }

        return self::$instance[$eventType];
    }

    static private function makeConnection(): \Illuminate\Database\Connection
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => MysqlConfig::$host,
            'port' => MysqlConfig::$port,
            'database' => MysqlConfig::$database,
            'username' => MysqlConfig::$username,
            'password' => MysqlConfig::$password,
            'charset' => MysqlConfig::charset,
            'collation' => MysqlConfig::collation,
            'prefix' => MysqlConfig::$prefix,
        ]);

        return $capsule->getConnection();
    }
}