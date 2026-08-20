<div>
    <a href="{{ route('foundations.index') }}" wire:navigate class="text-sm text-stone-500 hover:text-stone-900">&larr; Retour aux groupes scolaires</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ $foundation->name }}</h1>

    @if ($generatedPassword)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Compte créé pour <span class="font-medium">{{ $generatedPasswordFor }}</span>. Mot de passe temporaire (affiché une seule fois) :
            <span class="font-mono font-semibold">{{ $generatedPassword }}</span>
        </div>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Établissements du groupe</h2>

            <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($establishments as $establishment)
                            <tr wire:key="establishment-{{ $establishment->id }}">
                                <td class="px-4 py-2 text-stone-900">{{ $establishment->name }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        wire:click="unlinkEstablishment({{ $establishment->id }})"
                                        wire:confirm="Détacher cet établissement du groupe ?"
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        Détacher
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-stone-500">Aucun établissement rattaché.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form wire:submit="linkEstablishment" class="mt-3 flex gap-2">
                <select wire:model="establishment_to_link" class="block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">Rattacher un établissement existant…</option>
                    @foreach ($availableEstablishments as $establishment)
                        <option value="{{ $establishment->id }}">{{ $establishment->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="shrink-0 rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Rattacher
                </button>
            </form>
            @error('establishment_to_link') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($availableEstablishments->isEmpty())
                <p class="mt-2 text-sm text-stone-400">Aucun établissement indépendant disponible à rattacher.</p>
            @endif
        </section>

        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Fondateurs</h2>

            <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($founders as $founder)
                            <tr wire:key="founder-{{ $founder->id }}">
                                <td class="px-4 py-2 text-stone-900">
                                    {{ $founder->user->name }}
                                    <span class="block text-xs text-stone-500">{{ $founder->user->email }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        wire:click="removeFounder({{ $founder->id }})"
                                        wire:confirm="Retirer ce fondateur du groupe ?"
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        Retirer
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-stone-500">Aucun fondateur rattaché.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form wire:submit="addFounder" class="mt-3 space-y-2 rounded-lg border border-stone-200 bg-white p-3">
                <div>
                    <label class="block text-xs font-medium text-stone-700">Nom</label>
                    <input type="text" wire:model="founder_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('founder_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">E-mail</label>
                    <input type="email" wire:model="founder_email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('founder_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-stone-400">Si un compte existe déjà avec cet e-mail, il est simplement rattaché comme fondateur.</p>
                </div>
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Ajouter le fondateur
                </button>
            </form>
        </section>
    </div>
</div>
