<?php

declare(strict_types=1);

class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME = '_csrf_token';

    public static function init(): void
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            self::regenerate();
        }
    }

    public static function token(): string
    {
        self::init();
        return $_SESSION[self::SESSION_KEY];
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    public static function verify(): bool
    {
        $submitted = $_POST[self::FIELD_NAME] ?? '';
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals(self::token(), $submitted);
    }

    public static function verifyOrFail(string $redirectUrl): void
    {
        if (!self::verify()) {
            flash('error', __('error.csrf'));
            redirect($redirectUrl);
        }
    }

    public static function regenerate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
