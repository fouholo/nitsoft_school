<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Réductions</h1>

        @can('create', \App\Domain\Billing\Models\Discount::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouvelle réduction
            </button>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap gap-4">
        <div>
            <label class="sr-only">Année scolaire</label>
            <select wire:model.live="school_year_id" class="rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>

        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un élève..."
            class="block w-full max-w-sm rounded-lg border-stone-300 text-sm"
        >
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">Élève</label>
                @if ($editingId)
                    <select disabled class="mt-1 block w-full rounded-lg border-stone-300 bg-stone-50 text-sm text-stone-500">
                        <option>{{ $editingStudentLabel }}</option>
                    </select>
                @else
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="studentSearch"
                        placeholder="Rechercher un élève par nom..."
                        class="mt-1 block w-full rounded-lg border-stone-300 text-sm"
                    >
                    <select wire:model="student_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">—</option>
                        @foreach ($students as $student)
                            @php $classroom = $student->enrollments->first()?->classroom; @endphp
                            <option value="{{ $student->id }}">{{ $student->last_name }} {{ $student->first_name }}{{ $classroom ? ' — '.$classroom->name : '' }}</option>
                        @endforeach
                    </select>
                    @if ($studentSearch === '' && $students->count() >= 50)
                        <p class="mt-1 text-xs text-stone-500">Affichage des 50 premiers élèves — utilisez la recherche pour affiner.</p>
                    @endif
                @endif
                @error('student_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Type</label>
                <select wire:model="type" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="percentage">Pourcentage</option>
                    <option value="fixed_amount">Montant fixe</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Valeur</label>
                <input type="number" step="0.01" wire:model="value" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Motif (optionnel)</label>
                <input type="text" wire:model="reason" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Type</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Valeur</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Motif</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Accordée par</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($discounts as $discount)
                        <tr wire:key="discount-{{ $discount->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $discount->student->last_name }} {{ $discount->student->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $discount->type === 'percentage' ? 'Pourcentage' : 'Montant fixe' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $discount->type === 'percentage' ? number_format((float) $discount->value, 2) . ' %' : money((float) $discount->value) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $discount->reason ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $discount->createdBy?->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @can('update', $discount)
                                    <button wire:click="edit({{ $discount->id }})" class="inline-flex min-h-11 items-center text-stone-500 hover:text-stone-900">Modifier</button>
                                @endcan
                                @can('delete', $discount)
                                    <button
                                        wire:click="delete({{ $discount->id }})"
                                        wire:confirm="Supprimer cette réduction ? Les tranches de scolarité de {{ $discount->student->last_name }} {{ $discount->student->first_name }} seront remises aux montants standards du niveau."
                                        class="ml-3 inline-flex min-h-11 items-center text-red-600 hover:text-red-700"
                                    >
                                        Supprimer
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-stone-500">Aucune réduction pour cette année scolaire.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
