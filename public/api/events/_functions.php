<?php
/**
 * Hilfsfunktionen für Event-APIs.
 *
 * @return array<string,mixed>
 */
function events_load_payload(): array
{
    $payload = $_POST;
    if (!empty($payload)) {
        return $payload;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    return [];
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function events_format_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'category_id' => (int) $row['category_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'location_text' => $row['location_text'],
        'location_url' => $row['location_url'],
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'] ?? $row['start_at'],
        'all_day' => (int) $row['all_day'],
        'color' => $row['color'] ?? null,
        'source' => $row['source'] ?? 'manual',
    ];
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function events_audit_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'category_id' => (int) $row['category_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'location_text' => $row['location_text'],
        'location_url' => $row['location_url'],
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'],
        'all_day' => (int) $row['all_day'],
        'visibility' => $row['visibility'] ?? null,
        'source' => $row['source'] ?? null,
        'external_id' => $row['external_id'] ?? null,
        'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
        'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        'is_deleted' => isset($row['is_deleted']) ? (int) $row['is_deleted'] : 0,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}
