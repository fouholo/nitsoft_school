<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Évaluations') }}</h1>

        @if ($isDirecteur)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle composition') }}
            </button>
        @endif
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Titre') }}</label>
                <input type="text" wire:model="title" placeholder="Composition 1" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('N° de composition') }}</label>
                <input type="number" min="1" max="10" wire:model="composition_number" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('composition_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Date') }}</label>
                <input type="date" wire:model="graded_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('graded_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <p class="mt-4 text-sm text-stone-500">{{ __('Une composition est commune à toutes les classes du primaire.') }}</p>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Titre') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Composition') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($gradeSheets as $gradeSheet)
                    <tr wire:key="grade-sheet-{{ $gradeSheet->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $gradeSheet->title }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ __('Composition :number', ['number' => $gradeSheet->composition_number]) }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $gradeSheet->graded_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('grading.grade-sheets.primaire-students', $gradeSheet) }}" class="text-stone-500 hover:text-stone-900">
                                {{ __('Saisir les notes') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune composition.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
