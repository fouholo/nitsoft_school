<div>
    <h1 class="text-2xl font-semibold text-stone-900">Informations générales</h1>

    <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-2 sm:max-w-xl">
        <div>
            <label class="block text-sm font-medium text-stone-700">Nom du ministère</label>
            <input type="text" wire:model="nom_ministere" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('nom_ministere') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700">Année scolaire en cours</label>
            <input type="text" wire:model="annee_scolaire_courante" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('annee_scolaire_courante') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-700">Armoirie</label>
            @if ($existingArmoiriePath)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingArmoiriePath) }}" alt="Armoirie actuelle" class="mt-1 mb-2 h-12 w-12 rounded-lg object-cover">
            @endif
            <input type="file" wire:model="armoirie" class="mt-1 block w-full text-sm">
            @error('armoirie') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-700">Image de fond de la carte d'identité scolaire</label>
            <p class="mt-1 text-xs text-stone-500">Format carte de crédit (85,6 × 53,98 mm) — prévoyez un espace lisible pour la photo et les informations de l'élève.</p>
            @if ($existingCardBackgroundPath)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingCardBackgroundPath) }}" alt="Fond de carte actuel" class="mt-2 mb-2 h-24 w-auto rounded-lg border border-stone-200 object-cover">
            @endif
            <input type="file" wire:model="cardBackground" class="mt-1 block w-full text-sm">
            @error('cardBackground') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Enregistrer
            </button>
        </div>
    </form>
</div>
