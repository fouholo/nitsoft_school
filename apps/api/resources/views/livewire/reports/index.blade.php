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
            <div class="mt-4 flex flex-wrap gap-2">
                <a
                    href="{{ route('reports.classroom-students-pdf', $classroom_id) }}"
                    target="_blank"
                    class="inline-block rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    Liste des élèves (PDF)
                </a>
                <a
                    href="{{ route('reports.classroom-id-cards-pdf', $classroom_id) }}"
                    target="_blank"
                    class="inline-block rounded-lg border border-orange-700 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50"
                >
                    Cartes d'identité scolaires (PDF)
                </a>
            </div>
        @endif
    </div>

    <div class="mt-4 rounded-lg border border-stone-200 bg-white p-4 sm:max-w-xl">
        <h2 class="text-base font-medium text-stone-900">Carte d'identité scolaire — un élève</h2>

        <div class="mt-4">
            <label class="block text-sm font-medium text-stone-700">Rechercher un élève</label>
            <input
                type="search"
                wire:model.live.debounce.300ms="studentSearch"
                placeholder="Nom, prénom ou matricule..."
                class="mt-1 block w-full rounded-lg border-stone-300 text-sm"
            >

            <select wire:model.live="card_student_id" class="mt-2 block w-full rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($cardStudents as $cardStudent)
                    <option value="{{ $cardStudent->id }}">
                        {{ $cardStudent->last_name }} {{ $cardStudent->first_name }}
                        @if ($cardStudent->student_number)
                            — {{ $cardStudent->student_number }}
                        @endif
                    </option>
                @endforeach
            </select>

            @if ($cardStudents->count() >= 50)
                <p class="mt-1 text-xs text-stone-500">Affinez la recherche pour voir plus de résultats.</p>
            @endif
        </div>

        @if ($card_student_id)
            <a
                href="{{ route('reports.student-id-card-pdf', $card_student_id) }}"
                target="_blank"
                class="mt-4 inline-block rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                Télécharger la carte (PDF)
            </a>
        @endif
    </div>
</div>
