<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Logger;

use FurqanSiddiqui\WebSocket\Server\User;

/**
 * Interface LoggerInterface
 * @package FurqanSiddiqui\WebSocket\Logger
 */
interface LoggerInterface
{
    public function connectionReceived(string $ip, int $port): void;

    public function connectionLost(string $ip, int $port): void;

    public function connectionTerminated(string $ip, int $port, int $code, ?string $message = null): void;

    public function connectionAccepted(User $user): void;

    public function disconnect(User $user, int $code, string $message): void;

}