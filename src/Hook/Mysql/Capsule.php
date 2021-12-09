<?php

namespace Joker\Swoole\Hook\Mysql;

use Illuminate\Container\Container;
use Illuminate\Database\DatabaseManager;

class Capsule extends \Illuminate\Database\Capsule\Manager
{
    public function __construct(Container $container = null)
    {
        parent::__construct($container);
    }

    protected function setupManager()
    {
        $factory = new ConnectionFactory($this->container);

        $this->manager = new DatabaseManager($this->container, $factory);
    }
}