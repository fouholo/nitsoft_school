<?php

declare(strict_types=1);

namespace App\Livewire\Billing\TuitionFees;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Establishments\Models\Establishment;
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
