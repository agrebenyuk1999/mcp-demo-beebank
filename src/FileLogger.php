<?php

declare(strict_types=1);

namespace BeeBank;

use Psr\Log\AbstractLogger;

final class FileLogger extends AbstractLogger
{
    public function __construct(private readonly string $path)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ('debug' === $level && !getenv('MCP_DEBUG')) {
            return;
        }

        $exception = $context['exception'] ?? null;
        unset($context['exception']);
        if ($exception instanceof \Throwable) {
            $context['error'] = $exception::class.': '.$exception->getMessage().' @ '.basename($exception->getFile()).':'.$exception->getLine();
        }
        $line = \sprintf("[%s] %s %s %s\n", date('H:i:s'), strtoupper((string) $level), $message, $context ? json_encode($context, \JSON_UNESCAPED_UNICODE) : '');
        file_put_contents($this->path, $line, \FILE_APPEND);
    }
}
