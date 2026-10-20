<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Timetable;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Grille de créneaux arabe et jours de cours arabes de l'école — miroir de
 * Academics\Timetable\SlotsIndex. Voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
#[Layout('layouts.app')]
class SlotsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $start_time = '';

    public string $end_time = '';

    public ?int $sequence = null;

    public bool $is_break = false;

    /** @var list<string> */
    public array $days = [];

    public bool $daysJustSaved = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicTimetableSlot::class);

        $this->days = array_map(fn (DayOfWeek $day) => $day->value, $this->establishment()->arabicTimetableDays());
    }

    public function saveDays(): void
    {
        $this->authorize('create', ArabicTimetableSlot::class);

        $this->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => [Rule::enum(DayOfWeek::class)],
        ], [
            'days.required' => __('Choisissez au moins un jour de cours arabe.'),
            'days.min' => __('Choisissez au moins un jour de cours arabe.'),
        ]);

        $this->establishment()->update(['arabic_timetable_days' => array_values(array_unique($this->days))]);

        $this->days = array_map(fn (DayOfWeek $day) => $day->value, $this->establishment()->refresh()->arabicTimetableDays());
        $this->daysJustSaved = true;
    }

    public function create(): void
    {
        $this->authorize('create', ArabicTimetableSlot::class);

        $this->resetForm();
        $this->sequence = 1 + (int) ArabicTimetableSlot::max('sequence');
        $this->showForm = true;
    }

    public function edit(int $slotId): void
    {
        $slot = ArabicTimetableSlot::findOrFail($slotId);

        $this->authorize('update', $slot);

        $this->resetErrorBag();
        $this->editingId = $slot->id;
        $this->label = $slot->label;
        $this->start_time = substr($slot->start_time, 0, 5);
        $this->end_time = substr($slot->end_time, 0, 5);
        $this->sequence = $slot->sequence;
        $this->is_break = $slot->is_break;
        $this->showForm = true;
    }

    public function save(): void
    {
        $slot = $this->editingId ? ArabicTimetableSlot::findOrFail($this->editingId) : null;

        $this->authorize($slot ? 'update' : 'create', $slot ?? ArabicTimetableSlot::class);

        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'sequence' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('arabic_timetable_slots', 'sequence')
                    ->where('establishment_id', (int) app('currentEstablishmentId'))
                    ->ignore($this->editingId),
            ],
            'is_break' => ['boolean'],
        ], [], [
            'label' => __('libellé'),
            'start_time' => __('heure de début'),
            'end_time' => __('heure de fin'),
            'sequence' => __('ordre'),
        ]);

        if ($slot) {
            $slot->update($data);
        } else {
            ArabicTimetableSlot::create($data + ['establishment_id' => (int) app('currentEstablishmentId')]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $slotId): void
    {
        $slot = ArabicTimetableSlot::findOrFail($slotId);

        $this->authorize('delete', $slot);

        $slot->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset(['editingId', 'label', 'start_time', 'end_time', 'sequence', 'is_break']);
    }

    private function establishment(): Establishment
    {
        return Establishment::findOrFail((int) app('currentEstablishmentId'));
    }

    public function render()
    {
        return view('livewire.arabic.timetable.slots-index', [
            'timetableSlots' => ArabicTimetableSlot::orderBy('sequence')->get(),
            'allDays' => DayOfWeek::cases(),
        ])->title(__('Grille de créneaux arabe'));
    }
}
