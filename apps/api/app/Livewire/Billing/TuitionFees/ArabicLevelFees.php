<?php

declare(strict_types=1);

namespace App\Livewire\Billing\TuitionFees;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\Installment;
use App\Domain\Establishments\Models\Establishment;
use Livewire\Component;

/**
 * Onglet « Niveaux arabes » de l'écran Tarifs (école franco-arabe) : même
 * saisie que les tarifs par niveau français (inscription + tranches,
 * duplication depuis un autre niveau), sans montant « affecté ». Appliqué à
 * l'élève dont la classe principale est arabe — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicLevelFees extends Component
{
    public ?int $schoolYearId = null;

    public bool $showForm = false;

    public ?int $configuringLevelId = null;

    public ?float $registration_amount = null;

    /**
     * Valeurs brutes des champs wire:model ('' quand un champ est vidé).
     *
     * @var array<int, float|string|null>
     */
    public array $installment_amounts = [];

    public bool $justSaved = false;

    public ?int $lastSavedLevelId = null;

    public ?int $duplicateSourceLevelId = null;

    public function mount(?int $schoolYearId): void
    {
        $this->authorize('viewAny', ArabicLevelFee::class);

        $this->schoolYearId = $schoolYearId;
    }

    public function configureLevel(int $arabicLevelId): void
    {
        $arabicLevelFee = $this->existingFee($arabicLevelId);

        $this->authorize($arabicLevelFee ? 'update' : 'create', $arabicLevelFee ?? ArabicLevelFee::class);

        $this->justSaved = false;
        $this->duplicateSourceLevelId = null;
        $this->configuringLevelId = $arabicLevelId;
        $this->registration_amount = $arabicLevelFee ? (float) $arabicLevelFee->registration_amount : 0.0;
        $this->installment_amounts = $this->amountsFrom($arabicLevelFee);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'registration_amount' => ['required', 'numeric', 'min:0'],
            'installment_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'registration_amount' => __("frais d'inscription"),
        ]);

        $existing = $this->existingFee((int) $this->configuringLevelId);

        $this->authorize($existing ? 'update' : 'create', $existing ?? ArabicLevelFee::class);

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update(['registration_amount' => $data['registration_amount']]);
            $arabicLevelFee = $existing;
        } else {
            $arabicLevelFee = ArabicLevelFee::create([
                'school_year_id' => $this->schoolYearId,
                'arabic_level_id' => $this->configuringLevelId,
                'registration_amount' => $data['registration_amount'],
            ]);
        }

        foreach ($this->installment_amounts as $installmentId => $amount) {
            if ($amount === null || $amount === '') {
                $arabicLevelFee->installmentAmounts()->where('installment_id', $installmentId)->delete();

                continue;
            }

            $arabicLevelFee->installmentAmounts()->updateOrCreate(
                ['installment_id' => $installmentId],
                ['amount' => $amount],
            );
        }

        $savedLevelId = $this->configuringLevelId;

        $this->cancel();

        $this->justSaved = true;
        $this->lastSavedLevelId = $savedLevelId;
    }

    public function updatedDuplicateSourceLevelId(): void
    {
        if ($this->duplicateSourceLevelId === null || ! $this->configuringLevelId) {
            return;
        }

        $sourceLevelId = $this->duplicateSourceLevelId;
        $this->duplicateSourceLevelId = null;

        $existing = $this->existingFee($this->configuringLevelId);

        $this->authorize($existing ? 'update' : 'create', $existing ?? ArabicLevelFee::class);

        $source = ArabicLevelFee::where('school_year_id', $this->schoolYearId)
            ->where('arabic_level_id', $sourceLevelId)
            ->with('installmentAmounts')
            ->first();

        if (! $source) {
            return;
        }

        $this->registration_amount = (float) $source->registration_amount;
        $this->installment_amounts = $this->amountsFrom($source);
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->showForm = false;
        $this->configuringLevelId = null;
        $this->registration_amount = null;
        $this->installment_amounts = [];
        $this->duplicateSourceLevelId = null;
    }

    private function existingFee(int $arabicLevelId): ?ArabicLevelFee
    {
        return ArabicLevelFee::withTrashed()
            ->where('school_year_id', $this->schoolYearId)
            ->where('arabic_level_id', $arabicLevelId)
            ->with('installmentAmounts')
            ->first();
    }

    /**
     * @return array<int, float|null>
     */
    private function amountsFrom(?ArabicLevelFee $arabicLevelFee): array
    {
        return Installment::where('school_year_id', $this->schoolYearId)
            ->orderBy('position')
            ->get()
            ->mapWithKeys(function (Installment $installment) use ($arabicLevelFee) {
                $amount = $arabicLevelFee?->installmentAmounts->firstWhere('installment_id', $installment->id);

                return [$installment->id => $amount ? (float) $amount->amount : null];
            })
            ->all();
    }

    public function render()
    {
        $allowedCycles = array_map(
            fn (Cycle $cycle) => $cycle->value,
            Establishment::findOrFail((int) app('currentEstablishmentId'))->allowedCycles(),
        );

        return view('livewire.billing.tuition-fees.arabic-level-fees', [
            'installments' => Installment::where('school_year_id', $this->schoolYearId)->orderBy('position')->get(),
            'arabicLevels' => ArabicLevel::whereIn('cycle', $allowedCycles)->orderBy('wording')->get(),
            'arabicLevelFees' => ArabicLevelFee::where('school_year_id', $this->schoolYearId)
                ->with('installmentAmounts')
                ->get()
                ->keyBy('arabic_level_id'),
        ]);
    }
}
