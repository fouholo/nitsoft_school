<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;
use Livewire\Component;

class UidSearchWidget extends Component
{
    public string $uid = '';

    public ?string $errorMessage = null;

    public function search(): void
    {
        $uid = trim($this->uid);

        if ($uid === '') {
            return;
        }

        if (! preg_match('/^\d{12}$/', $uid)) {
            $this->fail(__('Code non reconnu.'));

            return;
        }

        $prefix = substr($uid, 0, 3);

        match ($prefix) {
            '220' => $this->searchStaff($uid),
            '221' => $this->searchStudent($uid),
            default => $this->fail(__("Ce type de code n'est pas encore pris en charge.")),
        };
    }

    private function searchStudent(string $uid): void
    {
        $student = Student::where('uid_serveur', $uid)->first();

        if ($student === null) {
            $this->fail(__('Aucun élève trouvé avec ce code.'));

            return;
        }

        $this->redirectRoute('students.show', $student, navigate: true);
    }

    /**
     * Cloisonné à l'établissement courant, comme searchStudent() : un User
     * peut être affecté à plusieurs établissements (establishment_user), donc
     * pas de fiche staff.show unique sans ce cloisonnement — voir spec.
     */
    private function searchStaff(string $uid): void
    {
        $user = User::where('uid_serveur', $uid)->first();

        $pivot = $user !== null
            ? EstablishmentUserPivot::where('establishment_id', app('currentEstablishmentId'))
                ->where('user_id', $user->id)
                ->first()
            : null;

        if ($pivot === null) {
            $this->fail(__('Aucun membre du personnel trouvé avec ce code dans cet établissement.'));

            return;
        }

        $this->redirectRoute('staff.show', [$pivot->establishment_id, $pivot], navigate: true);
    }

    private function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->reset('uid');
        $this->dispatch('uid-search-failed');
    }

    public function render()
    {
        return view('livewire.dashboard.uid-search-widget');
    }
}
