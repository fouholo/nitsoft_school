<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
    >
        {{ $establishments->firstWhere('id', $currentEstablishmentId)?->name ?? 'Choisir un établissement' }}
        <span aria-hidden="true">&#9662;</span>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-cloak
        class="absolute right-0 z-10 mt-2 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg"
    >
        @forelse ($establishments as $establishment)
            <button
                type="button"
                wire:click="switchTo({{ $establishment->id }})"
                @click="open = false"
                class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 {{ $establishment->id === $currentEstablishmentId ? 'font-semibold' : '' }}"
            >
                {{ $establishment->name }}
            </button>
        @empty
            <p class="px-4 py-2 text-sm text-slate-500">Aucun établissement associé.</p>
        @endforelse
    </div>
</div>
