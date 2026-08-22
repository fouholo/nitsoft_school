<?php

declare(strict_types=1);

namespace App\Livewire\GeneralInformation;

use App\Domain\Establishments\Models\GeneralInformation;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public GeneralInformation $record;

    public string $nom_ministere = '';

    public string $annee_scolaire_courante = '';

    public ?TemporaryUploadedFile $armoirie = null;

    public string $existingArmoiriePath = '';

    public ?TemporaryUploadedFile $cardBackground = null;

    public string $existingCardBackgroundPath = '';

    public function mount(): void
    {
        $this->record = GeneralInformation::current();

        $this->authorize('view', $this->record);

        $this->nom_ministere = (string) $this->record->nom_ministere;
        $this->annee_scolaire_courante = (string) $this->record->annee_scolaire_courante;
        $this->existingArmoiriePath = (string) $this->record->armoirie_path;
        $this->existingCardBackgroundPath = (string) $this->record->card_background_path;
    }

    public function save(): void
    {
        $this->authorize('update', $this->record);

        $data = $this->validate([
            'nom_ministere' => ['nullable', 'string', 'max:255'],
            'annee_scolaire_courante' => ['nullable', 'string', 'max:255'],
            'armoirie' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
            'cardBackground' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        foreach (['nom_ministere', 'annee_scolaire_courante'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        unset($data['armoirie'], $data['cardBackground']);

        if ($this->armoirie) {
            if ($this->record->armoirie_path) {
                Storage::disk('public')->delete($this->record->armoirie_path);
            }

            $data['armoirie_path'] = $this->armoirie->store('general-information', 'public');
        }

        if ($this->cardBackground) {
            if ($this->record->card_background_path) {
                Storage::disk('public')->delete($this->record->card_background_path);
            }

            $data['card_background_path'] = $this->cardBackground->store('general-information', 'public');
        }

        $this->record->update($data);
        $this->existingArmoiriePath = (string) $this->record->armoirie_path;
        $this->existingCardBackgroundPath = (string) $this->record->card_background_path;
        $this->armoirie = null;
        $this->cardBackground = null;
    }

    public function render()
    {
        return view('livewire.general-information.edit')->title(__('Informations générales'));
    }
}
