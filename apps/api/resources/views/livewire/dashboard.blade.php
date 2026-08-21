<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Tableau de bord') }}</h1>

    @if ($noEstablishment)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            {{ __('Aucun établissement ne vous est actuellement accessible. Contactez votre administrateur.') }}
        </div>
    @else
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-stone-500">
            <span>{!! __('Connecté en tant que :role sur cet établissement.', ['role' => '<span class="font-medium text-stone-700">'.e($roleLabel).'</span>']) !!}</span>

            @if ($currentSchoolYear || $currentEstablishment)
                <span class="text-stone-300">·</span>
            @endif

            @if ($currentSchoolYear)
                <span>{!! __('Année scolaire :year', ['year' => '<span class="font-medium text-stone-700">'.e($currentSchoolYear->label).'</span>']) !!}</span>
            @endif

            @if ($currentTerm)
                <span class="text-stone-300">·</span>
                <span>{{ $currentTerm->label }}</span>
            @endif

            @if ($currentEstablishment)
                @if ($currentEstablishment->isSecondaire())
                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Secondaire') }}</span>
                @else
                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">{{ __('Cycle Primaire') }}</span>
                @endif
            @endif

            @if ($isMultiSchoolFounder)
                <span class="text-stone-300">·</span>
                <span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">
                    {{ __("Fondateur d'un groupe de :count écoles", ['count' => $groupEstablishmentsCount]) }}
                </span>
            @endif
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <circle cx="12" cy="8" r="4" /><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-2xl font-semibold text-stone-900">{{ $studentsCount }}</p>
                        <p class="text-sm text-stone-500">{{ __('Élèves actifs') }}</p>
                        @if ($studentsCount === 0)
                            <p class="mt-0.5 text-xs text-stone-500">{{ __("Aucun élève inscrit pour l'instant") }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" /><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-2xl font-semibold text-stone-900">{{ $classroomsCount }}</p>
                        <p class="text-sm text-stone-500">{{ __('Classes (année en cours)') }}</p>
                        @if ($classroomsCount === 0)
                            <p class="mt-0.5 text-xs text-stone-500">
                                {{ $currentSchoolYear ? __('Aucune classe créée pour :year', ['year' => $currentSchoolYear->label]) : __('Aucune année scolaire en cours') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            @if (in_array($role, ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'], true))
                <div class="rounded-2xl border border-stone-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M6 9v.01M18 15v.01" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-2xl font-semibold text-stone-900">{{ $pendingInvoicesCount }}</p>
                            <p class="text-sm text-stone-500">{{ __('Factures en attente') }}</p>
                            @if ($pendingInvoicesCount === 0)
                                <p class="mt-0.5 text-xs text-stone-500">{{ __('Aucun solde restant à percevoir') }}</p>
                            @else
                                <p class="text-xs text-stone-500">{{ __(':amount restant à percevoir', ['amount' => money($pendingInvoicesBalance)]) }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if (in_array($role, ['directeur', 'gestionnaire', 'fondateur'], true))
                <div class="rounded-2xl border border-stone-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="12" r="2.5" /><path d="M15 9.5h4M15 12.5h4M6.5 17c.5-1.6 1.7-2.7 2.9-2.7s2.4 1.1 2.9 2.7" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-2xl font-semibold text-stone-900">{{ $staffCount }}</p>
                            <p class="text-sm text-stone-500">{{ __('Membres du personnel') }}</p>
                            @if ($staffCount === 0)
                                <p class="mt-0.5 text-xs text-stone-500">{{ __('Aucun membre du personnel enregistré') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if (in_array($role, ['fondateur', 'directeur', 'gestionnaire'], true))
            @if ($isMultiSchoolFounder)
                <livewire:dashboard.establishment-filter />
            @endif

            <livewire:dashboard.financial-health-widget />
            <livewire:dashboard.trend-widget />
            <livewire:dashboard.alerts-widget />

            @if ($isMultiSchoolFounder)
                <livewire:dashboard.establishment-comparison-widget />
            @endif
        @endif

        @if (
            auth()->user()->can('viewAny', \App\Domain\Enrollment\Models\Student::class)
            || auth()->user()->can('viewAny', \App\Domain\Grading\Models\ReportCard::class)
            || auth()->user()->can('viewAny', \App\Domain\Attendance\Models\AttendanceSession::class)
            || auth()->user()->can('viewAny', \App\Domain\Billing\Models\Payment::class)
        )
            <h2 class="mt-10 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Accès rapides') }}</h2>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @can('viewAny', \App\Domain\Enrollment\Models\Student::class)
                    <a href="{{ route('students.index') }}" wire:navigate class="rounded-2xl border border-stone-200 bg-white p-5 hover:border-orange-200 hover:bg-orange-50/40">
                        <p class="text-sm font-medium text-stone-900">{{ __('Élèves') }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ __('Gérer les fiches et inscriptions') }}</p>
                    </a>
                @endcan

                @can('viewAny', \App\Domain\Grading\Models\ReportCard::class)
                    <a href="{{ route('grading.report-cards.index') }}" wire:navigate class="rounded-2xl border border-stone-200 bg-white p-5 hover:border-orange-200 hover:bg-orange-50/40">
                        <p class="text-sm font-medium text-stone-900">{{ __('Bulletins') }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ __('Générer et consulter les bulletins') }}</p>
                    </a>
                @endcan

                @can('viewAny', \App\Domain\Attendance\Models\AttendanceSession::class)
                    <a href="{{ route('attendance.sessions.index') }}" wire:navigate class="rounded-2xl border border-stone-200 bg-white p-5 hover:border-orange-200 hover:bg-orange-50/40">
                        <p class="text-sm font-medium text-stone-900">{{ __('Présences') }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ __("Faire l'appel du jour") }}</p>
                    </a>
                @endcan

                @can('viewAny', \App\Domain\Billing\Models\Payment::class)
                    <a href="{{ route('billing.payment-tracking.index') }}" wire:navigate class="rounded-2xl border border-stone-200 bg-white p-5 hover:border-orange-200 hover:bg-orange-50/40">
                        <p class="text-sm font-medium text-stone-900">{{ __('Suivi des paiements') }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ __('Suivre les paiements') }}</p>
                    </a>
                @endcan
            </div>
        @endif
    @endif
</div>
