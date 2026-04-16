<?php

namespace Spark\Http;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message ?: self::defaultMessage($statusCode), 0, $previous);
    }

    protected static function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'HTTP Error',
        };
    }
}
