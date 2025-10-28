<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CsrfMiddleware;
use App\Models\Appointment;
use App\Support\Request;
use App\Support\View;

class CalendarController
{
    private Appointment $appointments;

    public function __construct()
    {
        $this->appointments = new Appointment();
    }

    public function index(Request $request): string
    {
        $userId = $_SESSION['user_id'] ?? null;
        $appointments = $userId ? $this->appointments->forCoach($userId) : [];

        return View::make('calendar/index', [
            'title' => 'Kalender',
            'appointments' => $appointments,
            'csrf' => CsrfMiddleware::token(),
        ]);
    }

    public function store(Request $request): string
    {
        $data = [
            'coach_id' => $_SESSION['user_id'],
            'client_name' => $request->input('client_name'),
            'start_at' => $request->input('start_at'),
            'end_at' => $request->input('end_at'),
            'status' => $request->input('status', 'available'),
            'notes' => $request->input('notes'),
        ];

        $this->appointments->create($data);
        header('Location: /calendar');
        exit;
    }

    public function update(Request $request, int $id): string
    {
        $appointment = $this->appointments->find($id);
        if (!$appointment || (int) $appointment['coach_id'] !== (int) $_SESSION['user_id']) {
            http_response_code(403);
            return 'Appointment not accessible';
        }

        $this->appointments->update($id, [
            'client_name' => $request->input('client_name'),
            'start_at' => $request->input('start_at'),
            'end_at' => $request->input('end_at'),
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
        ]);

        header('Location: /calendar');
        exit;
    }

    public function destroy(Request $request, int $id): string
    {
        $appointment = $this->appointments->find($id);
        if (!$appointment || (int) $appointment['coach_id'] !== (int) $_SESSION['user_id']) {
            http_response_code(403);
            return 'Appointment not accessible';
        }

        $this->appointments->delete($id);
        header('Location: /calendar');
        exit;
    }
}
