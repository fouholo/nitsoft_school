<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\GuardianStudentPivot;
use App\Domain\Enrollment\Models\Student;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Lier un enfant')]
class LinkChild extends Component
{
    use EnsuresGuardianAccess;

    public string $uid = '';

    public ?string $relationship = null;

    public ?Student $foundStudent = null;

    public function mount(): void
    {
        $this->currentGuardian();
    }

    public function search(): void
    {
        $this->foundStudent = null;

        $this->validate(['uid' => ['required', 'digits:12']]);

        $this->foundStudent = Student::withoutTenant()->where('uid_serveur', $this->uid)->first();

        if (! $this->foundStudent) {
            throw ValidationException::withMessages([
                'uid' => "Aucun élève ne correspond à cet identifiant.",
            ]);
        }
    }

    public function requestLink(): void
    {
        abort_unless($this->foundStudent instanceof Student, 422);

        $this->validate(['relationship' => ['required', Rule::enum(GuardianRelationship::class)]]);

        GuardianStudentPivot::updateOrCreate(
            [
                'guardian_id' => $this->currentGuardian()->id,
                'student_id' => $this->foundStudent->id,
            ],
            [
                'establishment_id' => $this->foundStudent->establishment_id,
                'status' => GuardianLinkStatus::Pending,
                'relationship' => $this->relationship,
                'is_primary_contact' => false,
            ],
        );

        session()->flash('status', 'Votre demande a été envoyée, elle sera examinée par l’établissement.');

        $this->reset(['uid', 'relationship', 'foundStudent']);
    }

    public function render()
    {
        return view('livewire.guardian-portal.link-child');
    }
}
