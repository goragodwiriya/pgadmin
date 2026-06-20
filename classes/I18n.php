<?php

declare(strict_types=1);

class I18n
{
    private const SESSION_KEY = 'locale';
    private static array $strings = [];

    public static function init(): void
    {
        $allowed = config('available_locales', ['th', 'en']);
        $requested = $_GET['lang'] ?? null;

        if (is_string($requested) && in_array($requested, $allowed, true)) {
            $_SESSION[self::SESSION_KEY] = $requested;
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = config('default_locale', 'th');
        }
    }

    public static function current(): string
    {
        return $_SESSION[self::SESSION_KEY] ?? config('default_locale', 'th');
    }

    public static function translate(string $key, array $replace = []): string
    {
        static $loadedLocale = null;
        $current = self::current();

        if ($loadedLocale !== $current) {
            self::$strings = [];
            $loadedLocale = $current;
        }

        if (empty(self::$strings)) {
            $file = __DIR__ . '/../locale/' . $current . '.php';
            self::$strings = is_file($file) ? require $file : [];
        }

        $text = self::$strings[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }
}
