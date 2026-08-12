<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Invoice;
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

        $payment->loadMissing(['invoice', 'student', 'establishment', 'receivedBy']);

        $tuitionInvoices = Invoice::where('student_id', $payment->student_id)
            ->where('school_year_id', $payment->invoice->school_year_id)
            ->whereNotNull('installment_id')
            ->where('status', '!=', 'cancelled');

        $totalTuition = (float) (clone $tuitionInvoices)->sum('amount_due');
        $totalPayments = (float) (clone $tuitionInvoices)->sum('amount_paid');

        $nextPaymentDueDate = (clone $tuitionInvoices)
            ->where('id', '!=', $payment->invoice_id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->first()
            ?->due_date;

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'totalTuition' => $totalTuition,
            'totalPayments' => $totalPayments,
            'nextPaymentDueDate' => $nextPaymentDueDate,
        ])->setPaper('a5');

        $filename = Str::slug("recu-{$payment->receiptNumber()}-{$payment->student->last_name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
