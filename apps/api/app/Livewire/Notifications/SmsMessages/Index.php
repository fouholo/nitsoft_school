<?php

declare(strict_types=1);

namespace App\Livewire\Notifications\SmsMessages;

use App\Domain\Notifications\Models\SmsMessage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', SmsMessage::class);
    }

    public function render()
    {
        return view('livewire.notifications.sms-messages.index', [
            'smsMessages' => SmsMessage::with('guardian')->latest()->paginate(20),
        ])->title(__('Journal SMS'));
    }
}
