<?php

namespace Joker\Swoole\Facade;

use Joker\Swoole\Libs\FdManager;
use Joker\Swoole\Libs\Tools\Log;
use Joker\Swoole\Libs\Tools\Response\SuccessResponse;

final class Server
{
    /**
     * @var Facade
     */
    private $facade;

    public function __construct(int $eventType)
    {
        $this->facade = new Facade($eventType);
    }

    public function push(int $socketId, array $msg): bool
    {
        try {
            return FdManager::push($socketId, SuccessResponse::formatJson($msg));
        }catch (\Exception $exception){
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public function close(int $socketId): bool
    {
        return FdManager::close($socketId);
    }

    public function facade(): Facade
    {
        return $this->facade;
    }
}