<div>
    <h1 class="mb-6 text-lg font-semibold text-slate-900">Connexion</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Adresse e-mail</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
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
                autocomplete="current-password"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300">
            Se souvenir de moi
        </label>

        <button
            type="submit"
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            wire:loading.attr="disabled"
        >
            Se connecter
        </button>
    </form>
</div>
