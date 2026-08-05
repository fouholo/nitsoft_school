<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Models\Student;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Factures')]
class StudentInvoices extends Component
{
    use EnsuresGuardianAccess;

    public Student $student;

    public function mount(Student $student): void
    {
        $this->authorizeGuardianAccess($student);

        $this->student = $student;
    }

    public function render()
    {
        return view('livewire.guardian-portal.student-invoices', [
            'invoices' => Invoice::query()
                ->where('student_id', $this->student->id)
                ->orderByDesc('due_date')
                ->get(),
        ]);
    }
}
