<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Bulletins') }}</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-lg border border-stone-200 bg-white p-4">
        <div>
            <label class="block text-sm font-medium text-stone-700">{{ __('Classe') }}</label>
            <select wire:model.live="classroom_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700">{{ __('N° de composition') }}</label>
            <input type="number" min="1" max="10" wire:model.live="composition_number" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
        </div>

        @can('create', \App\Domain\Grading\Models\ReportCard::class)
            <button
                type="button"
                wire:click="generate"
                wire:loading.attr="disabled"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Générer les bulletins') }}
            </button>
        @endcan
    </div>

    @error('classroom_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Rang') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Moyenne / :scale', ['scale' => (int) $scale]) }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($reportCards as $reportCard)
                    <tr wire:key="report-card-{{ $reportCard->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $reportCard->rank }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $reportCard->student?->last_name }} {{ $reportCard->student?->first_name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $reportCard->average }}</td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('grading.report-cards.pdf', $reportCard) }}" target="_blank" class="text-stone-500 hover:text-stone-900">
                                {{ __('Voir le PDF') }}
                            </a>
                            <a href="{{ route('grading.report-cards.pdf', ['reportCard' => $reportCard, 'download' => 1]) }}" class="ms-3 text-stone-500 hover:text-stone-900">
                                {{ __('Télécharger') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">
                            {{ $classroom_id && $composition_number ? __('Sélectionnez une classe et un n° de composition, puis générez les bulletins.') : __('Sélectionnez une classe et un n° de composition.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
