<div>
    <h1 class="text-2xl font-semibold text-stone-900">Mot de passe</h1>
    <p class="mt-1 text-sm text-stone-500">Modifiez le mot de passe utilisé pour vous connecter.</p>

    @if ($changed)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            Mot de passe modifié.
        </div>
    @endif

    <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:max-w-sm">
        <div>
            <label for="current_password" class="block text-sm font-medium text-stone-700">Mot de passe actuel</label>
            <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700">Nouveau mot de passe</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Confirmer le nouveau mot de passe</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
        </div>

        <div>
            <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Enregistrer
            </button>
        </div>
    </form>
</div>
