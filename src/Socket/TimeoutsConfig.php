<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Socket;

/**
 * Class TimeoutsConfig
 * @package FurqanSiddiqui\WebSocket\Socket
 */
class TimeoutsConfig
{
    /**
     * @param int|null $changesSecs Defaults to NULL, blocks indefinitely
     * @param int $changesMicroSecs
     * @param int $readSecs
     * @param int $readMicroSecs
     * @param int $writeSecs
     * @param int $writeMicroSecs
     * @param int $handshakeWaitSecs
     * @param int $listenUsleep In microseconds (where value 1,000,000 = 1sec)
     */
    public function __construct(
        public int|null $changesSecs = null,
        public int      $changesMicroSecs = 0,
        public int      $readSecs = 0,
        public int      $readMicroSecs = 0,
        public int      $writeSecs = 0,
        public int      $writeMicroSecs = 0,
        public int      $handshakeWaitSecs = 10,
        public int      $listenUsleep = 100000
    )
    {
    }
}