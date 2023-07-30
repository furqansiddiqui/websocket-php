<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

use FurqanSiddiqui\WebSocket\Messages\Opcodes;
use FurqanSiddiqui\WebSocket\Messages\ServerMessage;
use FurqanSiddiqui\WebSocket\Server;

/**
 * Class UsersPool
 * @package FurqanSiddiqui\WebSocket\Server
 */
class UsersPool
{
    protected array $users = [];
    protected int $count = 0;

    /**
     * @param \FurqanSiddiqui\WebSocket\Server $wsServer
     * @param \Closure|null $onJoinCallback Callback with User instance as it enters pool
     * @param \Closure|null $onAuthenticateCallback Callback with User instances once its accepted/authenticated
     * @param \Closure|null $onRemoveCallback Purpose of this callback is to do cleanup from any groups/rooms
     */
    public function __construct(
        protected readonly Server $wsServer,
        public ?\Closure          $onJoinCallback = null,
        public ?\Closure          $onAuthenticateCallback = null,
        public ?\Closure          $onRemoveCallback = null
    )
    {
    }

    /**
     * Adds a user to pool, User may not have done handshake/authentications yet
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @return void
     */
    public function join(User $user): void
    {
        if ($this->onJoinCallback) {
            call_user_func_array($this->onJoinCallback, [$user]);
        }

        $this->users[$user->uid] = $user;
        $this->count++;
    }

    /**
     * @param string|\FurqanSiddiqui\WebSocket\Messages\ServerMessage $message
     * @param bool $binary
     * @return int
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function broadcast(string|ServerMessage $message, bool $binary = false): int
    {
        if (is_string($message)) {
            $message = (new ServerMessage($binary ? Opcodes::BINARY : Opcodes::TEXT))->addFrame($message, true);
        }

        $sent = 0;
        /** @var \FurqanSiddiqui\WebSocket\Server\User $user */
        foreach ($this->users as $user) {
            if ($user->send($message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @return void
     */
    public function remove(User $user): void
    {
        unset($this->users[$user->uid]);
        $this->count--;

        if ($this->onRemoveCallback) {
            call_user_func_array($this->onRemoveCallback, [$user]);
        }
    }

    /**
     * @param \Socket $socket
     * @return \FurqanSiddiqui\WebSocket\Server\User|null
     */
    public function search(\Socket $socket): ?User
    {
        /** @var \FurqanSiddiqui\WebSocket\Server\User $user */
        foreach ($this->users as $user) {
            if ($user->socket() === $socket) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param string $ip
     * @param int $port
     * @return \FurqanSiddiqui\WebSocket\Server\User|null
     */
    public function find(string $ip, int $port): ?User
    {
        return $this->get(hash("sha1", $port . "@" . $ip, true));
    }

    /**
     * @param string $uid
     * @return \FurqanSiddiqui\WebSocket\Server\User|null
     */
    public function get(string $uid): ?User
    {
        $found = null;
        /** @var \FurqanSiddiqui\WebSocket\Server\User $user */
        foreach ($this->users as $user) {
            if ($uid === $user->uid) {
                $found = $user;
                break;
            }
        }

        return $found;
    }

    /**
     * Gets an Array of all socket instances/resources in pool, also removes lost connections from pool
     * @return array
     */
    public function sockets(): array
    {
        $sockets = [];
        /** @var \FurqanSiddiqui\WebSocket\Server\User $user */
        foreach ($this->users as $user) {
            if ($user->socket()) {
                $sockets[] = $user->socket();
                continue;
            }

            $this->remove($user);
        }

        return $sockets;
    }
}