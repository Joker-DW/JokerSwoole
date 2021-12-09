<?php

namespace Joker\Swoole\Hook;

use Joker\Swoole\Exception\JokerSwooleFatalException;


class RedisHook
{
    private $redis;
    private $redisReflectionClass;

    private $host = null;
    private $port = null;
    private $optionArr = [];
    private $password = null;
    private $db = null;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redisReflectionClass = new \ReflectionClass($this->redis);
    }

    private function _reConnect(){
        if ($this->host !== null && $this->port !== null){
            $this->connect($this->host, $this->port);
        }

        if (!empty($this->optionArr)){
            $this->_setOptions($this->optionArr);
        }

        if ($this->password !== null){
            $this->auth($this->password);
        }

        if ($this->db !== null){
            $this->select($this->db);
        }
    }

    public function __call($name, $arguments)
    {
        if (!$this->_isFuncExistsInRedis($name)){
            throw new JokerSwooleFatalException('Redis不存在方法'. $name);
        }

        while (true){
            try {
                return call_user_func_array([$this->redis, $name], $arguments);
            }catch (\RedisException $redisException){
                sleep(2);
                $this->_reConnect();
            }
        }
    }

    private function _isFuncExistsInRedis(string $funcName): bool
    {
        return (
            $this->redisReflectionClass->hasMethod($funcName)
            && $this->redisReflectionClass->getMethod($funcName)->isPublic()
        );
    }

    public function connect(string $host, int $port): bool
    {
        $this->host = $host;
        $this->port = $port;

        while (true){
            try {
                return $this->redis->connect($host, $port);
            }catch (\RedisException $redisException){
                sleep(2);
            }
        }
    }

    public function setOption(int $option, $value): bool
    {
        $this->optionArr[$option] = $value;

        while (true){
            try {
                return $this->redis->setOption($option, $value);
            }catch (\RedisException $redisException){
                sleep(2);
                $this->connect($this->host, $this->port);
                $this->_setOptions($this->optionArr);
            }
        }
    }

    private function _setOptions(array $options)
    {
        if (!empty($options)){
            foreach ($options as $option => $value){
                $this->setOption($option, $value);
            }
        }
    }

    public function auth(string $password): bool
    {
        $this->password = $password;

        while (true){
            try {
                return $this->redis->auth($password);
            }catch (\RedisException $redisException){
                sleep(2);
                $this->connect($this->host, $this->port);
                $this->_setOptions($this->optionArr);
            }
        }
    }

    public function select(int $db): bool
    {
        $this->db = $db;

        while (true){
            try {
                return $this->redis->select($db);
            }catch (\RedisException $redisException){
                sleep(2);
                $this->connect($this->host, $this->port);
                $this->_setOptions($this->optionArr);
                if ($this->password !== null){
                    $this->auth($this->password);
                }
            }
        }

    }
}