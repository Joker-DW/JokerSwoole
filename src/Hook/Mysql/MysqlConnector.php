<?php

namespace Joker\Swoole\Hook\Mysql;

use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Share\Mysql;

class MysqlConnector extends \Illuminate\Database\Connectors\MySqlConnector
{
    /**
     * Create a new PDO connection instance.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     * @return \PDO
     */
    protected function createPdoConnection($dsn, $username, $password, $options)
    {
        $options[\PDO::ATTR_TIMEOUT] = 5;

        while (true){
            try {
                return new \PDO($dsn, $username, $password, $options);
            }catch (\PDOException $e){
                if ($this->causedByLostConnection($e)){
                    Log::debug('createPdoConnection : Mysql连接超时，尝试重连。');
                    sleep(1);
                }else{
                    throw $e;
                }
            }
        }
    }
}