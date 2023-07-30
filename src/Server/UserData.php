<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

/**
 * Class UserData
 * @package FurqanSiddiqui\WebSocket\Server
 */
class UserData
{
    private array $data = [];

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[strtolower($key)] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->data[strtolower($key)] ?? null;
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        unset($this->data[strtolower($key)]);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->data);
    }
}