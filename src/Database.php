<?php

declare(strict_types=1);

namespace BeeBank;

final class Database
{
    public static function connect(?string $path = null): \PDO
    {
        $path ??= getenv('BEEBANK_DB') ?: \dirname(__DIR__).'/var/beebank.sqlite';

        $pdo = new \PDO('sqlite:'.$path, options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        if (':memory:' !== $path) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        self::migrate($pdo);

        return $pdo;
    }

    public static function reset(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS operations; DROP TABLE IF EXISTS hives; DROP TABLE IF EXISTS settings');
        self::migrate($pdo);
    }

    private static function migrate(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS hives (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                queen TEXT NOT NULL,
                status TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS operations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                from_hive_id INTEGER REFERENCES hives(id),
                to_hive_id INTEGER REFERENCES hives(id),
                grams INTEGER NOT NULL CHECK (grams > 0),
                memo TEXT,
                created_at TEXT NOT NULL
            );
            SQL);
    }
}
