<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CsrfMiddleware;
use App\Models\Invoice;
use App\Support\Request;
use App\Support\View;
use App\Services\EmailService;
use App\Services\PdfService;

class InvoiceController
{
    private Invoice $invoices;
    private PdfService $pdf;
    private EmailService $mailer;

    public function __construct()
    {
        $this->invoices = new Invoice();
        $this->pdf = new PdfService();
        $this->mailer = new EmailService();
    }

    public function index(Request $request): string
    {
        $coachId = (int) $_SESSION['user_id'];
        return View::make('invoices/index', [
            'title' => 'Rechnungen',
            'invoices' => $this->invoices->forCoach($coachId),
            'csrf' => CsrfMiddleware::token(),
        ]);
    }

    public function create(Request $request): string
    {
        return View::make('invoices/create', [
            'title' => 'Neue Rechnung',
            'csrf' => CsrfMiddleware::token(),
        ]);
    }

    public function store(Request $request): string
    {
        $this->invoices->create([
            'coach_id' => $_SESSION['user_id'],
            'client_email' => $request->input('client_email'),
            'amount' => $request->input('amount'),
            'due_date' => $request->input('due_date'),
        ]);

        header('Location: /invoices');
        exit;
    }

    public function send(Request $request, int $id): string
    {
        $invoice = $this->invoices->find($id);
        if (!$invoice || (int) $invoice['coach_id'] !== (int) $_SESSION['user_id']) {
            http_response_code(403);
            return 'Invoice not accessible';
        }

        $pdfPath = $this->pdf->generate('invoice_' . $id . '.pdf', sprintf("Rechnung #%d\nBetrag: %s\nFällig am: %s", $id, $invoice['amount'], $invoice['due_date']));
        $this->mailer->send($invoice['client_email'], 'Ihre Rechnung', 'Bitte finden Sie die Rechnung im Anhang.', $pdfPath);
        $this->invoices->markSent($id, $pdfPath);

        header('Location: /invoices');
        exit;
    }
}
