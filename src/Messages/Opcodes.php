<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Messages;

/**
 * Class Opcodes
 * @package FurqanSiddiqui\WebSocket\Messages
 */
enum Opcodes: string
{
    case CONTINUE = "0000";
    case TEXT = "0001";
    case BINARY = "0002";
    case CLOSE = "1000";
    case PING = "1001";
    case PONG = "1010";
}