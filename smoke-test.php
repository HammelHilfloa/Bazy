<?php
$base = 'http://localhost:8000';
$cacheFile = __DIR__ . '/cache/holidays_2026.json';

function httpGet(string $url): string
{
    $response = @file_get_contents($url);
    if ($response === false) {
        throw new RuntimeException("GET fehlgeschlagen: {$url}");
    }
    return $response;
}

function httpPost(string $url, array $headers, string $body): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException("POST fehlgeschlagen: {$url}");
    }
    return $response;
}

try {
    $csrf = json_decode(httpGet("{$base}/api/csrf.php"), true)['token'] ?? null;
    if (!$csrf) {
        throw new RuntimeException('CSRF Token fehlt');
    }

    $events = json_decode(httpGet("{$base}/api/events.php"), true);
    if (!is_array($events)) {
        throw new RuntimeException('Events Antwort ungültig');
    }

    $backupFilesBefore = glob(__DIR__ . '/data/backups/events_2026_*.json');

    $testEvents = $events;
    $testEvents[] = [
        'id' => 'evt-smoke-test',
        'title' => 'Smoke-Test-Event',
        'description' => 'Wird nach Testlauf wieder entfernt.',
        'startDate' => '2026-12-01',
        'endDate' => '2026-12-01',
        'category' => 'Senioren',
        'color' => '#0F4C81',
    ];

    httpPost(
        "{$base}/api/events.php",
        [
            'Content-Type: application/json',
            "X-CSRF-Token: {$csrf}",
        ],
        json_encode($testEvents)
    );

    $backupFilesAfter = glob(__DIR__ . '/data/backups/events_2026_*.json');
    $backupCreated = count($backupFilesAfter) > count($backupFilesBefore);

    // Restore original events
    httpPost(
        "{$base}/api/events.php",
        [
            'Content-Type: application/json',
            "X-CSRF-Token: {$csrf}",
        ],
        json_encode($events)
    );

    // Holiday cache test
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    httpGet("{$base}/api/holidays.php");
    $cacheCreated = file_exists($cacheFile);
    $mtime1 = $cacheCreated ? filemtime($cacheFile) : null;
    sleep(1);
    httpGet("{$base}/api/holidays.php");
    $mtime2 = $cacheCreated ? filemtime($cacheFile) : null;
    $cacheUsed = $cacheCreated && $mtime1 === $mtime2;

    echo "Smoke-Test erfolgreich. Ergebnisse:\n";
    echo "- GET events: OK\n";
    echo "- POST events & Backup: " . ($backupCreated ? 'OK' : 'Kein Backup erkannt') . "\n";
    echo "- Holidays Cache: " . ($cacheCreated ? 'angelegt' : 'nicht vorhanden') . ", aus Cache geliefert: " . ($cacheUsed ? 'ja' : 'nein') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Smoke-Test fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}
