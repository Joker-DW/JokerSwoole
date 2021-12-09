<?php

namespace Joker\Swoole\Libs\Tools\Response;

use Joker\Swoole\Libs\Tools\Response;

class SuccessResponse
{
    public const code = 0;

    static public function formatJson(array $data): string
    {
        return Response::formatJson(self::code, $data, 'success');
    }
}