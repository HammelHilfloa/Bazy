<?php

namespace App\Models;

class Offer extends Model
{
    protected string $table = 'offers';

    public function forCoach(int $coachId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM offers WHERE coach_id = :coach ORDER BY created_at DESC');
        $stmt->execute(['coach' => $coachId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO offers (coach_id, client_email, subject, body, status, sent_at, created_at, updated_at) VALUES (:coach_id, :client_email, :subject, :body, :status, :sent_at, NOW(), NOW())');
        $stmt->execute([
            'coach_id' => $data['coach_id'],
            'client_email' => $data['client_email'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => $data['status'] ?? 'draft',
            'sent_at' => $data['sent_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE offers SET status = :status, sent_at = CASE WHEN :status = "sent" THEN NOW() ELSE sent_at END, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $id,
        ]);
    }
}
