<div>
    <h1 class="text-2xl font-semibold text-slate-900">Informations générales</h1>

    <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-2 sm:max-w-xl">
        <div>
            <label class="block text-sm font-medium text-slate-700">Nom du ministère</label>
            <input type="text" wire:model="nom_ministere" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            @error('nom_ministere') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Année scolaire en cours</label>
            <input type="text" wire:model="annee_scolaire_courante" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            @error('annee_scolaire_courante') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Enregistrer
            </button>
        </div>
    </form>
</div>
