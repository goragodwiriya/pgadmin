<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "✓ {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "✗ {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

test('isReadOnlySql allows SELECT', function (): void {
    if (!isReadOnlySql('SELECT * FROM users')) {
        throw new RuntimeException('expected true');
    }
});

test('isReadOnlySql blocks DROP', function (): void {
    if (isReadOnlySql('DROP TABLE users')) {
        throw new RuntimeException('expected false');
    }
});

test('isReadOnlySql allows EXPLAIN', function (): void {
    if (!isReadOnlySql('EXPLAIN SELECT 1')) {
        throw new RuntimeException('expected true');
    }
});

test('Csrf token generation', function (): void {
    $_SESSION = [];
    Csrf::init();
    $token = Csrf::token();
    if (strlen($token) !== 64) {
        throw new RuntimeException('invalid token length');
    }
    $_POST[Csrf::fieldName()] = $token;
    if (!Csrf::verify()) {
        throw new RuntimeException('verify failed');
    }
});

test('Locale translate EN', function (): void {
    $_SESSION['locale'] = 'en';
    I18n::init();
    $text = __('nav.dashboard');
    if ($text !== 'Dashboard') {
        throw new RuntimeException("got {$text}");
    }
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
