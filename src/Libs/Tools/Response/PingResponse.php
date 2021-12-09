<?php

namespace Joker\Swoole\Libs\Tools\Response;

use Joker\Swoole\Libs\Tools\Response;

class PingResponse
{
    public const code = 999;

    static public function formatJson(array $data): string
    {
        return Response::formatJson(self::code, $data, 'success');
    }
}