<?php

declare(strict_types=1);

namespace App\Livewire\Billing\TuitionFees;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tarifs')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public bool $showInstallmentForm = false;

    public ?int $editingInstallmentId = null;

    public string $label = '';

    public string $due_date = '';

    public ?int $position = null;

    public bool $showLevelFeeForm = false;

    public ?int $configuringLevelId = null;

    public bool $configuringLevelIsSecondaire = false;

    public ?float $registration_amount = null;

    public ?float $registration_amount_assigned = null;

    /**
     * Valeurs brutes des champs wire:model — un input vidé par l'utilisateur
     * transmet '' (pas null), d'où le type élargi par rapport à ce qui est
     * effectivement stocké en base.
     *
     * @var array<int, float|string|null>
     */
    public array $installment_amounts = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Installment::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    public function updatedSchoolYearId(): void
    {
        $this->cancelInstallment();
        $this->cancelLevelFee();
    }

    public function createInstallment(): void
    {
        $this->authorize('create', Installment::class);

        $this->resetInstallmentForm();
        $this->position = 1 + (int) Installment::where('school_year_id', $this->school_year_id)->max('position');
        $this->showInstallmentForm = true;
    }

    public function editInstallment(int $installmentId): void
    {
        $installment = Installment::findOrFail($installmentId);

        $this->authorize('update', $installment);

        $this->editingInstallmentId = $installment->id;
        $this->label = $installment->label;
        $this->due_date = $installment->due_date->toDateString();
        $this->position = $installment->position;
        $this->showInstallmentForm = true;
    }

    public function saveInstallment(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'position' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('installments', 'position')
                    ->where(fn ($query) => $query
                        ->where('establishment_id', app('currentEstablishmentId'))
                        ->where('school_year_id', $this->school_year_id)
                        ->whereNull('deleted_at'))
                    ->ignore($this->editingInstallmentId),
            ],
        ]);

        if ($this->editingInstallmentId) {
            $installment = Installment::findOrFail($this->editingInstallmentId);
            $this->authorize('update', $installment);
        } else {
            $this->authorize('create', Installment::class);
            $installment = new Installment(['school_year_id' => $this->school_year_id]);
        }

        $installment->fill($data);
        $installment->save();

        $this->resetInstallmentForm();
        $this->showInstallmentForm = false;
    }

    public function deleteInstallment(int $installmentId): void
    {
        $installment = Installment::findOrFail($installmentId);

        $this->authorize('delete', $installment);

        $installment->delete();
    }

    public function cancelInstallment(): void
    {
        $this->resetInstallmentForm();
        $this->showInstallmentForm = false;
    }

    public function configureLevel(int $levelId): void
    {
        $levelFee = LevelFee::withTrashed()
            ->where('school_year_id', $this->school_year_id)
            ->where('level_id', $levelId)
            ->first();

        $this->authorize($levelFee ? 'update' : 'create', $levelFee ?? LevelFee::class);

        $this->configuringLevelId = $levelId;
        $this->configuringLevelIsSecondaire = Level::find($levelId)?->cycle === Cycle::Secondaire;
        $this->registration_amount = $levelFee ? (float) $levelFee->registration_amount : 0.0;
        $this->registration_amount_assigned = $levelFee?->registration_amount_assigned !== null
            ? (float) $levelFee->registration_amount_assigned
            : null;

        $this->installment_amounts = Installment::where('school_year_id', $this->school_year_id)
            ->orderBy('position')
            ->get()
            ->mapWithKeys(function (Installment $installment) use ($levelFee) {
                $amount = $levelFee?->installmentAmounts->firstWhere('installment_id', $installment->id);

                return [$installment->id => $amount ? (float) $amount->amount : null];
            })
            ->all();

        $this->showLevelFeeForm = true;
    }

    public function saveLevelFees(): void
    {
        $data = $this->validate([
            'registration_amount' => ['required', 'numeric', 'min:0'],
            'registration_amount_assigned' => ['nullable', 'numeric', 'min:0'],
            'installment_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $existing = LevelFee::withTrashed()
            ->where('school_year_id', $this->school_year_id)
            ->where('level_id', $this->configuringLevelId)
            ->first();

        $this->authorize($existing ? 'update' : 'create', $existing ?? LevelFee::class);

        $attributes = [
            'registration_amount' => $data['registration_amount'],
            'registration_amount_assigned' => $this->configuringLevelIsSecondaire
                ? ($data['registration_amount_assigned'] ?? null)
                : null,
        ];

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update($attributes);
            $levelFee = $existing;
        } else {
            $levelFee = LevelFee::create($attributes + [
                'school_year_id' => $this->school_year_id,
                'level_id' => $this->configuringLevelId,
            ]);
        }

        foreach ($this->installment_amounts as $installmentId => $amount) {
            if ($amount === null || $amount === '') {
                $levelFee->installmentAmounts()->where('installment_id', $installmentId)->delete();

                continue;
            }

            $levelFee->installmentAmounts()->updateOrCreate(
                ['installment_id' => $installmentId],
                ['amount' => $amount],
            );
        }

        $this->cancelLevelFee();
    }

    public function cancelLevelFee(): void
    {
        $this->showLevelFeeForm = false;
        $this->configuringLevelId = null;
        $this->configuringLevelIsSecondaire = false;
        $this->registration_amount = null;
        $this->registration_amount_assigned = null;
        $this->installment_amounts = [];
    }

    public function generateInvoices(int $levelId): void
    {
        $this->authorize('create', Invoice::class);

        $levelFee = LevelFee::where('school_year_id', $this->school_year_id)
            ->where('level_id', $levelId)
            ->with('installmentAmounts.installment')
            ->first();

        if (! $levelFee) {
            return;
        }

        $enrollments = Enrollment::where('school_year_id', $this->school_year_id)
            ->where('status', 'active')
            ->whereHas('classroom', fn ($query) => $query->where('level_id', $levelId))
            ->get();

        $assignedEnrollments = $enrollments->where('is_assigned', true);
        $notAssignedEnrollments = $enrollments->where('is_assigned', false);

        $studentsWithRegistrationInvoice = Invoice::where('school_year_id', $this->school_year_id)
            ->whereNull('installment_id')
            ->pluck('student_id');

        $this->createRegistrationInvoices($notAssignedEnrollments, (float) $levelFee->registration_amount, $studentsWithRegistrationInvoice);
        $this->createRegistrationInvoices($assignedEnrollments, (float) ($levelFee->registration_amount_assigned ?? 0), $studentsWithRegistrationInvoice);

        $totalTuition = (float) $levelFee->installmentAmounts->whereNotNull('amount')->sum('amount');

        $discountsByStudent = Discount::where('school_year_id', $this->school_year_id)->get()->keyBy('student_id');

        foreach ($levelFee->installmentAmounts->whereNotNull('amount') as $installmentAmount) {
            $installment = $installmentAmount->installment;

            $studentsWithInstallmentInvoice = Invoice::where('installment_id', $installment->id)
                ->pluck('student_id');

            foreach ($notAssignedEnrollments as $enrollment) {
                if ($studentsWithInstallmentInvoice->contains($enrollment->student_id)) {
                    continue;
                }

                Invoice::create([
                    'student_id' => $enrollment->student_id,
                    'school_year_id' => $this->school_year_id,
                    'installment_id' => $installment->id,
                    'label' => $installment->label,
                    'amount_due' => $this->applyDiscount(
                        (float) $installmentAmount->amount,
                        $totalTuition,
                        $discountsByStudent->get($enrollment->student_id),
                    ),
                    'due_date' => $installment->due_date,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }
        }

        // Correction : un élève désormais affecté ne doit plus avoir de
        // facture de tranche de ce niveau — sauf celles déjà (même
        // partiellement) payées, jamais touchées.
        if ($assignedEnrollments->isNotEmpty()) {
            Invoice::whereIn('student_id', $assignedEnrollments->pluck('student_id'))
                ->where('school_year_id', $this->school_year_id)
                ->whereIn('installment_id', $levelFee->installmentAmounts->pluck('installment_id'))
                ->where('amount_paid', 0)
                ->delete();
        }
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, int>  $studentsAlreadyInvoiced
     */
    private function createRegistrationInvoices(Collection $enrollments, float $registrationAmount, Collection $studentsAlreadyInvoiced): void
    {
        if ($registrationAmount <= 0) {
            return;
        }

        foreach ($enrollments as $enrollment) {
            if ($studentsAlreadyInvoiced->contains($enrollment->student_id)) {
                continue;
            }

            Invoice::create([
                'student_id' => $enrollment->student_id,
                'school_year_id' => $this->school_year_id,
                'installment_id' => null,
                'label' => "Frais d'inscription",
                'amount_due' => $registrationAmount,
                'due_date' => $enrollment->enrolled_on,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function applyDiscount(float $amount, float $totalTuition, ?Discount $discount): float
    {
        if (! $discount) {
            return $amount;
        }

        if ($discount->type === 'percentage') {
            return round($amount * (1 - ((float) $discount->value / 100)), 2);
        }

        if ($totalTuition <= 0) {
            return $amount;
        }

        $reduction = (float) $discount->value * $amount / $totalTuition;

        return round(max(0.0, $amount - $reduction), 2);
    }

    protected function resetInstallmentForm(): void
    {
        $this->reset(['editingInstallmentId', 'label', 'due_date', 'position']);
    }

    public function render()
    {
        $installments = $this->school_year_id
            ? Installment::where('school_year_id', $this->school_year_id)->orderBy('position')->get()
            : collect();

        $levelFees = $this->school_year_id
            ? LevelFee::where('school_year_id', $this->school_year_id)->with('installmentAmounts')->get()->keyBy('level_id')
            : collect();

        $allowedCycles = Establishment::findOrFail((int) app('currentEstablishmentId'))->allowedCycles();

        return view('livewire.billing.tuition-fees.index', [
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'installments' => $installments,
            'levels' => Level::whereIn('cycle', $allowedCycles)->orderBy('level_wording')->get(),
            'levelFees' => $levelFees,
        ]);
    }
}
