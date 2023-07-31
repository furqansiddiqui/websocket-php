<?php
declare(strict_types=1);

namespace FurqanSiddiqui\WebSocket\Logger;

use FurqanSiddiqui\WebSocket\Server\User;

/**
 * Class DefaultFileLogger
 * @package FurqanSiddiqui\WebSocket\Logger
 */
class DefaultLogger implements LoggerInterface
{
    public readonly ?string $logFilePath;
    private readonly mixed $filePointer;

    /**
     * @param bool $useANSIColors
     * @param string|null $logFilePath
     * @param string $eolChar
     * @param bool $displayTime
     */
    public function __construct(
        public readonly bool   $useANSIColors = false,
        ?string                $logFilePath = null,
        public readonly string $eolChar = PHP_EOL,
        public readonly bool   $displayTime = true,
    )
    {
        if ($logFilePath) {
            if (!@is_file($logFilePath) || !@is_writable($logFilePath)) {
                throw new \RuntimeException('Path to log file does not exist or is not writable');
            }

            $fp = @fopen($logFilePath, "w");
            if (!$fp) {
                throw new \RuntimeException('Could not open log file for writing');
            }

            $this->logFilePath = $logFilePath;
            $this->filePointer = $fp;
            register_shutdown_function(function () use ($fp) {
                if ($fp) {
                    fclose($fp);
                }
            });
        } else {
            $this->logFilePath = null;
            $this->filePointer = null;
        }
    }

    /**
     * @param string $ip
     * @param int $port
     * @return string
     */
    private function normaliseIpPort(string $ip, int $port): string
    {
        return "{cyan}$ip{/}{yellow}@{/}{cyan}$port{/}";
    }

    /**
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @return string
     */
    private function normaliseUser(User $user): string
    {
        return sprintf("{cyan}%s{/}{yellow}:{/}{cyan}%s{/} {green}*{/}", $user->ip, $user->port);
    }

    /**
     * @param string $ip
     * @param int $port
     * @return void
     */
    public function connectionReceived(string $ip, int $port): void
    {
        $this->write("New connection received from " . $this->normaliseIpPort($ip, $port));
    }

    /**
     * @param string $ip
     * @param int $port
     * @param int $code
     * @return void
     */
    public function connectionLost(string $ip, int $port, int $code): void
    {
        $this->write(sprintf("Connection from %s was {red}dropped{/} {grey}(#{yellow}%d{/}{grey})",
            $this->normaliseIpPort($ip, $port), $code));
    }

    /**
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @return void
     */
    public function connectionAccepted(User $user): void
    {
        $this->write(sprintf(
            "Connection from %s {green}accepted{/} to path {cyan}%s{/}",
            $this->normaliseIpPort($user->ip, $user->port),
            $user->path
        ));
    }

    /**
     * @param string $ip
     * @param int $port
     * @param int $code
     * @param string|null $message
     * @return void
     */
    public function connectionTerminated(string $ip, int $port, int $code, ?string $message = null): void
    {
        $message = $message ? " {grey}({yellow}$message{/}{grey}){/}" : null;
        $errorMsg = sprintf(
            "Connection from %s is {red}TERMINATED{/} with error code {red}%d{/}%s",
            $this->normaliseIpPort($ip, $port),
            $code,
            $message
        );

        $this->write($errorMsg);
    }

    /**
     * @param \FurqanSiddiqui\WebSocket\Server\User $user
     * @param int $code
     * @param string $message
     * @return void
     */
    public function disconnect(User $user, int $code, string $message): void
    {
        $this->write(sprintf(
            "Connection from %s {red}disconnected{/} with code {yellow}%d{/} {grey}({yellow}%s{/}{grey}){/}",
            $this->normaliseUser($user),
            $code,
            $message
        ));
    }

    /**
     * @param string $log
     * @return void
     */
    private function write(string $log): void
    {
        if ($this->displayTime) {
            $log = "{grey}[" . date("d/m/y H:i:s") . "]:{/} " . $log;
        }

        $prepared = $this->ansiEscapeSeq($log);
        $prepared .= $this->eolChar;
        if ($this->filePointer) {
            if (!@fwrite($this->filePointer, $prepared)) {
                throw new \RuntimeException(sprintf('Could not write %d bytes to log file', strlen($prepared)));
            }

            return;
        }

        print $prepared;
    }

    /**
     * @param string $prepare
     * @param bool $reset
     * @return string
     */
    private function ansiEscapeSeq(string $prepare, bool $reset = true): string
    {
        $useColors = $this->useANSIColors;
        $prepared = preg_replace_callback(
            '/{([a-z]+|\/)}/i',
            function ($modifier) use ($useColors) {
                if (!$useColors) {
                    return "";
                }

                return match (strtolower($modifier[1] ?? "")) {
                    "red" => "\e[31m",
                    "green" => "\e[32m",
                    "yellow" => "\e[33m",
                    "blue" => "\e[34m",
                    "magenta" => "\e[35m",
                    "gray", "grey" => "\e[90m",
                    "cyan" => "\e[36m",
                    "b", "bold" => "\e[1m",
                    "u", "underline" => "\e[4m",
                    "blink" => "\e[5m",
                    "invert" => "\e[7m",
                    "reset", "/" => "\e[0m",
                    default => $modifier[0] ?? "",
                };
            },
            $prepare
        );

        if ($reset) {
            $prepared .= "\e[0m";
        }

        return $prepared;
    }
}