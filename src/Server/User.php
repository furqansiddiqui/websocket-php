<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Server;

use FurqanSiddiqui\WebSocket\Exception\MessageFramesException;
use FurqanSiddiqui\WebSocket\Messages\ClientMessage;
use FurqanSiddiqui\WebSocket\Messages\Opcodes;
use FurqanSiddiqui\WebSocket\Messages\ServerMessage;
use FurqanSiddiqui\WebSocket\Server;

/**
 * Class User
 * @package FurqanSiddiqui\WebSocket\Server
 */
class User
{
    /** @var \Socket|null Socket resource/instance */
    private ?\Socket $socket;
    /** @var bool System set value, TRUE when handshake+authentication was successful */
    private bool $handshake = false;
    /** @var string Buffer for incomplete messages/frames */
    private string $buffer = "";
    /** @var array */
    private array $messages = [];
    /** @var \FurqanSiddiqui\WebSocket\Messages\ClientMessage|null */
    private ?ClientMessage $pendingMessage = null;

    /** @var int Timestamp when connection was established */
    public readonly int $connectedOn;
    /** @var \FurqanSiddiqui\WebSocket\Server\HttpWsHeaders Request headers */
    public readonly HttpWsHeaders $headers;
    /** @var \FurqanSiddiqui\WebSocket\Server\UserData Arbitrary User data */
    public readonly UserData $data;
    /** @var string System generated unique identifier for user */
    public readonly string $uid;
    /** @var string IP address from connection was received */
    public readonly string $ip;
    /** @var int Port number on connection machine */
    public readonly int $port;
    /** @var string Connection was made to this path on server */
    public readonly string $path;

    /**
     * @param \FurqanSiddiqui\WebSocket\Server $wsServer
     * @param \Socket $socket
     */
    public function __construct(public readonly Server $wsServer, \Socket $socket)
    {
        $this->socket = $socket;
        $this->connectedOn = time();
        $this->headers = new HttpWsHeaders(validate: true);
        $this->data = new UserData();

        socket_getpeername($this->socket, $remoteIP, $remotePort);
        $this->ip = $remoteIP;
        $this->port = $remotePort;
        $this->uid = hash("sha1", $this->port . "@" . $this->ip, true);

        // Add to pool & Log received connection
        $this->wsServer->users->join($this);
        $this->wsServer->logs->connectionReceived($this->ip, $this->port);

        // Private range IP?
        if (!$this->wsServer->allowPrivateIPs) {
            if (!filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->terminate(403, "Forbidden", ["error" => "Connections from private/reserved IPs are disabled"]);
                return;
            }
        }

        // Proceed to read from socket
        $this->readToBuffer();
    }

    /**
     * @return int
     */
    public function hasMessages(): int
    {
        return count($this->messages);
    }

    /**
     * @return \FurqanSiddiqui\WebSocket\Messages\ClientMessage|null
     */
    public function getMessage(): ?ClientMessage
    {
        return array_shift($this->messages);
    }

    /**
     * @return void
     */
    public function readToBuffer(): void
    {
        if (!$this->socket) {
            return;
        }

        if (!@socket_recv($this->socket, $buffer, $this->wsServer->readChunkSize, MSG_DONTWAIT)) {
            $this->wsServer->logs->connectionLost($this->ip, $this->port);
            $this->closeSocket();
            return;
        }

        // Append to buffer
        $this->buffer .= $buffer;

        if (!$this->handshake) {
            // No handshake yet, received bytes might be handshake buffer
            $this->handshake();
            return;
        }

        try {
            while (strlen($this->buffer) >= 1) {
                unset($completed);

                if (!$this->pendingMessage) {
                    $this->pendingMessage = new ClientMessage();
                }

                if ($this->pendingMessage) {
                    $this->pendingMessage->writeToBuffer($this->buffer);
                    $this->buffer = "";

                    if ($this->pendingMessage->isComplete) {
                        $this->buffer = $this->pendingMessage->excessBytes;
                        $this->pendingMessage->excessBytes = "";
                        $completed = $this->pendingMessage;
                        $this->pendingMessage = null;

                        if ($this->wsServer->msgHandleOn === MessageHandleEvent::LIVE) {
                            $this->wsServer->event_messageReceived($this, $completed);
                        } else {
                            $this->messages[] = $completed;
                        }
                    }
                }
            }
        } catch (MessageFramesException $e) {
            $this->disconnect($e->getMessage(), $e->getCode());
            return;
        }

        if ($this->messages && $this->wsServer->msgHandleOn === MessageHandleEvent::BATCH_PER_USER) {
            while ($this->hasMessages() > 0) {
                $this->wsServer->event_messageReceived($this, $this->getMessage());
            }
        }
    }

