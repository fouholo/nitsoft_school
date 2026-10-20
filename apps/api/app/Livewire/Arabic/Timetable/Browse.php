<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Timetable;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Point d'entrée « Emplois du temps » de la filière arabe : les classes
 * arabes de l'année courante — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
#[Layout('layouts.app')]
class Browse extends Component
{
    public function mount(): void
    {
        $this->authorize('browseStaff', ArabicTimetableSession::class);
    }

    public function render()
    {
        return view('livewire.arabic.timetable.browse', [
            'arabicClassrooms' => ArabicClassroom::query()
                ->whereHas('schoolYear', fn (Builder $query) => $query->where('is_current', true))
                ->withCount('timetableSessions')
                ->orderBy('name')
                ->get(),
        ])->title(__('Emplois du temps arabes'));
    }
}
