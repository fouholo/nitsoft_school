<?php

declare(strict_types=1);

namespace App\Livewire\Messaging;

use App\Domain\Messaging\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UnreadBadge extends Component
{
    public function render()
    {
        $user = Auth::user();

        $count = $user->conversations()
            ->get()
            ->filter(fn (Conversation $conversation) => $conversation->unreadCountFor($user) > 0)
            ->count();

        return view('livewire.messaging.unread-badge', ['count' => $count]);
    }
}
