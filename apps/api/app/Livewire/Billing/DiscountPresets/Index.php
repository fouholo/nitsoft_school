<?php

declare(strict_types=1);

namespace App\Livewire\Billing\DiscountPresets;

use App\Domain\Billing\Models\DiscountPreset;
use App\Domain\Billing\Rules\MaxPercentage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Catalogue de réductions du fondateur — voir
 * docs/superpowers/specs/2026-10-31-catalogue-reductions-design.md.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'percentage';

    public ?float $value = null;

    public string $registration_type = 'percentage';

    public ?float $registration_value = null;

    public function mount(): void
    {
        $this->authorize('viewAny', DiscountPreset::class);
    }

    public function create(): void
    {
        $this->authorize('create', DiscountPreset::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $presetId): void
    {
        $preset = DiscountPreset::findOrFail($presetId);

        $this->authorize('update', $preset);

        $this->resetValidation();
        $this->editingId = $preset->id;
        $this->name = $preset->name;
        $this->type = $preset->type ?? 'percentage';
        $this->value = $preset->type !== null ? (float) $preset->value : null;
        $this->registration_type = $preset->registration_type ?? 'percentage';
        $this->registration_value = $preset->registration_type !== null ? (float) $preset->registration_value : null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $preset = $this->editingId ? DiscountPreset::findOrFail($this->editingId) : null;

        $this->authorize($preset ? 'update' : 'create', $preset ?? DiscountPreset::class);

        $data = $this->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('discount_presets', 'name')
                    ->where('establishment_id', (int) app('currentEstablishmentId'))
                    ->ignore($this->editingId),
            ],
            'type' => ['required', 'in:percentage,fixed_amount'],
            'value' => ['required_without:registration_value', 'nullable', 'numeric', 'min:0', new MaxPercentage($this->type)],
            'registration_type' => ['required', 'in:percentage,fixed_amount'],
            'registration_value' => ['nullable', 'numeric', 'min:0', new MaxPercentage($this->registration_type)],
        ], [
            'value.required_without' => __("Renseignez une réduction sur la scolarité ou sur les frais d'inscription."),
        ], [
            'name' => __('nom'),
        ]);

        $values = [
            'name' => $data['name'],
            'type' => $data['value'] !== null ? $data['type'] : null,
            'value' => $data['value'],
            'registration_type' => $data['registration_value'] !== null ? $data['registration_type'] : null,
            'registration_value' => $data['registration_value'],
        ];

        if ($preset) {
            $preset->update($values);
        } else {
            DiscountPreset::create([...$values, 'created_by' => Auth::id()]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function toggleActive(int $presetId): void
    {
        $preset = DiscountPreset::findOrFail($presetId);

        $this->authorize('update', $preset);

        $preset->update(['is_active' => ! $preset->is_active]);
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'type', 'value', 'registration_type', 'registration_value']);
    }

    public function render()
    {
        return view('livewire.billing.discount-presets.index', [
            'presets' => DiscountPreset::orderByDesc('is_active')->orderBy('name')->get(),
        ])->title(__('Réductions types'));
    }
}
