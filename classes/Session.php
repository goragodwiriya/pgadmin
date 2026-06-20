<?php

declare(strict_types=1);

class Session
{
    private const KEY = 'pg_connection';
    private const ACTIVITY_KEY = 'last_activity';

    public static function isActive(): bool
    {
        if (!isset($_SESSION[self::KEY])) {
            return false;
        }

        $timeout = (int) config('session_timeout', 1800);
        if ($timeout <= 0) {
            return true;
        }

        $last = (int) ($_SESSION[self::ACTIVITY_KEY] ?? 0);
        if ($last > 0 && (time() - $last) > $timeout) {
            self::destroy();
            return false;
        }

        return true;
    }

    public static function touchActivity(): void
    {
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    public static function setConnection(Database $db): void
    {
        $_SESSION[self::KEY] = [
            'host' => $db->getHost(),
            'port' => $db->getPort(),
            'username' => $db->getUsername(),
            'password' => self::getStoredPassword(),
            'database' => $db->getDatabase(),
        ];
    }

    public static function setCredentials(array $credentials): void
    {
        $_SESSION[self::KEY] = $credentials;
    }

    public static function getCredentials(): ?array
    {
        return $_SESSION[self::KEY] ?? null;
    }

    public static function getConnection(): ?Database
    {
        $creds = self::getCredentials();
        if ($creds === null) {
            return null;
        }

        return new Database(
            $creds['host'],
            (int) $creds['port'],
            $creds['username'],
            $creds['password'] ?? '',
            $creds['database'] ?? 'postgres'
        );
    }

    public static function setPassword(string $password): void
    {
        $_SESSION['pg_password'] = $password;
        if (isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY]['password'] = $password;
        }
    }

    private static function getStoredPassword(): string
    {
        return $_SESSION['pg_password'] ?? $_SESSION[self::KEY]['password'] ?? '';
    }

    public static function setCurrentDatabase(string $database): void
    {
        if (isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY]['database'] = $database;
        }
    }

    public static function getCurrentDatabase(): string
    {
        return $_SESSION[self::KEY]['database'] ?? 'postgres';
    }

    public static function destroy(): void
    {
        unset(
            $_SESSION[self::KEY],
            $_SESSION['pg_password'],
            $_SESSION[self::ACTIVITY_KEY],
            $_SESSION['last_sql'],
            $_SESSION['sql_result'],
            $_SESSION['query_history']
        );
    }
}
