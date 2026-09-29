<div>
    <h1 class="mb-6 text-lg font-semibold text-stone-900">Inscription parent</h1>

    <form wire:submit="register" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="first_name" class="block text-sm font-medium text-stone-700">Prénom</label>
                <input
                    type="text"
                    id="first_name"
                    wire:model="first_name"
                    autofocus
                    class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
                >
                @error('first_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium text-stone-700">Nom</label>
                <input
                    type="text"
                    id="last_name"
                    wire:model="last_name"
                    class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
                >
                @error('last_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700">Adresse e-mail</label>
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
            <label for="phone" class="block text-sm font-medium text-stone-700">Téléphone</label>
            <input
                type="text"
                id="phone"
                wire:model="phone"
                placeholder="Utilisé pour vous envoyer des SMS"
                class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm"
            >
            @error('phone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700">Mot de passe</label>
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
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Confirmer le mot de passe</label>
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
            S'inscrire
        </button>

        <p class="text-center text-sm text-stone-500">
            Déjà un compte ? <a href="{{ route('login') }}" wire:navigate class="text-orange-700 hover:underline">Se connecter</a>
        </p>
    </form>
</div>
