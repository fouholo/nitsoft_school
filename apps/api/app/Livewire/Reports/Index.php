<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Listes/Rapports')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public ?int $classroom_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    public function updatedSchoolYearId(): void
    {
        $this->classroom_id = null;
    }

    public function render()
    {
        return view('livewire.reports.index', [
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'classrooms' => $this->school_year_id
                ? Classroom::where('school_year_id', $this->school_year_id)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
