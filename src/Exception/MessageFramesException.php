<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Exception;

/**
 * Class MessageFramesException
 * @package FurqanSiddiqui\WebSocket\Exception
 */
class MessageFramesException extends WebSocketException
{
    /**
     * @return static
     */
    public static function BadOpCodeReceived(): static
    {
        return new static('Bad OpCode received', 1000);
    }

    /**
     * @return static
     */
    public static function BadMessageLengthIndicator(): static
    {
        return new static('Invalid message length indicator', 1001);
    }

    /**
     * @return static
     */
    public static function CannotAppendCompleteFrame(): static
    {
        return new static('Cannot append to an already completed frame', 1002);
    }

    /**
     * @return static
     */
    public static function CannotSealIncompleteFrame(): static
    {
        return new static('Cannot seal an incomplete frame', 1003);
    }

    /**
     * @return static
     */
    public static function CannotUnsealIncompleteFrame(): static
    {
        return new static('Cannot unseal an incomplete frame', 1004);
    }

    /**
     * @return static
     */
    public static function FramesMismatchOpcodes(): static
    {
        return new static('All frames in a message must have same OpCode', 1005);
    }

    /**
     * @return static
     */
    public static function CannotUnsealIncompleteMsg(): static
    {
        return new static('Cannot unseal an incomplete frame', 1006);
    }

    /**
     * @return static
     */
    public static function UnmaskedMsgFromClient(): static
    {
        return new static('Clients cannot send unmasked messages', 1007);
    }
}