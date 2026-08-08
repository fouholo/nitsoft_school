<div>
    <h1 class="mb-6 text-lg font-semibold text-slate-900">Inscription administrateur SaaS</h1>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nom</label>
            <input
                type="text"
                id="name"
                wire:model="name"
                autofocus
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Adresse e-mail</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmer le mot de passe</label>
            <input
                type="password"
                id="password_confirmation"
                wire:model="password_confirmation"
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            wire:loading.attr="disabled"
        >
            S'inscrire
        </button>

        <p class="text-center text-sm text-slate-500">
            Déjà un compte ? <a href="{{ route('login') }}" wire:navigate class="text-indigo-600 hover:underline">Se connecter</a>
        </p>
    </form>
</div>
