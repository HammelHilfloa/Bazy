<?php

namespace App\Models;

class Appointment extends Model
{
    protected string $table = 'appointments';

    public function forCoach(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM appointments WHERE coach_id = :coach ORDER BY start_at');
        $stmt->execute(['coach' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO appointments (coach_id, client_name, start_at, end_at, status, notes, created_at, updated_at) VALUES (:coach_id, :client_name, :start_at, :end_at, :status, :notes, NOW(), NOW())');
        $stmt->execute([
            'coach_id' => $data['coach_id'],
            'client_name' => $data['client_name'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'status' => $data['status'] ?? 'available',
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE appointments SET client_name = :client_name, start_at = :start_at, end_at = :end_at, status = :status, notes = :notes, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'client_name' => $data['client_name'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'status' => $data['status'],
            'notes' => $data['notes'],
            'id' => $id,
        ]);
    }
}
