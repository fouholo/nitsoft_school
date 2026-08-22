<div>
    <h1 class="mb-6 text-lg font-semibold text-stone-900">{{ __('Inscription administrateur SaaS') }}</h1>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
            <input
                type="text"
                id="name"
                wire:model="name"
                autofocus
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700">{{ __('Adresse e-mail') }}</label>
            <input
                type="email"
                id="email"
                wire:model="email"
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
                autocomplete="new-password"
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700">{{ __('Confirmer le mot de passe') }}</label>
            <input
                type="password"
                id="password_confirmation"
                wire:model="password_confirmation"
                autocomplete="new-password"
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-orange-700 px-4 py-2 text-sm font-medium text-white hover:bg-orange-800"
            wire:loading.attr="disabled"
        >
            {{ __("S'inscrire") }}
        </button>

        <p class="text-center text-sm text-stone-500">
            {{ __('Déjà un compte ?') }} <a href="{{ route('login') }}" wire:navigate class="text-orange-700 hover:underline">{{ __('Se connecter') }}</a>
        </p>
    </form>
</div>
