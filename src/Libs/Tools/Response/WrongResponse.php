<?php

namespace Joker\Swoole\Libs\Tools\Response;

use Joker\Swoole\Libs\Tools\Response;

class WrongResponse
{
    public const code = -1;

    static public function formatJson(string $msg): string
    {
        return Response::formatJson(self::code, [], $msg);
    }
}