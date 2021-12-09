<?php

namespace Joker\Swoole\Libs\Tools;

class Response
{
    static public function formatJson(int $code, $data = [], string $msg = ''): string
    {
        if (!empty($data) && array_keys($data) === range(0, count($data) - 1)){
            throw new \Exception('data必须为关联数组');
        }

        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => new \ArrayObject($data)
        ], JSON_UNESCAPED_UNICODE);
    }
}