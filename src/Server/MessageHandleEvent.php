<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

/**
 * Class MessageHandleEvent
 * @package FurqanSiddiqui\WebSocket\Server
 */
enum MessageHandleEvent: int
{
    case EVERY_LOOP = 1;
    case LIVE = 2;
    case BATCH_PER_USER = 3;
}