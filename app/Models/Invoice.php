<?php

namespace App\Models;

class Invoice extends Model
{
    protected string $table = 'invoices';

    public function forCoach(int $coachId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE coach_id = :coach ORDER BY created_at DESC');
        $stmt->execute(['coach' => $coachId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO invoices (coach_id, client_email, amount, due_date, status, pdf_path, sent_at, created_at, updated_at) VALUES (:coach_id, :client_email, :amount, :due_date, :status, :pdf_path, :sent_at, NOW(), NOW())');
        $stmt->execute([
            'coach_id' => $data['coach_id'],
            'client_email' => $data['client_email'],
            'amount' => $data['amount'],
            'due_date' => $data['due_date'],
            'status' => $data['status'] ?? 'draft',
            'pdf_path' => $data['pdf_path'] ?? null,
            'sent_at' => $data['sent_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markSent(int $id, string $pdfPath): void
    {
        $stmt = $this->db->prepare('UPDATE invoices SET status = "sent", pdf_path = :pdf, sent_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'pdf' => $pdfPath,
            'id' => $id,
        ]);
    }
}
