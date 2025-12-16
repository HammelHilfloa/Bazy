<?php
$base = 'http://localhost:8000';
$cacheFile = __DIR__ . '/cache/holidays_2026_DE-NW.json';

function parseStatusCode(array $headers): int
{
    foreach ($headers as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function extractSessionCookie(array $headers): ?string
{
    foreach ($headers as $line) {
        if (stripos($line, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^;]+)/i', $line, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Perform an HTTP request and return [body, statusCode, headers].
 */
function httpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null, ?string $cookie = null): array
{
    $httpHeaders = $headers;
    if ($cookie) {
        $httpHeaders[] = "Cookie: {$cookie}";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $httpHeaders),
            'content' => $body ?? '',
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $headersOut = $http_response_header ?? [];
    $status = parseStatusCode($headersOut);

    if ($response === false) {
        throw new RuntimeException("Request fehlgeschlagen: {$url} (HTTP {$status})");
    }

    return [$response, $status, $headersOut];
}

try {
    [$csrfBody, $csrfStatus, $csrfHeaders] = httpRequest("{$base}/api/csrf.php");
    $csrf = json_decode($csrfBody, true)['token'] ?? null;
    if ($csrfStatus !== 200 || !$csrf) {
        throw new RuntimeException('CSRF Token fehlt');
    }

    $sessionCookie = extractSessionCookie($csrfHeaders);
    [$eventsBody, $eventsStatus] = httpRequest("{$base}/api/events.php", 'GET', [], null, $sessionCookie);
    $events = json_decode($eventsBody, true);
    if ($eventsStatus !== 200 || !is_array($events)) {
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

    [$postBody, $postStatus] = httpRequest(
        "{$base}/api/events.php",
        'POST',
        [
            'Content-Type: application/json',
            "X-CSRF-Token: {$csrf}",
        ],
        json_encode($testEvents),
        $sessionCookie
    );
    if ($postStatus !== 200) {
        throw new RuntimeException('POST fehlgeschlagen: Events konnten nicht gespeichert werden');
    }

    $backupFilesAfter = glob(__DIR__ . '/data/backups/events_2026_*.json');
    $backupCreated = count($backupFilesAfter) > count($backupFilesBefore);

    // Restore original events
    httpRequest(
        "{$base}/api/events.php",
        'POST',
        [
            'Content-Type: application/json',
            "X-CSRF-Token: {$csrf}",
        ],
        json_encode($events),
        $sessionCookie
    );

    // Holiday cache test
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    httpRequest("{$base}/api/holidays.php", 'GET', [], null, $sessionCookie);
    $cacheCreated = file_exists($cacheFile);
    $mtime1 = $cacheCreated ? filemtime($cacheFile) : null;
    sleep(1);
    httpRequest("{$base}/api/holidays.php", 'GET', [], null, $sessionCookie);
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
