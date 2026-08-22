<div class="flex h-[calc(100vh-8rem)] gap-4">
    <div class="flex w-80 shrink-0 flex-col rounded-lg border border-stone-200 bg-white">
        <div class="flex items-center justify-between border-b border-stone-200 p-3">
            <h1 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Messagerie') }}</h1>
            <button type="button" wire:click="toggleNewConversationForm" class="rounded-lg bg-orange-700 px-2 py-1 text-xs font-medium text-white hover:bg-orange-800">
                {{ $showNewConversationForm ? __('Annuler') : __('Nouvelle conversation') }}
            </button>
        </div>

        @if ($showNewConversationForm)
            <div class="space-y-2 border-b border-stone-200 p-3">
                <input type="text" wire:model.live.debounce.300ms="contactSearch" placeholder="{{ __('Rechercher une personne...') }}" class="block w-full rounded-lg border-stone-300 text-sm">

                <div class="max-h-40 space-y-1 overflow-y-auto">
                    @forelse ($contacts as $contact)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-stone-50">
                            <input type="checkbox" wire:click="toggleParticipant({{ $contact->id }})" @checked(in_array($contact->id, $selectedParticipantIds)) class="rounded border-stone-300">
                            {{ $contact->name }}
                        </label>
                    @empty
                        <p class="px-2 py-1 text-xs text-stone-400">{{ __('Aucun contact trouvé.') }}</p>
                    @endforelse
                </div>
                @error('selectedParticipantIds') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                @if (count($selectedParticipantIds) > 1)
                    <input type="text" wire:model="groupName" placeholder="{{ __('Nom du groupe (optionnel)') }}" class="block w-full rounded-lg border-stone-300 text-sm">
                @endif

                <button type="button" wire:click="startConversation" class="w-full rounded-lg bg-orange-700 px-2 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Démarrer') }}
                </button>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $conversation)
                @php $unread = $conversation->unreadCountFor(auth()->user()); @endphp
                <a
                    href="{{ route('messaging.index', $conversation) }}"
                    wire:navigate
                    class="block border-b border-stone-100 px-3 py-2.5 {{ $selectedConversation?->id === $conversation->id ? 'bg-orange-50' : 'hover:bg-stone-50' }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm {{ $unread > 0 ? 'font-semibold text-stone-900' : 'font-medium text-stone-700' }}">{{ $conversation->displayName(auth()->user()) }}</span>
                        @if ($unread > 0)
                            <span class="shrink-0 rounded-full bg-orange-700 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $unread }}</span>
                        @endif
                    </div>
                    @if ($conversation->messages->first())
                        <p class="mt-0.5 truncate text-xs text-stone-500">{{ $conversation->messages->first()->body }}</p>
                    @endif
                </a>
            @empty
                <p class="p-3 text-sm text-stone-500">{{ __('Aucune conversation pour l’instant.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="flex flex-1 flex-col rounded-lg border border-stone-200 bg-white">
        @if ($selectedConversation)
            <div class="border-b border-stone-200 p-3">
                <h2 class="text-sm font-semibold text-stone-900">{{ $selectedConversation->displayName(auth()->user()) }}</h2>
            </div>

            <div wire:poll.5s="refreshThread" class="flex-1 space-y-3 overflow-y-auto p-4">
                @forelse ($messages as $chatMessage)
                    @php $isMine = $chatMessage->sender_id === auth()->id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-md rounded-lg px-3 py-2 text-sm {{ $isMine ? 'bg-orange-700 text-white' : 'bg-stone-100 text-stone-900' }}">
                            @unless ($isMine)
                                <p class="mb-0.5 text-xs font-semibold text-stone-500">{{ $chatMessage->sender->name }}</p>
                            @endunless
                            <p>{{ $chatMessage->body }}</p>
                            <p class="mt-1 text-[11px] {{ $isMine ? 'text-orange-100' : 'text-stone-400' }}">{{ $chatMessage->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">{{ __('Aucun message pour l’instant. Écrivez le premier !') }}</p>
                @endforelse
            </div>

            <form wire:submit="sendMessage" class="border-t border-stone-200 p-3">
                <div class="flex items-end gap-2">
                    <textarea wire:model="newMessageBody" rows="2" placeholder="{{ __('Votre message...') }}" class="block w-full rounded-lg border-stone-300 text-sm"></textarea>
                    <button type="submit" class="shrink-0 rounded-lg bg-orange-700 px-3 py-2 text-sm font-medium text-white hover:bg-orange-800">{{ __('Envoyer') }}</button>
                </div>
                @error('newMessageBody') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @else
            <div class="flex flex-1 items-center justify-center p-6 text-sm text-stone-500">
                {{ __('Sélectionnez une conversation ou démarrez-en une nouvelle.') }}
            </div>
        @endif
    </div>
</div>
