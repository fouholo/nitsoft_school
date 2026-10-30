<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Compositions') }}</h1>

        @can('create', \App\Domain\Arabic\Models\ArabicComposition::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle composition') }}
            </button>
        @endcan
    </div>

    @error('delete')
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-2">
            <div>
                <label for="month" class="block text-sm font-medium text-stone-700">{{ __('Mois') }}</label>
                <select id="month" wire:model="month" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @foreach ($months as $monthOption)
                        <option value="{{ $monthOption->value }}">{{ $monthOption->label() }}</option>
                    @endforeach
                </select>
                @error('month') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="graded_on" class="block text-sm font-medium text-stone-700">{{ __('Date') }}</label>
                <input id="graded_on" type="date" wire:model="graded_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('graded_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">{{ __('Enregistrer') }}</button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">{{ __('Annuler') }}</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Mois') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Notes saisies') }}</th>
                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($arabicCompositions as $arabicComposition)
                    <tr wire:key="arabic-composition-{{ $arabicComposition->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $arabicComposition->month->label() }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $arabicComposition->graded_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 tabular-nums text-stone-600">{{ $arabicComposition->grades_count }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-end">
                            <a href="{{ route('arabic.compositions.students', $arabicComposition) }}" class="text-orange-700 hover:underline">{{ __('Saisir les notes') }}</a>
                            @can('delete', $arabicComposition)
                                <button
                                    type="button"
                                    wire:click="delete({{ $arabicComposition->id }})"
                                    wire:confirm="{{ __('Supprimer cette composition ?') }}"
                                    class="ms-3 text-red-600 hover:text-red-800"
                                >{{ __('Supprimer') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune composition pour cette année scolaire.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
