<?php

require_once __DIR__ . '/Util.php';

class SeriesException extends InvalidArgumentException
{
}

class Series
{
    private const WEEKDAY_MAP = [
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
        'SU' => 7,
    ];

    /**
     * @return array{freq:string,interval:int,byday:int[],until:?DateTimeImmutable,count:?int}
     */
    public static function parseRrule(string $rrule, string $timezone): array
    {
        $parts = array_filter(array_map('trim', explode(';', strtoupper($rrule))));
        $rules = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key === null || $value === null || $key === '') {
                throw new SeriesException('Ungültige RRULE-Syntax.');
            }
            $rules[$key] = $value;
        }

        $freq = $rules['FREQ'] ?? null;
        if ($freq !== 'WEEKLY') {
            throw new SeriesException('Unterstützte FREQ-Werte: WEEKLY.');
        }

        $interval = isset($rules['INTERVAL']) ? (int) $rules['INTERVAL'] : 1;
        if ($interval <= 0) {
            throw new SeriesException('INTERVAL muss eine positive Zahl sein.');
        }

        $bydayRaw = isset($rules['BYDAY']) ? explode(',', $rules['BYDAY']) : [];
        $byday = [];
        foreach ($bydayRaw as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            if (!isset(self::WEEKDAY_MAP[$token])) {
                throw new SeriesException('BYDAY enthält einen ungültigen Wochentag.');
            }
            $byday[] = self::WEEKDAY_MAP[$token];
        }
        $byday = array_values(array_unique($byday));

        $until = isset($rules['UNTIL']) ? self::parseUntil($rules['UNTIL'], $timezone) : null;

        $count = null;
        if (isset($rules['COUNT'])) {
            $count = (int) $rules['COUNT'];
            if ($count <= 0) {
                throw new SeriesException('COUNT muss größer als 0 sein.');
            }
        }

        return [
            'freq' => $freq,
            'interval' => $interval,
            'byday' => $byday,
            'until' => $until,
            'count' => $count,
        ];
    }

    private static function parseUntil(string $value, string $timezone): ?DateTimeImmutable
    {
        $tz = new DateTimeZone($timezone);
        $formats = [
            'Ymd\THis\Z',
            'Ymd\THis',
            'Ymd',
        ];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($dt instanceof DateTimeImmutable) {
                if ($format === 'Ymd') {
                    $dt = $dt->setTime(23, 59, 59);
                }
                return $dt;
            }
        }

        throw new SeriesException('UNTIL konnte nicht geparst werden.');
    }

    /**
     * Baut einen schnellen Lookup für Feiertage/Ferien-Daten innerhalb eines Zeitraums.
     *
     * @return array<string,bool> Key: YYYY-MM-DD
     */
    public static function buildHolidayIndex(PDO $pdo, string $from, string $to): array
    {
        $sql = 'SELECT e.start_at, COALESCE(e.end_at, e.start_at) AS end_at
                FROM events e
                INNER JOIN categories c ON c.id = e.category_id
                WHERE e.is_deleted = 0
                  AND e.source = "openholidays"
                  AND c.name IN ("Ferien", "Feiertage")
                  AND e.start_at <= :to
                  AND COALESCE(e.end_at, e.start_at) >= :from';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['from' => $from, 'to' => $to]);

        $index = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $start = Util::parseDateTime($row['start_at']);
            $end = Util::parseDateTime($row['end_at']);
            if (!$start || !$end) {
                continue;
            }

            $cursor = $start->setTime(0, 0, 0);
            $endDay = $end->setTime(0, 0, 0);

            while ($cursor <= $endDay) {
                $index[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $index;
    }

    /**
     * Generiert Serien-Vorkommen im angegebenen Zeitraum.
     *
     * @param array<string,mixed> $seriesRow Muss keys `id`, `rrule`, `series_timezone`, `skip_if_holiday`, `template_event`
     * @param array<string,array<string,mixed>> $overrides Key = occurrence_start (Y-m-d H:i:s)
     * @return array<int,array<string,mixed>>
     */
    public static function generateOccurrences(array $seriesRow, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, array $overrides, array $holidayIndex): array
    {
        if (empty($seriesRow['template_event']) || !is_array($seriesRow['template_event'])) {
            return [];
        }

        $template = $seriesRow['template_event'];
        $tzName = $seriesRow['series_timezone'] ?: 'Europe/Berlin';
        $tz = new DateTimeZone($tzName);

        $templateStart = Util::parseDateTime($template['start_at'], $tzName);
        $templateEnd = Util::parseDateTime($template['end_at'] ?? $template['start_at'], $tzName) ?: $templateStart;

        if (!$templateStart || !$templateEnd) {
            return [];
        }

        $rule = self::parseRrule((string) $seriesRow['rrule'], $tzName);
        $byday = $rule['byday'];
        if (empty($byday)) {
            $byday = [(int) $templateStart->format('N')];
        }

        $firstWeekStart = $templateStart->setTime(0, 0, 0)->modify('monday this week');
        $durationSeconds = max(0, $templateEnd->getTimestamp() - $templateStart->getTimestamp());
        $occurrences = [];
        $generated = 0;
        $week = 0;

        $rangeToTs = $rangeEnd->getTimestamp();
        $rangeFromTs = $rangeStart->getTimestamp();

        while (true) {
            $weekStart = $firstWeekStart->modify('+' . ($week * $rule['interval']) . ' weeks');
            $weekStop = false;

            foreach ($byday as $weekday) {
                $candidateStart = $weekStart->modify('+' . ($weekday - 1) . ' days')
                    ->setTime((int) $templateStart->format('H'), (int) $templateStart->format('i'), (int) $templateStart->format('s'));

                if ($week === 0 && $candidateStart < $templateStart) {
                    continue;
                }

                if ($rule['until'] && $candidateStart > $rule['until']) {
                    $weekStop = true;
                    break;
                }

                $generated++;
                if ($rule['count'] && $generated > $rule['count']) {
                    $weekStop = true;
                    break;
                }

                $candidateEnd = $candidateStart->modify('+' . $durationSeconds . ' seconds');
                if ($candidateStart->getTimestamp() > $rangeToTs || $candidateEnd->getTimestamp() < $rangeFromTs) {
                    continue;
                }

                $occKey = $candidateStart->format('Y-m-d H:i:s');

                if ((int) $seriesRow['skip_if_holiday'] === 1) {
                    $occDate = $candidateStart->format('Y-m-d');
                    if (isset($holidayIndex[$occDate])) {
                        continue;
                    }
                }

                if (isset($overrides[$occKey])) {
                    $override = $overrides[$occKey];
                    if (($override['override_type'] ?? '') === 'cancelled') {
                        continue;
                    }

                    if (($override['override_type'] ?? '') === 'modified' && !empty($override['event']) && is_array($override['event'])) {
                        $event = $override['event'];

                        $overrideStart = Util::parseDateTime($event['start_at'] ?? $occKey, $tzName);
                        $overrideEnd = Util::parseDateTime($event['end_at'] ?? ($event['start_at'] ?? $occKey), $tzName) ?: $overrideStart;

                        if ($overrideStart && $overrideEnd) {
                            if ($overrideStart->getTimestamp() > $rangeToTs || $overrideEnd->getTimestamp() < $rangeFromTs) {
                                continue;
                            }
                        }

                        $event['series_id'] = (int) $seriesRow['id'];
                        $event['occurrence_start'] = $occKey;
                        $event['override_type'] = 'modified';
                        $occurrences[] = $event;
                        continue;
                    }
                }

                $event = $template;
                $event['start_at'] = $candidateStart->format('Y-m-d H:i:s');
                $event['end_at'] = $candidateEnd->format('Y-m-d H:i:s');
                $event['series_id'] = (int) $seriesRow['id'];
                $event['occurrence_start'] = $occKey;
                $occurrences[] = $event;
            }

            if ($weekStop) {
                break;
            }

            $week++;
            if ($weekStart->getTimestamp() > $rangeToTs) {
                break;
            }
        }

        return $occurrences;
    }
}
