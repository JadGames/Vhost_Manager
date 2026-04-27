<?php

declare(strict_types=1);

namespace App\Services;

final class Logger
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARN', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
