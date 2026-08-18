<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Le reçu PDF n'est jamais pré-généré ni stocké : il est rendu à la volée à
 * chaque consultation (affichage inline par défaut, téléchargement sur
 * demande via ?download=1) — même principe que les bulletins. La situation
 * financière affichée est l'instantané figé sur le paiement lui-même (voir
 * PaymentService::recordPayment()), pas un calcul en direct.
 */
class PaymentReceiptPdfController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->loadMissing(['enrollment.classroom', 'student', 'establishment', 'receivedBy']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
        ])->setPaper('a5');

        $filename = Str::slug("recu-{$payment->receiptNumber()}-{$payment->student->last_name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
