<?php

declare(strict_types=1);

namespace BeeBank;

/**
 * Журнал усього, що ходить через /mcp: JSON Lines у var/mcp-requests.log, читає bin/mcp-watch.
 * Кожен HTTP-запит дає запис "request" одразу, "sse" на кожну подію потоку і "response" наприкінці,
 * тож запит, що чекає на підтвердження людини, видно ще до того, як він завершився.
 */
final class TrafficLog
{
    private readonly string $rid;
    private readonly float $startedAt;
    private string $sseBuffer = '';

    public function __construct(private readonly string $path)
    {
        $this->rid = substr(bin2hex(random_bytes(3)), 0, 4);
        $this->startedAt = microtime(true);
    }

    public function request(string $httpMethod, ?string $session, ?string $client, string $body): void
    {
        $this->write('request', [
            'http' => $httpMethod,
            'session' => $session,
            'client' => $client,
            'body' => self::decode($body),
        ]);
    }

    public function chunk(string $chunk): void
    {
        $this->sseBuffer .= $chunk;
        while (false !== $end = strpos($this->sseBuffer, "\n\n")) {
            $event = substr($this->sseBuffer, 0, $end);
            $this->sseBuffer = substr($this->sseBuffer, $end + 2);
            foreach (explode("\n", $event) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $this->write('sse', ['body' => self::decode(trim(substr($line, 5)))]);
                }
            }
        }
    }

    public function response(int $status, ?string $session, string $body): void
    {
        $this->write('response', [
            'status' => $status,
            'session' => $session,
            'ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
            'body' => str_starts_with(ltrim($body), 'event:') || str_starts_with(ltrim($body), 'data:') ? '(SSE)' : self::decode($body),
        ]);
    }

    private function write(string $kind, array $data): void
    {
        $line = json_encode(['kind' => $kind, 'rid' => $this->rid, 'ts' => date('H:i:s')] + $data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        file_put_contents($this->path, $line."\n", \FILE_APPEND | \LOCK_EX);
    }

    private static function decode(string $body): mixed
    {
        return '' === $body ? null : (json_decode($body) ?? $body);
    }
}
