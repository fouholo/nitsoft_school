<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Mot de passe') }}</h1>
    <p class="mt-1 text-sm text-stone-500">{{ __('Modifiez le mot de passe utilisé pour vous connecter.') }}</p>

    @if ($forced)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:max-w-xl" role="alert">
            <p class="font-medium">{{ __('Choisissez votre mot de passe pour continuer.') }}</p>
            <p class="mt-1">{{ __("Votre compte utilise encore le mot de passe fourni à sa création. Pour protéger votre accès, remplacez-le par un mot de passe que vous êtes seul à connaître.") }}</p>
        </div>
    @endif

    @if ($changed)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ __('Mot de passe modifié.') }}
        </div>
    @endif

    <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:max-w-sm">
        <div>
            <label for="current_password" class="block text-sm font-medium text-stone-700">{{ __('Mot de passe actuel') }}</label>
            <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700">{{ __('Nouveau mot de passe') }}</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" aria-describedby="password-hint" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            <p id="password-hint" class="mt-1 text-xs text-stone-500">{{ __('8 caractères minimum.') }}</p>
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700">{{ __('Confirmer le nouveau mot de passe') }}</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
        </div>

        <div>
            <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Enregistrer') }}
            </button>
        </div>
    </form>
</div>
