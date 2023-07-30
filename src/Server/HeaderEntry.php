<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

/**
 * Class HeaderEntry
 * @package FurqanSiddiqui\WebSocket\Server
 */
class HeaderEntry
{
    /**
     * @param string $name
     * @param string $value
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value
    )
    {
    }
}