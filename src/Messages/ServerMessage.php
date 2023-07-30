<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Messages;

use FurqanSiddiqui\WebSocket\Exception\MessageFramesException;

/**
 * Class ServerMessage
 * @package FurqanSiddiqui\WebSocket\Messages
 */
class ServerMessage
{
    private bool $isComplete = false;
    private array $frames = [];

    public function __construct(public readonly Opcodes $opCode)
    {
    }

    /**
     * @return string
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function seal(): string
    {
        if (!$this->isComplete) {
            throw MessageFramesException::CannotSealIncompleteMsg();
        }

        $sealed = "";
        /** @var \FurqanSiddiqui\WebSocket\Messages\MessageFrame $frame */
        foreach ($this->frames as $frame) {
            $sealed .= $frame->seal();
        }

        return $sealed;
    }

    /**
     * @param string $message
     * @param bool $isFinal
     * @return $this
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function addFrame(string $message, bool $isFinal = true): static
    {
        if ($this->isComplete) {
            throw MessageFramesException::CannotAppendCompleteMsg();
        }

        $this->isComplete = $isFinal;
        $this->frames[] = (new MessageFrame($isFinal, $this->opCode, strlen($message), null))->append($message);
        return $this;
    }
}