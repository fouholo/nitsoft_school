<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Matières</h1>

        @can('create', \App\Domain\Academics\Models\Subject::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle matière
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nom</label>
                <input type="text" wire:model="name" placeholder="Mathématiques" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Abréviation</label>
                <input type="text" wire:model="abbreviation" placeholder="MATHS" maxlength="10" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('abbreviation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Domaine</label>
                <select wire:model="domain_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                    @endforeach
                </select>
                @error('domain_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-4">
                <span class="block text-sm font-medium text-slate-700">Cycle</span>
                <div class="mt-1 flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_prescolaire_primaire" class="rounded border-slate-300">
                        Préscolaire/Primaire
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_secondaire" class="rounded border-slate-300">
                        Secondaire
                    </label>
                </div>
                @error('is_prescolaire_primaire') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Abréviation</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Cycle</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Domaine</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($subjects as $subject)
                    <tr wire:key="subject-{{ $subject->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $subject->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $subject->abbreviation }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            @if ($subject->is_prescolaire_primaire && $subject->is_secondaire)
                                Préscolaire/Primaire, Secondaire
                            @elseif ($subject->is_prescolaire_primaire)
                                Préscolaire/Primaire
                            @elseif ($subject->is_secondaire)
                                Secondaire
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $subject->domain?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('update', $subject)
                                <button wire:click="edit({{ $subject->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $subject)
                                <button
                                    wire:click="delete({{ $subject->id }})"
                                    wire:confirm="Supprimer cette matière ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune matière.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
