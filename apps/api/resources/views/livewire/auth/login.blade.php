<div>
    <h1 class="mb-6 text-lg font-semibold text-stone-900">{{ __('Connexion') }}</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-stone-700">{{ __('Adresse e-mail') }}</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700">{{ __('Mot de passe') }}</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model="remember" class="rounded border-stone-300">
            {{ __('Se souvenir de moi') }}
        </label>

        <button
            type="submit"
            class="w-full rounded-lg bg-orange-700 px-4 py-2 text-sm font-medium text-white hover:bg-orange-800"
            wire:loading.attr="disabled"
        >
            {{ __('Se connecter') }}
        </button>

        <p class="text-center text-sm text-stone-500">
            {{ __("Vous êtes parent d'élève ?") }} <a href="{{ route('register') }}" wire:navigate class="text-orange-700 hover:underline">{{ __("S'inscrire") }}</a>
        </p>
    </form>
</div>
