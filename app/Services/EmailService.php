<?php

namespace App\Services;

class EmailService
{
    public function send(string $to, string $subject, string $body, ?string $attachment = null): void
    {
        $logEntry = sprintf("To: %s\nSubject: %s\nAttachment: %s\nBody:\n%s\n----\n", $to, $subject, $attachment ?? 'none', $body);
        file_put_contents(__DIR__ . '/../../storage/emails/' . date('Ymd_His') . '_' . md5($to . $subject) . '.log', $logEntry);
    }
}
