<?php

namespace Joker\Swoole;

final class Config
{
    public $swooleHost = '0.0.0.0';
    public $swoolePort = 9501;
    public $swooleWorkerNum = 5;

    public $redisHost = '127.0.0.1';
    public $redisPort = 6379;
    public $redisPassword = '';
    public $redisDb = 0;

    public $mysqlHost = 'localhost';
    public $mysqlPort = 3306;
    public $mysqlDatabase = 'database';
    public $mysqlUsername = '';
    public $mysqlPassword = '';
    public $mysqlPrefix = '';

    /**
     * @var bool 开启验签；默认不开启；开启后将对所有Message信息进行验签
     */
    public $enableCheckSign = false;

    public $debug = false;
}