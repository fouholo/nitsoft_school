<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Mes enfants') }}</h1>

    @if ($pendingCount > 0)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __(":count demande(s) de liaison en attente d'approbation par l'établissement. Vous serez averti dès qu'elle(s) sera(seront) traitée(s).", ['count' => $pendingCount]) }}
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @forelse ($students as $student)
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <p class="text-lg font-semibold text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</p>
                <p class="text-sm text-stone-500">{{ $student->enrollments->first()?->classroom?->name }}</p>

                <div class="mt-3 flex flex-wrap gap-1 text-sm">
                    <a href="{{ route('guardian-portal.students.grades', $student) }}" class="inline-flex min-h-11 items-center rounded-lg px-2 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                        {{ __('Notes') }}
                    </a>
                    <a href="{{ route('guardian-portal.students.attendance', $student) }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                        {{ __('Présences') }}
                        @if ($alerts[$student->id]['recentAbsence'] ?? false)
                            <span class="rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">{{ __('Absence récente') }}</span>
                        @endif
                    </a>
                    <a href="{{ route('guardian-portal.students.billing', $student) }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                        {{ __('Facturation') }}
                        @if ($alerts[$student->id]['overdue'] ?? false)
                            <span class="rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">{{ __('Retard') }}</span>
                        @endif
                    </a>
                </div>
            </div>
        @empty
            @if ($pendingCount === 0)
                <p class="text-stone-500">
                    {{ __('Aucun enfant rattaché à votre compte.') }}
                    <a href="{{ route('guardian-portal.link-child') }}" wire:navigate class="text-orange-700 hover:underline">{{ __('Lier un enfant') }}</a>
                </p>
            @endif
        @endforelse
    </div>
</div>
