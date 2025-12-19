<?php
// JSON-basiertes Kalender-Backend für die Standalone-Ansicht
// Speichert alle Daten in public/standalone/data/calendar.json

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const DATA_FILE = __DIR__ . '/../data/calendar.json';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    match ($method) {
        'GET' => respond(loadCalendar()),
        'POST' => handleUpsert(),
        'DELETE' => handleDelete(),
        default => respondError(405, 'Methode nicht erlaubt'),
    };
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Standalone calendar API error: ' . $e->getMessage());
    echo json_encode(['error' => 'Interner Fehler: Kalender konnte nicht verarbeitet werden']);
}

function loadCalendar(): array
{
    if (!file_exists(DATA_FILE)) {
        $seed = defaultCalendar();
        saveCalendar($seed);
        return $seed;
    }

    $raw = file_get_contents(DATA_FILE);
    $data = json_decode($raw ?: 'null', true);
    if (!is_array($data)) {
        throw new RuntimeException('Kalender JSON ist ungültig oder leer');
    }

    $data['schemaVersion'] = 2;
    if (!isset($data['year'])) {
        $data['year'] = (int) date('Y');
    }

    // Sicherheitshalber IDs ergänzen
    $data['events'] = array_map(static function (array $event): array {
        if (!isset($event['id']) || $event['id'] === '') {
            $event['id'] = generateId();
        }
        return $event;
    }, $data['events'] ?? []);

    return $data;
}

function saveCalendar(array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Kalender konnte nicht in JSON konvertiert werden');
    }

    $written = file_put_contents(DATA_FILE, $json, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Kalender-Datei konnte nicht gespeichert werden');
    }
}

function handleUpsert(): void
{
    $payload = json_decode(file_get_contents('php://input') ?: 'null', true);
    if (!is_array($payload) || !isset($payload['event']) || !is_array($payload['event'])) {
        respondError(400, 'Es wurde kein gültiges Event gesendet');
    }

    $calendar = loadCalendar();
    $event = normalizeEvent($payload['event']);

    if (!in_array($event['group'], $calendar['groups'], true)) {
        $calendar['groups'][] = $event['group'];
    }

    $calendar['events'] = upsertEvent($calendar['events'], $event);
    $calendar['updatedAt'] = nowIso();

    saveCalendar($calendar);
    respond(['calendar' => $calendar, 'event' => $event]);
}

function handleDelete(): void
{
    $payload = json_decode(file_get_contents('php://input') ?: 'null', true);
    $id = $_GET['id'] ?? ($payload['id'] ?? null);
    if (!$id) {
        respondError(400, 'Event-ID fehlt');
    }

    $calendar = loadCalendar();
    $before = count($calendar['events']);
    $calendar['events'] = array_values(array_filter(
        $calendar['events'],
        static fn(array $event): bool => ($event['id'] ?? null) !== $id
    ));

    $deleted = count($calendar['events']) < $before;
    if ($deleted) {
        $calendar['updatedAt'] = nowIso();
        saveCalendar($calendar);
    }

    respond(['calendar' => $calendar, 'deleted' => $deleted]);
}

function normalizeEvent(array $event): array
{
    $title = trim((string) ($event['title'] ?? ''));
    $group = trim((string) ($event['group'] ?? ''));
    $date = trim((string) ($event['date'] ?? ''));
    $notes = trim((string) ($event['notes'] ?? ''));
    $color = trim((string) ($event['color'] ?? ''));

    if ($title === '' || $group === '' || $date === '') {
        respondError(400, 'Titel, Gruppe und Datum sind erforderlich');
    }

    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
        respondError(400, 'Datum muss im Format JJJJ-MM-TT vorliegen');
    }

    if ($color !== '' && !preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) {
        respondError(400, 'Farbe muss ein Hex-Wert sein (z. B. #2563eb)');
    }

    $normalizedColor = $color === '' ? null : (str_starts_with($color, '#') ? $color : '#' . $color);

    return [
        'id' => isset($event['id']) && $event['id'] !== '' ? (string) $event['id'] : generateId(),
        'title' => $title,
        'group' => $group,
        'date' => $date,
        'notes' => $notes,
        'color' => $normalizedColor ?? '',
    ];
}

function upsertEvent(array $events, array $event): array
{
    $filtered = array_values(array_filter(
        $events,
        static fn(array $existing): bool => ($existing['id'] ?? '') !== $event['id']
    ));

    $filtered[] = $event;

    usort($filtered, static function (array $a, array $b): int {
        return strcmp($a['date'] ?? '', $b['date'] ?? '');
    });

    return $filtered;
}

function nowIso(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

function respond($payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respondError(int $status, string $message): void
{
    respond(['error' => $message], $status);
}

function generateId(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable) {
        return uniqid('ev_', true);
    }
}

function defaultCalendar(): array
{
    return [
        'schemaVersion' => 2,
        'year' => 2026,
        'createdAt' => nowIso(),
        'source' => 'judo_kalender_2026-05.pdf',
        'groups' => [
            'Ferien / Feiertage',
            'U 11 / U 13',
            'U 15',
            'U 18',
            'U 21',
            'Frauen',
            'Männer',
            'Judo spielend lernen',
            'Trainingsfokus Kinder',
            'Trainingsfokus Erwachsene',
        ],
        'events' => [],
    ];
}
