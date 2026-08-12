<div>
    <h1 class="text-2xl font-semibold text-slate-900">Listes/Rapports</h1>

    <div class="mt-4 rounded-md border border-slate-200 bg-white p-4 sm:max-w-xl">
        <h2 class="text-base font-medium text-slate-900">Liste des élèves d'une classe</h2>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model.live="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Classe</label>
                <select wire:model.live="classroom_id" @disabled(! $school_year_id) class="mt-1 block w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500">
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
                class="mt-4 inline-block rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Télécharger le PDF
            </a>
        @endif
    </div>
</div>
