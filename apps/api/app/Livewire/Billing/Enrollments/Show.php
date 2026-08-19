<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Enrollments;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Situation financière')]
class Show extends Component
{
    public Enrollment $enrollment;

    public bool $showAmountsForm = false;

    public ?float $registration_amount = null;

    /**
     * @var array<int, float|string|null>
     */
    public array $installment_amounts = [];

    public bool $showPaymentForm = false;

    public ?float $amount = null;

    public string $method = 'cash';

    public string $paid_at = '';

    public string $reference = '';

    public function mount(Enrollment $enrollment): void
    {
        $this->authorize('viewAny', Payment::class);

        $this->enrollment = $enrollment->load(['student', 'classroom.level', 'schoolYear']);
    }

    public function editAmounts(): void
    {
        $this->authorize('create', Payment::class);

        $this->registration_amount = (float) ($this->enrollment->registration_amount ?? 0);

        $this->installment_amounts = [];
        foreach (range(1, 7) as $position) {
            $amount = $this->enrollment->{"installment_{$position}_amount"};
            $this->installment_amounts[$position] = $amount !== null ? (float) $amount : null;
        }

        $this->showPaymentForm = false;
        $this->showAmountsForm = true;
    }

    public function saveAmounts(): void
    {
        $this->authorize('create', Payment::class);

        $data = $this->validate([
            'registration_amount' => ['required', 'numeric', 'min:0'],
            'installment_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->enrollment->registration_amount = $data['registration_amount'];

        foreach ($this->installment_amounts as $position => $amount) {
            $this->enrollment->{"installment_{$position}_amount"} = $amount === '' || $amount === null ? null : $amount;
        }

        $this->enrollment->save();

        $this->showAmountsForm = false;
    }

    public function cancelAmounts(): void
    {
        $this->showAmountsForm = false;
    }

    public function addPayment(): void
    {
        $this->authorize('create', Payment::class);

        $this->amount = null;
        $this->method = 'cash';
        $this->paid_at = now()->toDateString();
        $this->reference = '';
        $this->showAmountsForm = false;
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

        $paymentService->recordPayment($this->enrollment, [
            'amount' => $data['amount'],
            'method' => $data['method'],
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'] !== '' ? $data['reference'] : null,
        ], $user);

        $this->enrollment->refresh();
        $this->showPaymentForm = false;
    }

    public function cancelPayment(): void
    {
        $this->showPaymentForm = false;
    }

    public function render()
    {
        $registrationAmount = (float) ($this->enrollment->registration_amount ?? 0);
        $tuitionInstallments = $this->enrollment->tuitionInstallmentsWithStatus();
        $totalDue = $registrationAmount + (float) $tuitionInstallments->sum('amount');

        return view('livewire.billing.enrollments.show', [
            'payments' => $this->enrollment->payments()->with('receivedBy')->latest('paid_at')->get(),
            'tuitionInstallments' => $tuitionInstallments,
            'registrationAmount' => $registrationAmount,
            'registrationAmountPaid' => $this->enrollment->registrationAmountPaid(),
            'totalDue' => $totalDue,
            'balance' => $totalDue - (float) $this->enrollment->total_paid,
        ]);
    }
}
