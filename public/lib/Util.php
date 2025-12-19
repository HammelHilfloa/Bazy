<?php
class Util
{
    public static function sanitize(?string $value): string
    {
        return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function validateUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function parseDateTime(string $value, ?string $timezone = null): ?DateTimeImmutable
    {
        $tz = $timezone ? new DateTimeZone($timezone) : null;
        try {
            $date = new DateTimeImmutable($value, $tz);
            return $date;
        } catch (Exception) {
            return null;
        }
    }

    public static function formatDateTime(?DateTimeInterface $dateTime, string $format = 'Y-m-d H:i'): ?string
    {
        return $dateTime ? $dateTime->format($format) : null;
    }

    public static function formatDate(?DateTimeInterface $dateTime, string $format = 'Y-m-d'): ?string
    {
        return $dateTime ? $dateTime->format($format) : null;
    }

    public static function formatTime(?DateTimeInterface $dateTime, string $format = 'H:i'): ?string
    {
        return $dateTime ? $dateTime->format($format) : null;
    }
}
