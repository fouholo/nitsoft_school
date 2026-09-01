<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Timetable;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Browse extends Component
{
    public string $tab = 'classes';

    public function mount(): void
    {
        $this->authorize('browseStaff', TimetableSession::class);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function render()
    {
        $establishmentId = (int) app('currentEstablishmentId');
        $currentSchoolYearId = SchoolYear::where('is_current', true)->value('id');

        return view('livewire.academics.timetable.browse', [
            'classrooms' => Classroom::whereHas('level', fn (Builder $query) => $query->where('cycle', Cycle::Secondaire))
                ->where('school_year_id', $currentSchoolYearId)
                ->orderBy('name')
                ->get(),
            'teachers' => Establishment::find($establishmentId)
                ->users()
                ->wherePivot('role', 'enseignant')
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get(),
        ])->title(__('Emplois du temps'));
    }
}
