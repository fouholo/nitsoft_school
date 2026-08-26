<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Enrollment\Models\Student;
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
