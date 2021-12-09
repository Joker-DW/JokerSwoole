<?php

namespace Joker\Swoole\Hook\Mysql;

use Closure;
use Joker\Swoole\Exception\JokerSwooleFatalException;
use Joker\Swoole\Libs\Tools\Log;

class MysqlConnection extends \Illuminate\Database\MySqlConnection
{

    private $reConnectionInTransaction = false;
    private $reConnectionSqlList = [];

    protected function run($query, $bindings, Closure $callback)
    {
        while (true){
            try {
                $res = parent::run($query, $bindings, $callback);

                $this->reConnectionSqlList[] = [
                    'query' => $query,
                    'bindings' => $bindings,
                    'callback' => $callback
                ];

                return $res;
            }catch (\PDOException $e){
                if ($this->causedByLostConnection($e)){
                    Log::debug('run : Mysql连接超时，1秒后尝试重连。');
                    sleep(1);
                    $this->reconnect();
                    $this->reConnectSql();
                }else{
                    throw $e;
                }
            }
        }
    }

    /**
     * @deprecated
     * @param Closure $callback
     * @param int $attempts
     * @return mixed|void
     * @throws JokerSwooleFatalException
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        throw new JokerSwooleFatalException("transaction方法已经弃用");
    }

    public function beginTransaction()
    {
        while (true){
            try {
                if ($this->transactionLevel() > 0){
                    throw new JokerSwooleFatalException('请勿使用事务嵌套。');
                }

                parent::beginTransaction();

                $this->reConnectionInTransaction = true;

                break;
            }catch (\PDOException $e){
                if ($this->causedByLostConnection($e)){
                    Log::debug('beginTransaction : Mysql连接超时，1秒后尝试重连。');
                    sleep(1);
                    $this->reconnect();
                }else{
                    throw $e;
                }
            }
        }
    }

    public function commit()
    {
        while (true){
            try {
                parent::commit();

                $this->resetReConnection();

                break;
            }catch (\PDOException $e){
                if ($this->causedByLostConnection($e)){
                    Log::debug('commit : Mysql连接超时，1秒尝试重连。');
                    sleep(1);
                    $this->reconnect();
                    $this->reConnectSql();
                }else{
                    $this->resetReConnection();

                    throw $e;
                }
            }catch (\Exception $exception){
                $this->resetReConnection();

                throw $exception;
            }
        }
    }

    public function rollBack($toLevel = null)
    {
        try {
            parent::rollBack($toLevel);

            $this->resetReConnection();
        }catch (\PDOException $e){
            $this->resetReConnection();

        }catch (\Exception $exception){
            $this->resetReConnection();

            throw $exception;
        }
    }

    private function resetReConnection(){
        $this->reConnectionInTransaction = false;
        $this->reConnectionSqlList = [];
    }

    private function reConnectSql()
    {
        if (!$this->reConnectionInTransaction){
            return;
        }

        Log::debug('reConnectSql：由于Mysql断线前正处于事务中，所以开始重试执行断线前事务中的Sql。');

        while (true){
            try {
                $this->beginTransaction();
                Log::debug('reConnectSql：事务开启成功。');

                if (!empty($this->reConnectionSqlList)){
                    foreach ($this->reConnectionSqlList as $sql){
                        Log::debug('reConnectSql：开始执行Sql【'. $sql['query']. '】。');
                        parent::run($sql['query'], $sql['bindings'], $sql['callback']);
                        Log::debug('reConnectSql：Sql【'. $sql['query']. '】执行成功。');
                    }
                }
                Log::debug('reConnectSql：Sql全部成功。');

                break;
            }catch (\PDOException $e){
                if ($this->causedByLostConnection($e)){
                    Log::debug('reConnectSql : Mysql连接超时，1秒后尝试重连。');
                    sleep(1);
                    $this->reconnect();
                }else{
                    throw $e;
                }
            }
        }
    }
}