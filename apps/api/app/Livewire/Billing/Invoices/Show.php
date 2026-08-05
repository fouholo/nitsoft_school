<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Invoices;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Facture')]
class Show extends Component
{
    public Invoice $invoice;

    public bool $showPaymentForm = false;

    public ?float $amount = null;

    public string $method = 'cash';

    public string $paid_at = '';

    public string $reference = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);

        $this->invoice = $invoice;
    }

    public function addPayment(): void
    {
        $this->authorize('create', Payment::class);

        $this->amount = round((float) $this->invoice->amount_due - (float) $this->invoice->amount_paid, 2);
        $this->method = 'cash';
        $this->paid_at = now()->toDateString();
        $this->reference = '';
        $this->showPaymentForm = true;
    }

    public function savePayment(PaymentService $paymentService): void
    {
        $this->authorize('create', Payment::class);

        $data = $this->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,mobile_money,bank_transfer,card'],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $paymentService->recordPayment($this->invoice, [
            'amount' => $data['amount'],
            'method' => $data['method'],
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'] !== '' ? $data['reference'] : null,
        ], $user);

        $this->invoice->refresh();
        $this->showPaymentForm = false;
    }

    public function cancelPayment(): void
    {
        $this->showPaymentForm = false;
    }

    public function render()
    {
        return view('livewire.billing.invoices.show', [
            'payments' => $this->invoice->payments()->with(['receipt', 'receivedBy'])->latest('paid_at')->get(),
        ]);
    }
}
