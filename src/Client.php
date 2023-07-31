<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket;

/**
 * Class Client
 * @package FurqanSiddiqui\WebSocket
 */
class Client extends AbstractWebSocket
{
    public function __construct(string $hostname, int $port, public readonly string $path = "/")
    {
        parent::__construct($hostname, $port);
    }
}