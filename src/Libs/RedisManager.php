<?php


namespace Joker\Swoole\Libs;

use Joker\Swoole\Config\RedisConfig;
use Joker\Swoole\Hook\RedisHook;

/**
 * Redis管理器
 * Class RedisManager
 * @package App\Libs
 */
class RedisManager
{
    static public function getObj(): RedisHook
    {
        $redis = new RedisHook();
        $redis->connect(RedisConfig::$host, RedisConfig::$port);
        $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
        if (!empty(RedisConfig::$password)){
            $redis->auth(RedisConfig::$password);
        }
        $redis->select(RedisConfig::$db);
        return $redis;
    }

    static public function makeKey(array $keys): string
    {
        return RedisConfig::prefix. RedisConfig::keySeparator. RedisConfig::keyUniqueStr. RedisConfig::keySeparator. join(RedisConfig::keySeparator, $keys);
    }
}