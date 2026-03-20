<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use PDO;

final class SqliteConnection
{
    private static ?PDO $instance = null;

    public static function create(string $databasePath): PDO
    {
        $pdo = new PDO(
            dsn: 'sqlite:' . $databasePath,
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        return $pdo;
    }

    public static function shared(string $databasePath): PDO
    {
        return self::$instance ??= self::create($databasePath);
    }

    public static function migrate(PDO $pdo, string $schemaPath): void
    {
        $sql = file_get_contents($schemaPath);

        if ($sql === false) {
            throw new \RuntimeException(sprintf('Cannot read schema file: %s', $schemaPath));
        }

        $pdo->exec($sql);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}