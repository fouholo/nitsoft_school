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
                <span class="block text-sm font-medium text-slate-700">Coefficient et barème par niveau (laisser vide si non applicable)</span>
                <div class="mt-1 grid grid-cols-2 gap-4 sm:grid-cols-6">
                    @foreach (['cp1' => 'CP1', 'cp2' => 'CP2', 'ce1' => 'CE1', 'ce2' => 'CE2', 'cm1' => 'CM1', 'cm2' => 'CM2'] as $suffix => $label)
                        <div class="rounded-md border border-slate-200 p-2">
                            <p class="text-xs font-medium text-slate-700">{{ $label }}</p>
                            <label class="mt-1 block text-xs text-slate-500">Coef.</label>
                            <input type="number" step="0.5" wire:model="coefficient_{{ $suffix }}" class="mt-0.5 block w-full rounded-md border-slate-300 text-sm">
                            @error('coefficient_'.$suffix) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <label class="mt-1 block text-xs text-slate-500">Barème</label>
                            <input type="number" step="1" wire:model="bareme_{{ $suffix }}" class="mt-0.5 block w-full rounded-md border-slate-300 text-sm">
                            @error('bareme_'.$suffix) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
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
                <tr class="bg-slate-50 text-xs text-slate-400">
                    <th class="px-4 pb-2 text-left font-normal"></th>
                    <th class="px-4 pb-2 text-left font-normal"></th>
                    <th class="px-4 pb-2 text-left font-normal" colspan="6">coefficient / barème</th>
                    <th class="px-4 pb-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($primarySubjects as $primarySubject)
                    <tr wire:key="primary-subject-{{ $primarySubject->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $primarySubject->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $primarySubject->abbreviation }}</td>
                        @foreach (['cp1', 'cp2', 'ce1', 'ce2', 'cm1', 'cm2'] as $suffix)
                            <td class="px-4 py-2 text-slate-600">
                                {{ $primarySubject->{'coefficient_'.$suffix} ?? '—' }} / {{ $primarySubject->{'bareme_'.$suffix} ?? '—' }}
                            </td>
                        @endforeach
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
