<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket;

use FurqanSiddiqui\WebSocket\Exception\WebSocketException;
use FurqanSiddiqui\WebSocket\Socket\SocketLastError;

/**
 * Class AbstractWebSocket
 * @package FurqanSiddiqui\WebSocket
 */
abstract class AbstractWebSocket
{
    public readonly \Socket $socket;

    /**
     * @param string $hostname
     * @param int $port
     * @throws \FurqanSiddiqui\WebSocket\Exception\WebSocketException
     */
    public function __construct(
        public readonly string $hostname,
        public readonly int    $port,
    )
    {
        if (!filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new WebSocketException('Invalid IPv4 websocket host address');
        }

        if ($port < 0x50 || $port > 0xffff) {
            throw new WebSocketException('Invalid websocket listen port');
        }

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new WebSocketException('Failed to create TCP socket', socketError: new SocketLastError(null));
        }

        $this->socket = $socket;
    }
}