<?php

declare(strict_types=1);

class RateLimiter
{
    private static function storageDir(): string
    {
        $dir = sys_get_temp_dir() . '/pg_manager_rate_limit';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    private static function key(string $scope, string $ip): string
    {
        return hash('sha256', $scope . '|' . $ip);
    }

    private static function filePath(string $scope, string $ip): string
    {
        return self::storageDir() . '/' . self::key($scope, $ip) . '.json';
    }

    /** @return array{attempts: list<int>, blocked_until: int} */
    private static function read(string $scope, string $ip): array
    {
        $path = self::filePath($scope, $ip);
        if (!is_file($path)) {
            return ['attempts' => [], 'blocked_until' => 0];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return ['attempts' => [], 'blocked_until' => 0];
        }

        return [
            'attempts' => array_values(array_filter($data['attempts'] ?? [], 'is_int')),
            'blocked_until' => (int) ($data['blocked_until'] ?? 0),
        ];
    }

    private static function write(string $scope, string $ip, array $data): void
    {
        file_put_contents(
            self::filePath($scope, $ip),
            json_encode($data),
            LOCK_EX
        );
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $value = trim(explode(',', $value)[0]);
            }
            return $value;
        }
        return 'unknown';
    }

    public static function isBlocked(string $scope, int $maxAttempts, int $windowSeconds): bool
    {
        $data = self::read($scope, self::clientIp());
        $now = time();

        if ($data['blocked_until'] > $now) {
            return true;
        }

        $attempts = array_values(array_filter(
            $data['attempts'],
            static fn(int $ts): bool => ($now - $ts) < $windowSeconds
        ));

        return count($attempts) >= $maxAttempts;
    }

    public static function secondsUntilUnblock(string $scope): int
    {
        $data = self::read($scope, self::clientIp());
        return max(0, $data['blocked_until'] - time());
    }

    public static function recordFailure(string $scope, int $maxAttempts, int $windowSeconds, int $blockSeconds): void
    {
        $ip = self::clientIp();
        $data = self::read($scope, $ip);
        $now = time();

        $attempts = array_values(array_filter(
            $data['attempts'],
            static fn(int $ts): bool => ($now - $ts) < $windowSeconds
        ));
        $attempts[] = $now;

        $blockedUntil = $data['blocked_until'];
        if (count($attempts) >= $maxAttempts) {
            $blockedUntil = $now + $blockSeconds;
            $attempts = [];
        }

        self::write($scope, $ip, [
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
        ]);
    }

    public static function clear(string $scope): void
    {
        $path = self::filePath($scope, self::clientIp());
        if (is_file($path)) {
            unlink($path);
        }
    }
}