    /**
     * Performs necessary checks, WebSocket spec handshake and then further authentication
     * @return void
     */
    private function handshake(): void
    {
        if ($this->handshake) { // Handshake was completed already
            return;
        }

        // Expecting complete buffer data
        if (!str_starts_with($this->buffer, "GET") || !str_ends_with($this->buffer, "\r\n\r\n")) {
            // Incomplete buffer data
            if ((time() - $this->connectedOn) >= $this->wsServer->timeOuts->handshakeWaitSecs) {
                // Terminate connection
                $this->terminate(408, "Handshake Timeout", [
                    "error" => sprintf('Handshake was not completed in %d seconds', $this->wsServer->timeOuts->handshakeWaitSecs)
                ]);
            }

            return; // Wait for more, or timeout
        }

        $request = $this->buffer;
        $this->buffer = ""; // Cleanup buffer

        // Parse request
        $headers = preg_split("(\r\n|\n|\r)", $request);
        $request = array_shift($headers);
        if (!preg_match("/^GET\s(\/(\w+[\-\w]*)?)+\sHTTP\/1.1$/", $request)) {
            $this->terminate(400, "Bad Request", ["error" => "Invalid request path/namespace"]);
            return;
        }

        // Parse Headers
        foreach ($headers as $header) {
            if (preg_match('/^([\w\-]+): (.*)$/', trim($header), $match)) {
                if ($this->headers->set($match[1], $match[2])) {
                    $this->terminate(400, "Bad Request",
                        ["error" => "Invalid header sent", "last" => $this->headers->last()?->name]);
                    return;
                }
            }
        }

        $websocketSecret = $this->headers->get("sec-websocket-key")?->value;
        if (!$websocketSecret) {
            $this->terminate(400, "Bad Request",
                ["error" => "Websocket secret key required", "header" => "sec-websocket-key"]);
            return;
        }

        // Path/Namespace Validation
        $namespace = strtolower("/" . trim(substr($request, 4, -9), "/"));
        if (is_array($this->wsServer->allowPaths)) {
            if (!in_array($namespace, $this->wsServer->allowPaths)) {
                $this->terminate(404, "Not Found", ["error" => "Requests to this path/namespace not available"]);
                return;
            }
        }

        $this->path = $namespace;
        try {
            if (!$this->wsServer->event_authorizeUser($this)) {
                throw new \RuntimeException("You do not have permission to access this resource");
            }
        } catch (\Throwable $t) {
            $this->terminate(401, "Unauthorized",
                ["exception" => get_class($t), "error" => $t->getMessage(), "code" => $t->getCode()]);
            return;
        }

        // Send response to waiting client
        $response[] = "HTTP/1.1 101 " . $this->wsServer->statusLine;
        /** @var \FurqanSiddiqui\WebSocket\Server\HeaderEntry $responseHeader */
        foreach ($this->wsServer->responseHeaders as $responseHeader) {
            $response[] = $responseHeader->name . ": " . $responseHeader->value;
        }

        $response[] = "Sec-WebSocket-Accept: " . base64_encode(hash("sha1", $websocketSecret . $this->wsServer->magicString, true));
        if (!@socket_write($this->socket, "\r\n" . implode("\r\n", $response) . "\r\n\r\n")) {
            $this->wsServer->logs->connectionLost($this->ip, $this->port);
            $this->closeSocket();
            return;
        }

        $this->handshake = true;
        $this->wsServer->logs->connectionAccepted($this);
        if ($this->wsServer->users->onAuthenticateCallback) {
            try {
                call_user_func_array($this->wsServer->users->onAuthenticateCallback, [$this]);
            } catch (\Throwable $t) {
                $this->disconnect($t->getMessage());
            }
        }
    }

    /**
     * @param string $message
     * @param bool $binary
     * @return bool
     * @throws \FurqanSiddiqui\WebSocket\Exception\MessageFramesException
     */
    public function broadcast(string $message, bool $binary = false): bool
    {
        return $this->send((new ServerMessage($binary ? Opcodes::BINARY : Opcodes::TEXT))->addFrame($message, true));
    }

    /**
     * @param \FurqanSiddiqui\WebSocket\Messages\ServerMessage $message
     * @return bool
     */
    public function send(ServerMessage $message): bool
    {
        if (!$this->socket) {
            return false;
        }

        try {
            if (!@socket_write($this->socket, $message->seal())) {
                throw new \RuntimeException('Could not write to socket');
            }

            return true;
        } catch (MessageFramesException $e) {
            $this->disconnect($e->getMessage(), $e->getCode());
        }

        return false;
    }

    /**
     * Disconnects client as in closes socket instance/resource and removes from UsersPool
     * @param string $message
     * @param int $code
     * @return void
     */
    public function disconnect(string $message = "No reason", int $code = 0): void
    {
        $this->pendingMessage = null;
        $this->buffer = "";
        $this->closeSocket();
        $this->wsServer->users->remove($this);
        $this->wsServer->logs->disconnect($this, $code, $message);
    }

    /**
     * Returns pointer to Socket resource/instance or NULL if connection was lost
     * @return \Socket|null
     */
    public function socket(): ?\Socket
    {
        return $this->socket;
    }

    /**
     * Internally terminates connection with client before handshake/authorization, silently sends response headers & body
     * @param int $httpStatusCode
     * @param string $message
     * @param string|array $body
     * @return void
     */
    private function terminate(int $httpStatusCode = 400, string $message = "Bad Request", string|array $body = ""): void
    {
        if (!$this->socket) {
            return;
        }

        $contentType = "text/plain";
        if (is_array($body)) {
            $contentType = "application/json";
            $body = json_encode($body);
        }

        $response = "\r\nHTTP/1.1 " . $httpStatusCode . " " . $message . "\r\n" .
            "Content-Type: " . $contentType . "\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "Connection: close\r\n" .
            "\r\n" . $body;

        @socket_write($this->socket, $response);
        $this->closeSocket();

        $this->wsServer->logs->connectionTerminated($this->ip, $this->port, $httpStatusCode,
            is_array($body) && isset($body["error"]) ? $body["error"] : $message);
    }

    /**
     * Silently closes socket connection and unsets internal pointer to socket resource/instance
     * @return void
     */
    private function closeSocket(): void
    {
        if ($this->socket) {
            @socket_shutdown($this->socket, 2);
            @socket_close($this->socket);
            $this->socket = null;
        }
    }
}
