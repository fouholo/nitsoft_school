<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Classes</h1>

        @can('create', \App\Domain\Academics\Models\Classroom::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle classe
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nom</label>
                <input type="text" wire:model="name" placeholder="6ème A" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Niveau</label>
                <input type="text" wire:model="level" placeholder="6ème" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Cycle</label>
                <select wire:model="cycle" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    @foreach ($cycles as $cycleOption)
                        <option value="{{ $cycleOption->value }}">{{ $cycleOption->label() }}</option>
                    @endforeach
                </select>
                @error('cycle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Capacité</label>
                <input type="number" wire:model="capacity" min="1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('capacity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Niveau</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Cycle</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Capacité</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Année scolaire</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($classrooms as $classroom)
                    <tr wire:key="classroom-{{ $classroom->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $classroom->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $classroom->level }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $classroom->cycle->badgeClass() }}">
                                {{ $classroom->cycle->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $classroom->capacity }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $classroom->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $classroom)
                                <button wire:click="edit({{ $classroom->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $classroom)
                                <button
                                    wire:click="delete({{ $classroom->id }})"
                                    wire:confirm="Supprimer cette classe ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucune classe.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
