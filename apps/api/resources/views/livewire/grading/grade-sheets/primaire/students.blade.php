<div>
    <a href="{{ route('grading.grade-sheets.index') }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; Retour aux évaluations</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-stone-900">{{ $gradeSheet->title }}</h1>
        <p class="text-sm text-stone-500">
            Composition {{ $gradeSheet->composition_number }} — commune à toutes les classes du primaire
        </p>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Classe</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($students as $student)
                    <tr wire:key="student-{{ $student->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $student->enrollments->first()?->classroom?->name }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('grading.grade-sheets.primaire-enter-student', ['gradeSheet' => $gradeSheet, 'student' => $student]) }}" class="text-stone-500 hover:text-stone-900">
                                Saisir les notes
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-stone-500">Aucun élève.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
