<div>
    <h1 class="text-2xl font-semibold text-stone-900">Listes/Rapports</h1>

    <div class="mt-4 rounded-lg border border-stone-200 bg-white p-4 sm:max-w-xl">
        <h2 class="text-base font-medium text-stone-900">Liste des élèves d'une classe</h2>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-stone-700">Année scolaire</label>
                <select wire:model.live="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Classe</label>
                <select wire:model.live="classroom_id" @disabled(! $school_year_id) class="mt-1 block w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($classroom_id)
            <a
                href="{{ route('reports.classroom-students-pdf', $classroom_id) }}"
                target="_blank"
                class="mt-4 inline-block rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                Télécharger le PDF
            </a>
        @endif
    </div>
</div>
