<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Exception;

use FurqanSiddiqui\WebSocket\Socket\SocketLastError;

/**
 * Class WebSocketException
 * @package FurqanSiddiqui\WebSocket\Exception
 */
class WebSocketException extends \Exception
{
    public readonly ?SocketLastError $socketError;

    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param \FurqanSiddiqui\WebSocket\Socket\SocketLastError|null $socketError
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null, ?SocketLastError $socketError = null)
    {
        parent::__construct($message, $code, $previous);
        $this->socketError = $socketError;
    }
}