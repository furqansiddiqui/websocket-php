<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

use Traversable;

/**
 * Class HttpWsHeaders
 * @package FurqanSiddiqui\WebSocket\Server
 */
class HttpWsHeaders implements \IteratorAggregate
{
    private array $headers = [];

    /**
     * @param bool $validate
     * @param int $maxNameSize
     * @param int $maxValueSize
     */
    public function __construct(
        public readonly bool $validate,
        public int           $maxNameSize = 32,
        public int           $maxValueSize = 512
    )
    {
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->headers);
    }

    /**
     * @param string $name
     * @param string $value
     * @return bool
     */
    public function set(string $name, string $value): bool
    {
        if ($this->validate) {
            if (!preg_match("/^[a-z]+(-[a-z0-9]+)*$/i", $name) || strlen($name) > $this->maxNameSize) {
                return false;
            }

            if (!ctype_print($value) || strlen($value) > $this->maxValueSize) {
                return false;
            }
        }

        $this->headers[strtolower($name)] = new HeaderEntry($name, $value);
        return true;
    }

    /**
     * @param string $name
     * @return \FurqanSiddiqui\WebSocket\Server\HeaderEntry|null
     */
    public function get(string $name): ?HeaderEntry
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return \FurqanSiddiqui\WebSocket\Server\HeaderEntry|null
     */
    public function last(): ?HeaderEntry
    {
        $header = end($this->headers);
        return $header ?: null;
    }
}