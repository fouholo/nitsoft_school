<div>
    <h1 class="text-2xl font-semibold text-slate-900">Tableau de bord</h1>

    @if ($noEstablishment)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            Aucun établissement ne vous est actuellement accessible. Contactez votre administrateur.
        </div>
    @else
        <p class="mt-1 text-sm text-slate-500">
            Connecté en tant que <span class="font-medium text-slate-700">{{ $roleLabel }}</span> sur cet établissement.
        </p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <circle cx="12" cy="8" r="4" /><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-2xl font-semibold text-slate-900">{{ $studentsCount }}</p>
                        <p class="text-sm text-slate-500">Élèves actifs</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" /><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-2xl font-semibold text-slate-900">{{ $classroomsCount }}</p>
                        <p class="text-sm text-slate-500">Classes (année en cours)</p>
                    </div>
                </div>
            </div>

            @if (in_array($role, ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'], true))
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M6 9v.01M18 15v.01" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-2xl font-semibold text-slate-900">{{ $pendingInvoicesCount }}</p>
                            <p class="text-sm text-slate-500">Factures en attente</p>
                            <p class="text-xs text-slate-400">{{ money($pendingInvoicesBalance) }} restant à percevoir</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (in_array($role, ['directeur', 'gestionnaire', 'fondateur'], true))
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="12" r="2.5" /><path d="M15 9.5h4M15 12.5h4M6.5 17c.5-1.6 1.7-2.7 2.9-2.7s2.4 1.1 2.9 2.7" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-2xl font-semibold text-slate-900">{{ $staffCount }}</p>
                            <p class="text-sm text-slate-500">Membres du personnel</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <h2 class="mt-10 text-sm font-semibold uppercase tracking-wide text-slate-500">Accès rapides</h2>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('students.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 hover:border-indigo-200 hover:bg-indigo-50/40">
                <p class="text-sm font-medium text-slate-900">Élèves</p>
                <p class="mt-1 text-xs text-slate-500">Gérer les fiches et inscriptions</p>
            </a>

            <a href="{{ route('grading.report-cards.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 hover:border-indigo-200 hover:bg-indigo-50/40">
                <p class="text-sm font-medium text-slate-900">Bulletins</p>
                <p class="mt-1 text-xs text-slate-500">Générer et consulter les bulletins</p>
            </a>

            <a href="{{ route('attendance.sessions.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 hover:border-indigo-200 hover:bg-indigo-50/40">
                <p class="text-sm font-medium text-slate-900">Présences</p>
                <p class="mt-1 text-xs text-slate-500">Faire l'appel du jour</p>
            </a>

            <a href="{{ route('billing.payment-tracking.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white p-5 hover:border-indigo-200 hover:bg-indigo-50/40">
                <p class="text-sm font-medium text-slate-900">Suivi des paiements</p>
                <p class="mt-1 text-xs text-slate-500">Suivre les paiements</p>
            </a>
        </div>
    @endif
</div>
