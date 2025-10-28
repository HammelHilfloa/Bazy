<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;

return [
    ['GET', '/', [[AuthMiddleware::class, 'optional'], [CsrfMiddleware::class, 'generate']], [CalendarController::class, 'index']],
    ['GET', '/login', [[CsrfMiddleware::class, 'generate']], [AuthController::class, 'showLoginForm']],
    ['POST', '/login', [[CsrfMiddleware::class, 'verify']], [AuthController::class, 'login']],
    ['POST', '/logout', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [AuthController::class, 'logout']],
    ['GET', '/calendar', [[AuthMiddleware::class, 'handle'], [CsrfMiddleware::class, 'generate']], [CalendarController::class, 'index']],
    ['POST', '/appointments', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [CalendarController::class, 'store']],
    ['POST', '/appointments/{id}/update', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [CalendarController::class, 'update']],
    ['POST', '/appointments/{id}/delete', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [CalendarController::class, 'destroy']],
    ['GET', '/offers', [[AuthMiddleware::class, 'handle'], [CsrfMiddleware::class, 'generate']], [OfferController::class, 'index']],
    ['GET', '/offers/create', [[AuthMiddleware::class, 'handle'], [CsrfMiddleware::class, 'generate']], [OfferController::class, 'create']],
    ['POST', '/offers', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [OfferController::class, 'store']],
    ['POST', '/offers/{id}/send', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [OfferController::class, 'send']],
    ['GET', '/invoices', [[AuthMiddleware::class, 'handle'], [CsrfMiddleware::class, 'generate']], [InvoiceController::class, 'index']],
    ['GET', '/invoices/create', [[AuthMiddleware::class, 'handle'], [CsrfMiddleware::class, 'generate']], [InvoiceController::class, 'create']],
    ['POST', '/invoices', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [InvoiceController::class, 'store']],
    ['POST', '/invoices/{id}/send', [[CsrfMiddleware::class, 'verify'], [AuthMiddleware::class, 'handle']], [InvoiceController::class, 'send']],
];
