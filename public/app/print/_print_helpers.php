<?php

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/EventListService.php';

/**
 * @return int[]
 */
function print_parse_category_ids(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $ids = array_map(static fn (string $id): int => (int) $id, explode(',', $raw));
    $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

    return array_values(array_unique($ids));
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<int,array<string,mixed>>
 */
function print_group_by_category(array $events): array
{
    $grouped = [];
    foreach ($events as $event) {
        $catId = (int) ($event['category_id'] ?? 0);
        $grouped[$catId][] = $event;
    }

    return $grouped;
}

/**
 * @param int[] $categoryIds
 * @return array<int,array<string,string>>
 */
function print_load_categories(PDO $pdo, array $categoryIds): array
{
    if (empty($categoryIds)) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($categoryIds as $idx => $id) {
        $key = 'cat' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $sql = 'SELECT id, name, color FROM categories WHERE id IN (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int) $row['id']] = [
            'name' => $row['name'],
            'color' => $row['color'],
        ];
    }

    return $map;
}

function print_truncate(string $text, int $limit = 220): string
{
    $clean = trim(strip_tags($text));
    if (mb_strlen($clean) <= $limit) {
        return $clean;
    }

    return mb_substr($clean, 0, $limit - 1) . '…';
}

function print_format_date(string $value): string
{
    $dt = Util::parseDateTime($value);
    return $dt ? $dt->format('d.m.Y') : $value;
}

function print_format_time(string $start, ?string $end, int $allDay): string
{
    if ($allDay === 1) {
        return 'Ganztägig';
    }

    $startDt = Util::parseDateTime($start);
    $endDt = Util::parseDateTime($end ?? $start);

    $startStr = $startDt ? $startDt->format('H:i') : '';
    $endStr = $endDt ? $endDt->format('H:i') : '';

    if ($startStr === $endStr || $endStr === '') {
        return $startStr;
    }

    return $startStr . '–' . $endStr;
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<string,mixed>
 */
function print_prepare_data(array $events, PDO $pdo, array $config): array
{
    $grouped = print_group_by_category($events);
    $catIds = array_keys($grouped);

    $categories = print_load_categories($pdo, $catIds);

    return [
        'config' => $config,
        'categories' => $categories,
        'groupedEvents' => $grouped,
    ];
}

/**
 * @param array<string,mixed> $event
 */
function print_render_event_row(array $event): string
{
    $date = print_format_date((string) $event['start_at']);
    $time = print_format_time((string) $event['start_at'], $event['end_at'] ?? null, (int) $event['all_day']);
    $title = htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8');
    $location = htmlspecialchars((string) ($event['location_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(print_truncate((string) ($event['description'] ?? '')), ENT_QUOTES, 'UTF-8');

    $url = $event['location_url'] ?? '';
    $link = '';
    if (is_string($url) && Util::validateUrl($url)) {
        $host = parse_url($url, PHP_URL_HOST) ?: 'Link';
        $link = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars((string) $host, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return '<tr>'
        . '<td>' . $date . '</td>'
        . '<td>' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . $title . '</td>'
        . '<td>' . $location . '</td>'
        . '<td>' . $link . '</td>'
        . '<td class="description">' . $description . '</td>'
        . '</tr>';
}

/**
 * @param array<int,array<string,mixed>> $groupedEvents
 * @param array<int,array<string,string>> $categories
 */
function print_render_sections(array $groupedEvents, array $categories): string
{
    if (empty($groupedEvents)) {
        return '<p class="muted">Keine Termine im ausgewählten Zeitraum.</p>';
    }

    $html = '';
    $categoryOrder = array_keys($groupedEvents);
    usort($categoryOrder, static function (int $a, int $b) use ($categories): int {
        $nameA = $categories[$a]['name'] ?? ('Kategorie ' . $a);
        $nameB = $categories[$b]['name'] ?? ('Kategorie ' . $b);

        $cmp = strcasecmp($nameA, $nameB);
        if ($cmp !== 0) {
            return $cmp;
        }
        return $a <=> $b;
    });

    foreach ($categoryOrder as $catId) {
        $events = $groupedEvents[$catId];
        $category = $categories[(int) $catId] ?? ['name' => 'Kategorie ' . $catId, 'color' => '#e0e7ff'];
        $headerStyle = 'background:' . htmlspecialchars($category['color'], ENT_QUOTES, 'UTF-8');
        $sectionClass = 'category-section' . (count($events) > 10 ? ' is-long' : '');

        $html .= '<section class="' . $sectionClass . '">';
        $html .= '<div class="category-header" style="' . $headerStyle . '">'
            . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8')
            . '</div>';
        $html .= '<table class="event-table">';
        $html .= '<thead><tr>'
            . '<th>Datum</th>'
            . '<th>Zeit</th>'
            . '<th>Titel</th>'
            . '<th>Ort</th>'
            . '<th>Kurzlink</th>'
            . '<th>Beschreibung</th>'
            . '</tr></thead><tbody>';

        foreach ($events as $evt) {
            $html .= print_render_event_row($evt);
        }

        $html .= '</tbody></table></section>';
    }

    return $html;
}
