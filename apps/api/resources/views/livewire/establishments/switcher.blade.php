<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-100"
    >
        {{ $establishments->firstWhere('id', $currentEstablishmentId)?->name ?? __('Choisir un établissement') }}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-stone-400">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-cloak
        class="absolute end-0 z-10 mt-2 w-56 rounded-xl border border-stone-200 bg-white py-1 shadow-lg"
    >
        @forelse ($establishments as $establishment)
            <button
                type="button"
                wire:click="switchTo({{ $establishment->id }})"
                @click="open = false"
                class="block w-full px-4 py-2 text-start text-sm {{ $establishment->id === $currentEstablishmentId ? 'font-semibold text-orange-800 bg-orange-50' : 'text-stone-700 hover:bg-stone-50' }}"
            >
                {{ $establishment->name }}
            </button>
        @empty
            <p class="px-4 py-2 text-sm text-stone-500">{{ __('Aucun établissement associé.') }}</p>
        @endforelse
    </div>
</div>
