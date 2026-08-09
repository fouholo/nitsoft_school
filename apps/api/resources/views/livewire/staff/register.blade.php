<div>
    <h1 class="mb-6 text-lg font-semibold text-slate-900">Inscription établissement</h1>

    @if ($pendingApproval)
        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Votre compte a été créé. Il est en attente d'activation par l'administrateur de votre organisation.
        </div>

        <p class="mt-4 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" wire:navigate class="text-indigo-600 hover:underline">Retour à la connexion</a>
        </p>
    @else
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
                <label for="uid" class="block text-sm font-medium text-slate-700">Identifiant de l'établissement</label>
                <input
                    type="text"
                    id="uid"
                    wire:model="uid"
                    placeholder="Ex : 000000001046"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
                >
                @error('uid')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-slate-700">Rôle</label>
                <select
                    id="role"
                    wire:model="role"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
                >
                    <option value="">Sélectionner…</option>
                    <option value="fondateur">Fondateur</option>
                    <option value="directeur">Directeur</option>
                    <option value="gestionnaire">Gestionnaire</option>
                </select>
                @error('role')
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
    @endif
</div>
