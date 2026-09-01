<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Timetable;

use App\Domain\Timetable\Models\TimetableSlot;
use Livewire\Attributes\Layout;
use Livewire\Component;

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

    public function mount(): void
    {
        $this->authorize('viewAny', TimetableSlot::class);
    }

    public function create(): void
    {
        $this->authorize('create', TimetableSlot::class);

        $this->reset(['editingId', 'label', 'start_time', 'end_time', 'sequence', 'is_break']);
        $this->showForm = true;
    }

    public function edit(int $slotId): void
    {
        $slot = TimetableSlot::findOrFail($slotId);

        $this->authorize('update', $slot);

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
        $this->authorize($this->editingId ? 'update' : 'create', $this->editingId ? TimetableSlot::findOrFail($this->editingId) : TimetableSlot::class);

        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_break' => ['boolean'],
        ]);

        $data['establishment_id'] = (int) app('currentEstablishmentId');

        if ($this->editingId) {
            TimetableSlot::findOrFail($this->editingId)->update($data);
        } else {
            TimetableSlot::create($data);
        }

        $this->reset(['editingId', 'label', 'start_time', 'end_time', 'sequence', 'is_break']);
        $this->showForm = false;
    }

    public function delete(int $slotId): void
    {
        $slot = TimetableSlot::findOrFail($slotId);

        $this->authorize('delete', $slot);

        $slot->delete();
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.academics.timetable.slots-index', [
            'timetableSlots' => TimetableSlot::orderBy('sequence')->get(),
        ])->title(__('Grille de créneaux'));
    }
}
