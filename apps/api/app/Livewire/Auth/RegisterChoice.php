<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Point d'entrée « S'inscrire » de la page de connexion : oriente vers le
 * parcours adapté (fondateur, personnel de direction, parent d'élève).
 */
#[Layout('layouts.guest')]
class RegisterChoice extends Component
{
    public function render()
    {
        return view('livewire.auth.register-choice')->title(__('Inscription'));
    }
}
