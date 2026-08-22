<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', GradeSheet::class);
    }

    public function render()
    {
        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return view('livewire.grading.grade-sheets.index', [
            'isPrimaire' => $establishment?->isPrescolairePrimaire() ?? false,
        ])->title(__('Évaluations'));
    }
}
