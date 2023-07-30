<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Messages;

use FurqanSiddiqui\WebSocket\Exception\MessageFramesException;

/**
 * Class ClientMessage
 * @package FurqanSiddiqui\WebSocket\Messages
 */
class ClientMessage
{
    public bool $isComplete = false;
    public string $excessBytes = "";
    public ?Opcodes $opCode = null;

    private ?MessageFrame $currentFrame = null;
    private array $frames = [];
    private string $buffer = "";

    /**
     * @return string
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function unseal(): string
    {
        if (!$this->isComplete) {
            throw MessageFramesException::CannotUnsealIncompleteFrame();
        }

        $unsealed = "";
        /** @var \FurqanSiddiqui\WebSocket\Messages\MessageFrame $frame */
        foreach ($this->frames as $frame) {
            if ($frame->isComplete) {
                $unsealed .= $frame->unseal();
            }
        }

        return $unsealed;
    }

    /**
     * @param string $buffer
     * @return void
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function writeToBuffer(string $buffer): void
    {
        // Has an incomplete frame?
        if ($this->currentFrame) {
            $this->currentFrame->append($buffer); // Append to buffer directly to frame
            if ($this->currentFrame->isComplete) { // Frame is now complete?
                $this->buffer = $this->currentFrame->excessBytes;
                $this->currentFrame->excessBytes = "";
                $this->frames[] = $this->currentFrame;
                if ($this->currentFrame->isFinal) {
                    $this->isComplete = true; // Final frame received, Message is complete
                }

                $this->currentFrame = null;
            }
        } else {
            $this->buffer .= $buffer;
        }

        while (strlen($this->buffer) >= 1) {
            if ($this->isComplete) {
                $this->excessBytes = $this->buffer;
                return;
            }

            if (strlen($this->buffer) >= 2) {
                $newFrame = MessageFrame::Open($this->buffer);
                if (!$newFrame) { // Not enough bytes yet to open message?
                    return;
                }

                if (!$newFrame->maskBytes) {
                    throw MessageFramesException::UnmaskedMsgFromClient();
                }

                /** @var false|\FurqanSiddiqui\WebSocket\Messages\MessageFrame $lastFrame */
                $lastFrame = end($this->frames);
                if ($lastFrame && $lastFrame->opCode !== $newFrame->opCode) {
                    throw MessageFramesException::FramesMismatchOpcodes();
                }

                if (!$this->opCode) {
                    $this->opCode = $newFrame->opCode;
                }

                if ($newFrame->isComplete) {
                    if ($newFrame->isFinal) {
                        $this->isComplete = true;
                    }

                    $this->buffer = $newFrame->excessBytes;
                    $this->frames[] = $newFrame;
                } else {
                    $this->buffer = "";
                    $this->currentFrame = $newFrame;
                }
            }
        }
    }
}