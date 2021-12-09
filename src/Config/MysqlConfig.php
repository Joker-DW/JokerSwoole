<?php


namespace Joker\Swoole\Config;


class MysqlConfig
{
    static public $host = 'localhost';

    static public $port = 3306;

    static public $database = 'database';

    static public $username = '';

    static public $password = '';

    static public $prefix = '';

    public const charset = 'utf8mb4';

    public const collation = 'utf8mb4_general_ci';
}