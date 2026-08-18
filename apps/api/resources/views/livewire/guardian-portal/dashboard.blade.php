<div>
    <h1 class="text-2xl font-semibold text-slate-900">Mes enfants</h1>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @forelse ($students as $student)
            <div class="rounded-md border border-slate-200 bg-white p-4">
                <p class="font-medium text-slate-900">{{ $student->last_name }} {{ $student->first_name }}</p>
                <p class="text-sm text-slate-500">{{ $student->enrollments->first()?->classroom?->name }}</p>

                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('guardian-portal.students.grades', $student) }}" class="text-slate-600 hover:text-slate-900 hover:underline">
                        Notes
                    </a>
                    <a href="{{ route('guardian-portal.students.attendance', $student) }}" class="text-slate-600 hover:text-slate-900 hover:underline">
                        Présences
                    </a>
                    <a href="{{ route('guardian-portal.students.billing', $student) }}" class="text-slate-600 hover:text-slate-900 hover:underline">
                        Facturation
                    </a>
                </div>
            </div>
        @empty
            <p class="text-slate-500">
                Aucun enfant rattaché à votre compte.
                <a href="{{ route('guardian-portal.link-child') }}" wire:navigate class="text-indigo-600 hover:underline">Lier un enfant</a>
            </p>
        @endforelse
    </div>
</div>
