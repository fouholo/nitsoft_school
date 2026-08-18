<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Enrollment\Models\Student;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Facturation')]
class StudentBilling extends Component
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
        $enrollment = $this->student->enrollments()
            ->where('status', 'active')
            ->latest('enrolled_on')
            ->first();

        $registrationAmount = $enrollment !== null ? (float) $enrollment->registration_amount : 0.0;
        $tuitionInstallments = $enrollment !== null ? $enrollment->tuitionInstallments() : collect();
        $totalDue = $registrationAmount + (float) $tuitionInstallments->sum('amount');
        $totalPaid = $enrollment !== null ? (float) $enrollment->total_paid : 0.0;

        return view('livewire.guardian-portal.student-billing', [
            'enrollment' => $enrollment,
            'registrationAmount' => $registrationAmount,
            'tuitionInstallments' => $tuitionInstallments,
            'totalDue' => $totalDue,
            'totalPaid' => $totalPaid,
            'balance' => $totalDue - $totalPaid,
            'payments' => $enrollment?->payments()->latest('paid_at')->get() ?? collect(),
        ]);
    }
}
