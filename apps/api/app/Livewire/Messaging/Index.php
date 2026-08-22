<?php

declare(strict_types=1);

namespace App\Livewire\Messaging;

use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?Conversation $selectedConversation = null;

    public bool $showNewConversationForm = false;

    public string $contactSearch = '';

    /** @var array<int, int|string> */
    public array $selectedParticipantIds = [];

    public string $groupName = '';

    public string $newMessageBody = '';

    public function mount(?Conversation $conversation = null): void
    {
        if ($conversation !== null) {
            $this->authorize('view', $conversation);
            $this->selectedConversation = $conversation;
            $this->markAsRead();
        }
    }

    public function toggleNewConversationForm(): void
    {
        $this->showNewConversationForm = ! $this->showNewConversationForm;
        $this->reset(['contactSearch', 'selectedParticipantIds', 'groupName']);
    }

    public function toggleParticipant(int $userId): void
    {
        if (in_array($userId, $this->selectedParticipantIds, true)) {
            $this->selectedParticipantIds = array_values(array_diff($this->selectedParticipantIds, [$userId]));

            return;
        }

        $this->selectedParticipantIds[] = $userId;
    }

    public function startConversation(): void
    {
        $this->validate([
            'selectedParticipantIds' => ['required', 'array', 'min:1'],
            'groupName' => ['nullable', 'string', 'max:255'],
        ]);

        $participantIds = collect($this->selectedParticipantIds)
            ->map(fn ($id) => (int) $id)
            ->intersect($this->availableContactIds())
            ->values();

        abort_if($participantIds->isEmpty(), 422);

        $name = trim($this->groupName);

        $conversation = $participantIds->count() === 1 && $name === ''
            ? $this->findOrCreateDirectConversation($participantIds->first())
            : $this->createGroupConversation($participantIds->all(), $name);

        $this->reset(['contactSearch', 'selectedParticipantIds', 'groupName', 'showNewConversationForm']);

        $this->redirectRoute('messaging.index', ['conversation' => $conversation->id], navigate: true);
    }

    public function sendMessage(): void
    {
        abort_unless($this->selectedConversation !== null, 404);
        $this->authorize('view', $this->selectedConversation);

        $data = $this->validate([
            'newMessageBody' => ['required', 'string', 'max:5000'],
        ]);

        Message::create([
            'conversation_id' => $this->selectedConversation->id,
            'sender_id' => Auth::id(),
            'body' => $data['newMessageBody'],
        ]);

        $this->markAsRead();
        $this->reset('newMessageBody');
    }

    public function refreshThread(): void
    {
        if ($this->selectedConversation !== null) {
            $this->markAsRead();
        }
    }

    private function markAsRead(): void
    {
        $this->selectedConversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
    }

    /**
     * @return Collection<int, int>
     */
    private function availableContactIds(): Collection
    {
        $establishmentIds = Auth::user()->accessibleEstablishments()->pluck('id');

        return EstablishmentUserPivot::query()
            ->whereIn('establishment_id', $establishmentIds)
            ->where('is_active', true)
            ->where('user_id', '!=', Auth::id())
            ->distinct()
            ->pluck('user_id');
    }

    private function findOrCreateDirectConversation(int $otherUserId): Conversation
    {
        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('user_id', Auth::id()))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $otherUserId))
            ->get()
            ->first(fn (Conversation $conversation) => $conversation->participants()->count() === 2);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($otherUserId) {
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => Auth::id(),
            ]);

            $conversation->participants()->attach([Auth::id(), $otherUserId]);

            return $conversation;
        });
    }

    /**
     * @param  list<int>  $participantIds
     */
    private function createGroupConversation(array $participantIds, string $name): Conversation
    {
        return DB::transaction(function () use ($participantIds, $name) {
            $conversation = Conversation::create([
                'type' => 'group',
                'name' => $name !== '' ? $name : null,
                'created_by' => Auth::id(),
            ]);

            $conversation->participants()->attach(array_unique([Auth::id(), ...$participantIds]));

            return $conversation;
        });
    }

    public function render()
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', Auth::id()))
            ->with(['participants', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => optional($conversation->messages->first())->created_at ?? $conversation->created_at)
            ->values();

        $contacts = User::query()
            ->whereIn('id', $this->availableContactIds())
            ->when($this->contactSearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->contactSearch.'%'))
            ->orderBy('name')
            ->get();

        $messages = $this->selectedConversation !== null
            ? $this->selectedConversation->messages()->with('sender')->orderBy('created_at')->get()
            : collect();

        return view('livewire.messaging.index', [
            'conversations' => $conversations,
            'contacts' => $contacts,
            'messages' => $messages,
        ])->title(__('Messagerie'));
    }
}
