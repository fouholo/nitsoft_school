<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Matières du primaire</h1>

        @can('create', \App\Domain\Academics\Models\PrimarySubject::class)
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

            <div class="sm:col-span-4">
                <span class="block text-sm font-medium text-slate-700">Coefficient par niveau (laisser vide si non applicable)</span>
                <div class="mt-1 grid grid-cols-3 gap-4 sm:grid-cols-6">
                    <div>
                        <label class="block text-xs text-slate-500">CP1</label>
                        <input type="number" step="0.5" wire:model="coefficient_cp1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_cp1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">CP2</label>
                        <input type="number" step="0.5" wire:model="coefficient_cp2" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_cp2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">CE1</label>
                        <input type="number" step="0.5" wire:model="coefficient_ce1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_ce1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">CE2</label>
                        <input type="number" step="0.5" wire:model="coefficient_ce2" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_ce2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">CM1</label>
                        <input type="number" step="0.5" wire:model="coefficient_cm1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_cm1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">CM2</label>
                        <input type="number" step="0.5" wire:model="coefficient_cm2" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('coefficient_cm2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
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
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CP1</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CP2</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CE1</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CE2</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CM1</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">CM2</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($primarySubjects as $primarySubject)
                    <tr wire:key="primary-subject-{{ $primarySubject->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $primarySubject->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->abbreviation }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_cp1 ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_cp2 ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_ce1 ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_ce2 ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_cm1 ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->coefficient_cm2 ?? '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('update', $primarySubject)
                                <button wire:click="edit({{ $primarySubject->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $primarySubject)
                                <button
                                    wire:click="delete({{ $primarySubject->id }})"
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
                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">Aucune matière.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
