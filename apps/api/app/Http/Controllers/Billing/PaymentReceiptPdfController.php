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
 * demande via ?download=1) — même principe que les bulletins.
 */
class PaymentReceiptPdfController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->loadMissing(['invoice', 'student', 'establishment', 'receivedBy', 'receipt']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
        ])->setPaper('a5');

        $filename = Str::slug("recu-{$payment->receipt?->receipt_number}-{$payment->student->last_name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
