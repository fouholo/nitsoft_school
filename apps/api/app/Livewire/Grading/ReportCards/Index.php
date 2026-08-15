<?php

declare(strict_types=1);

namespace App\Livewire\Grading\ReportCards;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\ReportCard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bulletins')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', ReportCard::class);
    }

    public function render()
    {
        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return view('livewire.grading.report-cards.index', [
            'isPrimaire' => $establishment?->isPrescolairePrimaire() ?? false,
        ]);
    }
}
