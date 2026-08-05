<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
    >
        {{ $establishments->firstWhere('id', $currentEstablishmentId)?->name ?? 'Choisir un établissement' }}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-400">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-cloak
        class="absolute right-0 z-10 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
    >
        @forelse ($establishments as $establishment)
            <button
                type="button"
                wire:click="switchTo({{ $establishment->id }})"
                @click="open = false"
                class="block w-full px-4 py-2 text-left text-sm {{ $establishment->id === $currentEstablishmentId ? 'font-semibold text-indigo-700 bg-indigo-50' : 'text-slate-700 hover:bg-slate-50' }}"
            >
                {{ $establishment->name }}
            </button>
        @empty
            <p class="px-4 py-2 text-sm text-slate-500">Aucun établissement associé.</p>
        @endforelse
    </div>
</div>
