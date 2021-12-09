<?php


namespace Joker\Swoole\Config;


class RedisConfig
{
    static public $host = 'localhost';

    static public $port = 6379;

    static public $password = '';

    static public $db = 0;

    public const prefix = "JokerSwoole";

    public const keySeparator = "|||";

    /**
     * 用于区别业务redis key
     */
    public const keyUniqueStr = 'fj893thy78w3hf8';
}