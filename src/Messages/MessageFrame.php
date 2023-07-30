<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Messages;

use FurqanSiddiqui\WebSocket\Exception\MessageFramesException;

/**
 * Class MessageFrame
 * @package FurqanSiddiqui\WebSocket\Messages
 */
class MessageFrame
{
    public readonly ?string $maskBytesStr;
    public readonly ?array $maskBytes;

    private string $message = "";
    public bool $isComplete = false;
    public string $excessBytes = "";

    /**
     * @param bool $isFinal
     * @param \FurqanSiddiqui\WebSocket\Messages\Opcodes $opCode
     * @param int $messageLength
     * @param string|null $maskBytes
     */
    public function __construct(
        public readonly bool    $isFinal,
        public readonly Opcodes $opCode,
        public readonly int     $messageLength,
        ?string                 $maskBytes = null,
    )
    {
        if ($maskBytes) {
            $this->maskBytes = $this->maskingKeyFromBytes($maskBytes);
            $this->maskBytesStr = $maskBytes;
        } else {
            $this->maskBytes = null;
            $this->maskBytesStr = null;
        }
    }

    /**
     * @param string $bytes
     * @return $this
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function append(string $bytes): static
    {
        if ($this->isComplete) {
            throw MessageFramesException::CannotAppendCompleteFrame();
        }

        $this->message .= $bytes;
        if (strlen($this->message) > $this->messageLength) {
            $this->excessBytes = substr($this->message, $this->messageLength);
            $this->message = substr($this->message, 0, $this->messageLength);
            $this->isComplete = true;
        }

        // Is complete?
        if (strlen($this->message) === $this->messageLength) {
            $this->isComplete = true;
        }

        return $this;
    }

    /**
     * @return string
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function seal(): string
    {
        if (!$this->isComplete) {
            throw MessageFramesException::CannotSealIncompleteFrame();
        }

        // FIN bit + 3x reserved bits + 4 bits OpCode
        $firstByte = gmp_intval(gmp_init(($this->isFinal ? 1 : 0) . "000" . $this->opCode->value, 2));
        if ($this->maskBytes) {
            $masked = "";
            for ($i = 0; $i < strlen($this->message); $i++) {
                $masked .= chr(ord($this->message[$i]) ^ $this->maskBytes[$i % 4]);
            }

            $payload = $masked;
        } else {
            $payload = $this->message;
        }

        $payloadLength = strlen($payload);
        $lengthBytes = null;
        if ($payloadLength <= 125) {
            $lengthIndicator = $payloadLength;
        } else {
            $lengthIndicator = $payloadLength <= 0xffff ? 126 : 127;
            $lengthBytes = pack($payloadLength <= 0xffff ? "n" : "J", $payloadLength);
        }

        $maskAndLength = ($this->maskBytes ? 1 : 0) . str_pad(gmp_strval($lengthIndicator, 2), 7, "0", STR_PAD_LEFT);
        $sealed = pack("CC", $firstByte, gmp_intval(gmp_init($maskAndLength, 2))) . $lengthBytes;
        $sealed .= $this->maskBytesStr;
        return $sealed . $payload;
    }

    /**
     * @return string
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function unseal(): string
    {
        if (!$this->isComplete) {
            throw MessageFramesException::CannotUnsealIncompleteFrame();
        }

        if (!$this->maskBytes) {
            return $this->message;
        }

        $unmasked = "";
        for ($i = 0; $i < strlen($this->message); $i++) {
            $unmasked .= chr(ord($this->message[$i]) ^ $this->maskBytes[$i % 4]);
        }

        return $unmasked;
    }

    /**
     * @param string $received
     * @return static
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public static function Open(string $received): static|null
    {
        $firstByte = str_pad(gmp_strval(unpack("C", $received[0])[1], 2), 8, "0", STR_PAD_LEFT);
        $isFinalFrame = $firstByte[0] === "1";
        $opCode = Opcodes::tryFrom(substr($firstByte, -4));
        if (!$opCode) {
            throw MessageFramesException::BadOpCodeReceived();
        }

        $maskAndLength = str_pad(gmp_strval(unpack("C", $received[1])[1], 2), 8, "0", STR_PAD_LEFT);
        $isMasked = $maskAndLength[0] === "1";
        $lengthIndicator = gmp_intval(gmp_init(substr($maskAndLength, 1), 2));
        if ($lengthIndicator > 127) {
            throw MessageFramesException::BadMessageLengthIndicator();
        }

        $lengthBytes = match ($lengthIndicator) {
            127 => 8,
            126 => 2,
            default => 0
        };

        $requiredBytes = 2 + $lengthBytes + ($isMasked ? 4 : 0);
        if (strlen($received) < $requiredBytes) { // Doesn't have necessary number of bytes yet
            return null;
        }

        if ($lengthBytes === 0) {
            $length = $lengthIndicator;
        } else {
            $length = unpack($lengthIndicator === 127 ? "J" : "n", substr($received, 2, $lengthBytes))[1];
        }

        $maskBytes = $isMasked ? substr($received, 2 + $lengthBytes, 4) : null;
        $payload = $isMasked ? substr($received, 6 + $lengthBytes) : substr($received, 2 + $lengthBytes);
        return (new static($isFinalFrame, $opCode, $length, $maskBytes))->append($payload);
    }

    /**
     * @param string $bytes
     * @return array
     */
    private static function maskingKeyFromBytes(string $bytes): array
    {
        if (strlen($bytes) !== 4) {
            throw new \RuntimeException('Masking key must be precisely 4 bytes');
        }

        return [
            ord($bytes[0]),
            ord($bytes[1]),
            ord($bytes[2]),
            ord($bytes[3]),
        ];
    }
}