<?php


namespace Joker\Swoole\Exception;

use Throwable;

class JokerSwooleFatalException extends \Exception
{
    public function __construct(string $message = "", int $code = -2, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}