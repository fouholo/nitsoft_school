<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Mes enfants</a>

    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Notes — {{ $student->last_name }} {{ $student->first_name }}</h1>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Période</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Matière</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Évaluation</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($grades as $grade)
                    <tr wire:key="guardian-grade-{{ $grade->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $grade->gradeSheet?->term?->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $grade->gradeSheet?->subject?->name ?? $grade->gradeSheet?->primarySubject?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $grade->gradeSheet?->title }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $grade->score }} / {{ $grade->gradeSheet?->max_score }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucune note disponible.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
