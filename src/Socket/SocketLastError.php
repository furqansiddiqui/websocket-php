<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Socket;

use FurqanSiddiqui\WebSocket\Exception\WebSocketException;

/**
 * Class SocketLastError
 * @package FurqanSiddiqui\WebSocket\Socket
 */
class SocketLastError
{
    public readonly ?int $code;
    public readonly ?string $error;

    /**
     * @param \Socket|null $socket
     */
    public function __construct(?\Socket $socket = null)
    {
        $this->code = socket_last_error($socket) ?: null;
        $this->error = $this->code ? socket_strerror($this->code) : null;
    }

    /**
     * @param string $message
     * @return \FurqanSiddiqui\WebSocket\Exception\WebSocketException
     */
    public function exception(string $message): WebSocketException
    {
        return new WebSocketException($message, socketError: $this);
    }
}