<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Mes enfants')]
class Dashboard extends Component
{
    use EnsuresGuardianAccess;

    public function mount(): void
    {
        $this->currentGuardian();
    }

    public function render()
    {
        return view('livewire.guardian-portal.dashboard', [
            'students' => $this->currentGuardian()->students()->with('enrollments.classroom')->get(),
        ]);
    }
}
