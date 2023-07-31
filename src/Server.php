<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket;

use FurqanSiddiqui\WebSocket\Exception\WebSocketException;
use FurqanSiddiqui\WebSocket\Logger\DefaultLogger;
use FurqanSiddiqui\WebSocket\Logger\LoggerInterface;
use FurqanSiddiqui\WebSocket\Messages\ClientMessage;
use FurqanSiddiqui\WebSocket\Server\HttpWsHeaders;
use FurqanSiddiqui\WebSocket\Server\MessageHandleEvent;
use FurqanSiddiqui\WebSocket\Server\User;
use FurqanSiddiqui\WebSocket\Server\UsersPool;
use FurqanSiddiqui\WebSocket\Socket\SocketLastError;
use FurqanSiddiqui\WebSocket\Socket\TimeoutsConfig;

/**
 * Class Server
 * @package FurqanSiddiqui\WebSocket
 */
class Server extends AbstractWebSocket
{
    /** @var \FurqanSiddiqui\WebSocket\Socket\TimeoutsConfig Various timeout events configuration */
    public readonly TimeoutsConfig $timeOuts;
    /** @var \FurqanSiddiqui\WebSocket\Server\HttpWsHeaders */
    public readonly HttpWsHeaders $responseHeaders;
    /** @var \FurqanSiddiqui\WebSocket\Server\UsersPool */
    public readonly UsersPool $users;

    /** @var \FurqanSiddiqui\WebSocket\Logger\LoggerInterface Logger interface */
    public LoggerInterface $logs;
    /** @var \FurqanSiddiqui\WebSocket\Server\MessageHandleEvent */
    public MessageHandleEvent $msgHandleOn = MessageHandleEvent::LIVE;

    /** @var \Closure Callback method for socket_select() call fail */
    private \Closure $changeDetectFail;
    /** @var \Closure Callback method for user authentication */
    private \Closure $userAuthCallback;
    /** @var \Closure Callback method when a complete message frame is received */
    private \Closure $onMessageReceived;
    /** @var \Closure|null Callback at the end iteration */
    private ?\Closure $everyLoop = null;

    /**
     * @param string $hostname
     * @param int $port
     * @param string $magicString
     * @param string $statusLine
     * @param bool $allowPrivateIPs
     * @param array|null $allowPaths
     * @param int $readChunkSize
     * @param \FurqanSiddiqui\WebSocket\Logger\LoggerInterface|null $logger
     * @throws \FurqanSiddiqui\WebSocket\Exception\WebSocketException
     */
    public function __construct(
        string                 $hostname,
        int                    $port,
        public readonly string $magicString = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11",
        public readonly string $statusLine = "Switching Protocols",
        public readonly bool   $allowPrivateIPs = true,
        public readonly ?array $allowPaths = ["/"],
        public readonly int    $readChunkSize = 1024,
        ?LoggerInterface       $logger = null,
    )
    {
        parent::__construct($hostname, $port);

        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw (new SocketLastError($this->socket))->exception('Failed to initialise websocket');
        }

        if (!@socket_bind($this->socket, $this->hostname, $this->port)) {
            throw (new SocketLastError($this->socket))->exception('Failed to bind server listen IP or port');
        }

        if (!@socket_listen($this->socket)) {
            throw (new SocketLastError($this->socket))->exception('Websocket failed to start listening');
        }

        if (!@socket_set_nonblock($this->socket)) {
            throw (new SocketLastError($this->socket))->exception('Failed to set websocket server to non-blocking mode');
        }

        $this->logs = $logger ?? new DefaultLogger();
        $this->timeOuts = new TimeoutsConfig();
        $this->users = new UsersPool($this);
        $this->responseHeaders = new HttpWsHeaders(validate: false);

        // Default response headers to connecting users
        $this->responseHeaders->set("Upgrade", "websocket");
        $this->responseHeaders->set("Connection", "Upgrade");

        // Default behaviour on socket_select() call fail is to throw WebSocketException:
        $this->socketChangeDetectFail(function (WebSocketException $e) {
            throw $e;
        });

        // Default behaviour, everybody is authorised
        $this->setAuthenticationCallback(function () {
            return true;
        });
    }

    /**
     * Callback method everytime a complete message frame is received
     * @param \Closure $callback
     * @return $this
     */
    public function onMessageReceive(\Closure $callback): static
    {
        $this->onMessageReceived = $callback;
        return $this;
    }

    /**
     * Callback method set here will receive instance of User object on new connections (after initial checking)
     * Implementing methods must return TRUE or FALSE, or in turn can throw exceptions
     * @param \Closure $callback
     * @return $this
     */
    public function setAuthenticationCallback(\Closure $callback): static
    {
        $this->userAuthCallback = $callback;
        return $this;
    }

    /**
     * This method is called internally, should not be used from outside
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @return bool
     */
    public function event_authorizeUser(User $user): bool
    {
        return call_user_func_array($this->userAuthCallback, [$user]);
    }

    /**
     * This method is called internally, calls callback method when a message is received
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @param \FurqanSiddiqui\WebSocket\Messages\ClientMessage $message
     * @return void
     */
    public function event_messageReceived(User $user, ClientMessage $message): void
    {
        call_user_func_array($this->onMessageReceived, [$user, $message]);
    }

    /**
     * Determines how to handle the event when socket_select() returns FALSE, callback function is given with
     * instance of WebSocketException as argument which in turn contains instance of SocketLastError.
     * @param \Closure $callback
     * @return $this
     */
    public function socketChangeDetectFail(\Closure $callback): static
    {
        $this->changeDetectFail = $callback;
        return $this;
    }

    /**
     * @return never
     */
    public function start(): never
    {
        while (true) {
            $reads = $this->users->sockets();
            array_unshift($reads, $this->socket);
            $n = null;
            $changes = @socket_select($reads, $n, $n, $this->timeOuts->changesSecs, $this->timeOuts->changesMicroSecs);
            if ($changes === false) {
                call_user_func_array($this->changeDetectFail, [
                    (new SocketLastError($this->socket))->exception('Server socket_select failed')
                ]);

                continue;
            }

            foreach ($reads as $readSocket) {
                unset($newClientSocket);

                if ($readSocket === $this->socket) {
                    // Handle a new connection request
                    $newClientSocket = @socket_accept($readSocket);
                    if ($newClientSocket) {
                        new User($this, $newClientSocket);
                    }
                } else {
                    // Handle incoming messages
                    $this->users->search($readSocket)?->readToBuffer();
                }
            }

            // If configured, handle User Messages?
            if ($this->msgHandleOn === MessageHandleEvent::EVERY_LOOP) {
                /** @var User $user */
                foreach ($this->users as $user) {
                    while ($user->hasMessages() > 0) {
                        $this->event_messageReceived($user, $user->getMessage());
                    }
                }
            }

            // Callback and perhaps sleep?
            if ($this->everyLoop) {
                call_user_func_array($this->everyLoop, [$this]);
            }

            if ($this->timeOuts->listenUsleep > 0) {
                usleep($this->timeOuts->listenUsleep);
            }
        }
    }
}
