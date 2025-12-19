<?php

class Logger
{
    private const LOG_FILE = __DIR__ . '/../logs/app.log';

    /**
     * @param array<string,mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            '%s [%s] %s',
            date('Y-m-d H:i:s'),
            $level,
            $message
        );

        if (!empty($context)) {
            $normalized = self::normalizeContext($context);
            $line .= ' | ' . json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents(self::LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (is_object($value)) {
                $normalized[$key] = get_class($value);
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalizeContext($value);
            } elseif (is_scalar($value) || $value === null) {
                $normalized[$key] = $value;
            } else {
                $normalized[$key] = 'unserializable';
            }
        }

        return $normalized;
    }
}
