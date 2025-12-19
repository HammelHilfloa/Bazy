<?php
require_once __DIR__ . '/../events/_functions.php';

function series_load_payload(): array
{
    return events_load_payload();
}

/**
 * @param array<string,mixed> $series
 * @param array<string,mixed> $template
 * @return array<string,mixed>
 */
function series_format(array $series, array $template): array
{
    return [
        'id' => (int) $series['id'],
        'template_event_id' => (int) $series['template_event_id'],
        'template_event' => events_format_row($template),
        'rrule' => $series['rrule'],
        'series_timezone' => $series['series_timezone'],
        'skip_if_holiday' => (int) $series['skip_if_holiday'],
        'is_active' => (int) $series['is_active'],
    ];
}

/**
 * @param array<string,mixed> $series
 * @return array<string,mixed>
 */
function series_audit_payload(array $series): array
{
    return [
        'id' => isset($series['id']) ? (int) $series['id'] : null,
        'template_event_id' => isset($series['template_event_id']) ? (int) $series['template_event_id'] : null,
        'rrule' => $series['rrule'] ?? null,
        'series_timezone' => $series['series_timezone'] ?? null,
        'skip_if_holiday' => isset($series['skip_if_holiday']) ? (int) $series['skip_if_holiday'] : null,
        'is_active' => isset($series['is_active']) ? (int) $series['is_active'] : null,
        'created_by' => isset($series['created_by']) ? (int) $series['created_by'] : null,
        'updated_by' => isset($series['updated_by']) ? (int) $series['updated_by'] : null,
        'created_at' => $series['created_at'] ?? null,
        'updated_at' => $series['updated_at'] ?? null,
    ];
}
