<?php

declare(strict_types=1);

namespace App\Livewire\GeneralInformation;

use App\Domain\Establishments\Models\GeneralInformation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Informations générales')]
class Edit extends Component
{
    public GeneralInformation $record;

    public string $nom_ministere = '';

    public string $annee_scolaire_courante = '';

    public function mount(): void
    {
        $this->record = GeneralInformation::current();

        $this->authorize('view', $this->record);

        $this->nom_ministere = (string) $this->record->nom_ministere;
        $this->annee_scolaire_courante = (string) $this->record->annee_scolaire_courante;
    }

    public function save(): void
    {
        $this->authorize('update', $this->record);

        $data = $this->validate([
            'nom_ministere' => ['nullable', 'string', 'max:255'],
            'annee_scolaire_courante' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data as $field => $value) {
            $data[$field] = $value !== '' ? $value : null;
        }

        $this->record->update($data);
    }

    public function render()
    {
        return view('livewire.general-information.edit');
    }
}
