<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CsrfMiddleware;
use App\Models\Offer;
use App\Support\Request;
use App\Support\View;
use App\Services\EmailService;

class OfferController
{
    private Offer $offers;
    private EmailService $mailer;

    public function __construct()
    {
        $this->offers = new Offer();
        $this->mailer = new EmailService();
    }

    public function index(Request $request): string
    {
        $coachId = (int) $_SESSION['user_id'];
        return View::make('offers/index', [
            'title' => 'Angebote',
            'offers' => $this->offers->forCoach($coachId),
            'csrf' => CsrfMiddleware::token(),
        ]);
    }

    public function create(Request $request): string
    {
        return View::make('offers/create', [
            'title' => 'Neues Angebot',
            'csrf' => CsrfMiddleware::token(),
        ]);
    }

    public function store(Request $request): string
    {
        $this->offers->create([
            'coach_id' => $_SESSION['user_id'],
            'client_email' => $request->input('client_email'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
        ]);

        header('Location: /offers');
        exit;
    }

    public function send(Request $request, int $id): string
    {
        $offer = $this->offers->find($id);
        if (!$offer || (int) $offer['coach_id'] !== (int) $_SESSION['user_id']) {
            http_response_code(403);
            return 'Offer not accessible';
        }

        $this->mailer->send($offer['client_email'], $offer['subject'], $offer['body']);
        $this->offers->updateStatus($id, 'sent');

        header('Location: /offers');
        exit;
    }
}
