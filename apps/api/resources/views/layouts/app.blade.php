<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('branding/nitsoft-school-logo.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
        @php
            $icon = function (string $name): string {
                return match ($name) {
                    'home' => '<path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h5v-6h4v6h5V9.5" />',
                    'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" /><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" />',
                    'users' => '<circle cx="12" cy="8" r="4" /><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7" />',
                    'identification' => '<rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="12" r="2.5" /><path d="M15 9.5h4M15 12.5h4M6.5 17c.5-1.6 1.7-2.7 2.9-2.7s2.4 1.1 2.9 2.7" />',
                    'document-text' => '<path d="M7 3h7l5 5v13H7z" /><path d="M14 3v5h5" /><path d="M9 13h6M9 17h6" />',
                    'calendar-check' => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /><path d="m9 15 2 2 4-4" />',
                    'banknote' => '<rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M6 9v.01M18 15v.01" />',
                    'chat' => '<path d="M4 5h16v11H8l-4 4z" />',
                    'logout' => '<path d="M9 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3" /><path d="m16 17 5-5-5-5" /><path d="M21 12H9" />',
                    'building' => '<rect x="4" y="3" width="16" height="18" rx="1" /><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2" />',
                    'lock' => '<rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />',
                    default => '',
                };
            };

            $navItems = [
                ['type' => 'link', 'label' => __('Tableau de bord'), 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home'],
                ['type' => 'group', 'label' => __('Académique'), 'icon' => 'book', 'active' => 'academics.*', 'children' => [
                    ['label' => __('Années scolaires'), 'route' => 'academics.school-years.index', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\SchoolYear::class],
                    ['label' => __('Périodes'), 'route' => 'academics.terms.index', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\Term::class],
                    ['label' => __('Classes'), 'route' => 'academics.classrooms.index', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\Classroom::class],
                    ['label' => __('Coefficients par matière'), 'route' => 'academics.subject-coefficients.index', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\SubjectCoefficient::class],
                    ['label' => __('Affectations'), 'route' => 'academics.teacher-assignments.index', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\TeacherAssignment::class],
                ]],
                ['type' => 'link', 'label' => __('Élèves'), 'route' => 'students.index', 'active' => 'students.*', 'icon' => 'users', 'ability' => 'viewAny', 'model' => \App\Domain\Enrollment\Models\Student::class],
                ['type' => 'link', 'label' => __('Tuteurs'), 'route' => 'guardians.index', 'active' => 'guardians.*', 'icon' => 'identification', 'ability' => 'viewAny', 'model' => \App\Domain\Enrollment\Models\Guardian::class],
                ['type' => 'link', 'label' => __('Demandes de liaison'), 'route' => 'guardian-link-requests.index', 'active' => 'guardian-link-requests.*', 'icon' => 'identification', 'ability' => 'viewAny', 'model' => \App\Domain\Enrollment\Models\GuardianStudentPivot::class],
                ['type' => 'group', 'label' => __('Notes'), 'icon' => 'document-text', 'active' => 'grading.*', 'children' => [
                    ['label' => __('Évaluations'), 'route' => 'grading.grade-sheets.index', 'ability' => 'viewAny', 'model' => \App\Domain\Grading\Models\GradeSheet::class],
                    ['label' => __('Bulletins'), 'route' => 'grading.report-cards.index', 'ability' => 'viewAny', 'model' => \App\Domain\Grading\Models\ReportCard::class],
                ]],
                ['type' => 'link', 'label' => __('Présences'), 'route' => 'attendance.sessions.index', 'active' => 'attendance.*', 'icon' => 'calendar-check', 'ability' => 'viewAny', 'model' => \App\Domain\Attendance\Models\AttendanceSession::class],
                ['type' => 'link', 'label' => __('Messagerie'), 'route' => 'messaging.index', 'active' => 'messaging.*', 'icon' => 'chat'],
                ['type' => 'link', 'label' => __('Listes/Rapports'), 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'document-text', 'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\Classroom::class],
                ['type' => 'group', 'label' => __('Facturation'), 'icon' => 'banknote', 'active' => 'billing.*', 'children' => [
                    ['label' => __('Tarifs'), 'route' => 'billing.tuition-fees.index', 'ability' => 'viewAny', 'model' => \App\Domain\Billing\Models\Installment::class],
                    ['label' => __('Dépenses'), 'route' => 'billing.expenses.index', 'ability' => 'viewAny', 'model' => \App\Domain\Billing\Models\Expense::class],
                    ['label' => __('Suivi des paiements'), 'route' => 'billing.payment-tracking.index', 'ability' => 'viewAny', 'model' => \App\Domain\Billing\Models\Payment::class],
                    ['label' => __('Réductions'), 'route' => 'billing.discounts.index', 'ability' => 'viewAny', 'model' => \App\Domain\Billing\Models\Discount::class],
                    ['label' => __('Bilan financier'), 'route' => 'billing.financial-summary.index', 'ability' => 'viewAny', 'model' => \App\Domain\Billing\Models\Payment::class],
                ]],
                ['type' => 'group', 'label' => __('SMS'), 'icon' => 'chat', 'active' => 'notifications.*', 'children' => [
                    ['label' => __('Modèles'), 'route' => 'notifications.sms-templates.index', 'ability' => 'viewAny', 'model' => \App\Domain\Notifications\Models\SmsTemplate::class],
                    ['label' => __('Journal'), 'route' => 'notifications.sms-messages.index', 'ability' => 'viewAny', 'model' => \App\Domain\Notifications\Models\SmsMessage::class],
                    ['label' => __('Envoyer un SMS'), 'route' => 'notifications.sms-messages.create', 'ability' => 'create', 'model' => \App\Domain\Notifications\Models\SmsMessage::class],
                ]],
                // Groupe entier invisible sauf établissement is_arabe : son
                // seul enfant s'appuie sur ArabicSubjectCoefficientPolicy,
                // qui refuse déjà viewAny hors établissement is_arabe — pas
                // besoin de condition PHP supplémentaire ici, le filtrage
                // par ability ci-dessous suffit à vider (et donc masquer) le
                // groupe.
                ['type' => 'group', 'label' => __('Arabe'), 'icon' => 'book', 'active' => 'arabic.*', 'children' => [
                    ['label' => __('Coefficients par matière'), 'route' => 'arabic.subject-coefficients.index', 'ability' => 'viewAny', 'model' => \App\Domain\Arabic\Models\ArabicSubjectCoefficient::class],
                    ['label' => __('Périodes'), 'route' => 'arabic.terms.index', 'ability' => 'viewAny', 'model' => \App\Domain\Arabic\Models\ArabicTerm::class],
                    ['label' => __('Affectations enseignants'), 'route' => 'arabic.teacher-assignments.index', 'ability' => 'viewAny', 'model' => \App\Domain\Arabic\Models\ArabicTeacherAssignment::class],
                    ['label' => __('Grilles de notes'), 'route' => 'arabic.grade-sheets.index', 'ability' => 'viewAny', 'model' => \App\Domain\Arabic\Models\ArabicGradeSheet::class],
                    ['label' => __('Bulletins'), 'route' => 'arabic.report-cards.index', 'ability' => 'viewAny', 'model' => \App\Domain\Arabic\Models\ArabicReportCard::class],
                ]],
            ];

            $canAccessNavItem = fn (array $item): bool => ! isset($item['ability']) || auth()->user()->can($item['ability'], $item['model']);

            $navItems = collect($navItems)
                ->map(function (array $item) use ($canAccessNavItem) {
                    if ($item['type'] === 'group') {
                        $item['children'] = array_values(array_filter($item['children'], $canAccessNavItem));
                    }

                    return $item;
                })
                ->filter(fn (array $item) => $item['type'] === 'group' ? count($item['children']) > 0 : $canAccessNavItem($item))
                ->values()
                ->all();

            /**
             * Tous les écrans ci-dessus (hors Tableau de bord, qui affiche son
             * propre état vide) supposent un établissement courant lié dans le
             * container ("currentEstablishmentId") — un administrateur SaaS
             * sans établissement rattaché n'en a aucun, et ces routes
             * plantent ou tournent non-scopées sans cette liaison. On les
             * retire du menu plutôt que de laisser un lien qui ne fonctionne
             * pas pour ce profil.
             */
            if (! app()->bound('currentEstablishmentId')) {
                $navItems = array_values(array_filter(
                    $navItems,
                    fn (array $item) => $item['type'] === 'link' && $item['route'] === 'dashboard'
                ));
            }

            if (auth()->user()->isSaasAdmin()) {
                $navItems[] = ['type' => 'link', 'label' => __('Groupes scolaires'), 'route' => 'foundations.index', 'active' => 'foundations.*', 'icon' => 'building'];
                $navItems[] = ['type' => 'link', 'label' => __('Établissements'), 'route' => 'establishments.index', 'active' => 'establishments.*', 'icon' => 'building'];
                $navItems[] = ['type' => 'link', 'label' => __('Inspections'), 'route' => 'inspections.index', 'active' => 'inspections.*', 'icon' => 'building'];
                $navItems[] = ['type' => 'link', 'label' => __('Directions'), 'route' => 'directions.index', 'active' => 'directions.*', 'icon' => 'building'];
                $navItems[] = ['type' => 'link', 'label' => __('Matières'), 'route' => 'academics.subjects.index', 'active' => 'academics.subjects.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Matières du primaire'), 'route' => 'academics.primary-subjects.index', 'active' => 'academics.primary-subjects.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __("Barème d'appréciations"), 'route' => 'academics.appreciation-scales.index', 'active' => 'academics.appreciation-scales.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Domaines'), 'route' => 'domains.index', 'active' => 'domains.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Niveaux arabes'), 'route' => 'arabic.levels.index', 'active' => 'arabic.levels.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Séries arabes'), 'route' => 'arabic.series.index', 'active' => 'arabic.series.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Matières arabes'), 'route' => 'arabic.subjects.index', 'active' => 'arabic.subjects.*', 'icon' => 'book'];
                $navItems[] = ['type' => 'link', 'label' => __('Informations générales'), 'route' => 'general-information.edit', 'active' => 'general-information.*', 'icon' => 'building'];
            }

            if (auth()->user()->isMainSaasAdmin()) {
                $navItems[] = ['type' => 'link', 'label' => __('Administrateurs SaaS'), 'route' => 'saas-admins.index', 'active' => 'saas-admins.*', 'icon' => 'users'];
            }

            if (app()->bound('currentEstablishmentId')) {
                $currentEstablishment = \App\Domain\Establishments\Models\Establishment::find(app('currentEstablishmentId'));

                if ($currentEstablishment !== null && auth()->user()->isLocalAdminOf($currentEstablishment)) {
                    $navItems[] = ['type' => 'link', 'label' => __('Mon établissement'), 'route' => 'staff.index', 'active' => 'staff.index', 'icon' => 'users', 'params' => ['establishment' => $currentEstablishment->id]];
                }
            }

            if (auth()->user()->isFondateurSomewhere()) {
                $navItems[] = ['type' => 'link', 'label' => __('Mon organisation'), 'route' => 'staff.organization', 'active' => 'staff.organization', 'icon' => 'building'];
            }

            $initials = collect(explode(' ', auth()->user()->name))
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                ->take(2)
                ->implode('');
        @endphp

        <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">
            <div
                x-show="sidebarOpen"
                x-transition.opacity
                class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden"
                @click="sidebarOpen = false"
                style="display: none;"
            ></div>

            <aside
                class="fixed inset-y-0 start-0 z-40 flex w-64 shrink-0 -translate-x-full max-lg:rtl:translate-x-full transform flex-col border-e border-stone-200 bg-white transition-transform duration-200 ease-out lg:static lg:translate-x-0"
                :class="sidebarOpen && '!translate-x-0'"
            >
                <div class="flex h-16 items-center gap-2 border-b border-stone-200 px-5">
                    <img src="{{ asset('branding/nitsoft-school-logo.png') }}" alt="{{ config('app.name') }}" class="h-9 w-9 shrink-0 object-contain">
                    <span class="text-xl font-semibold leading-none text-blue-900 whitespace-nowrap">{{ config('app.name') }}</span>
                    <button type="button" class="ms-auto rounded-lg p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700 lg:hidden" aria-label="{{ __('Fermer le menu') }}" @click="sidebarOpen = false">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <path d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @foreach ($navItems as $item)
                        @if ($item['type'] === 'link')
                            @php $isActive = request()->routeIs($item['active']); @endphp
                            <a
                                href="{{ route($item['route'], $item['params'] ?? []) }}"
                                wire:navigate
                                @click="sidebarOpen = false"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $isActive ? 'bg-orange-100 font-semibold text-orange-800' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0">
                                    {!! $icon($item['icon']) !!}
                                </svg>
                                {{ $item['label'] }}
                                @if ($item['route'] === 'messaging.index')
                                    @livewire('messaging.unread-badge')
                                @endif
                            </a>
                        @else
                            @php $groupActive = request()->routeIs($item['active']); @endphp
                            <details {{ $groupActive ? 'open' : '' }} class="group pt-3 first:pt-0">
                                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide text-stone-500 hover:bg-stone-100 hover:text-stone-700 [&::-webkit-details-marker]:hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0">
                                        {!! $icon($item['icon']) !!}
                                    </svg>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0 text-stone-400 transition-transform duration-150 group-open:rotate-90">
                                        <path d="m9 6 6 6-6 6" />
                                    </svg>
                                </summary>
                                <div class="mt-1 space-y-1 pb-1">
                                    @foreach ($item['children'] as $child)
                                        @php $childActive = request()->routeIs($child['route']); @endphp
                                        <a
                                            href="{{ route($child['route']) }}"
                                            wire:navigate
                                            @click="sidebarOpen = false"
                                            class="block rounded-lg py-1.5 ps-10 pe-3 text-sm {{ $childActive ? 'bg-orange-100 font-semibold text-orange-800' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}"
                                        >
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach
                </nav>

                <div class="border-t border-stone-200 p-3">
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100 text-sm font-semibold text-orange-800">
                            {{ $initials }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-stone-900" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-stone-500">{{ auth()->user()->currentRoleLabel() }}</p>
                        </div>
                        <a href="{{ route('account.password.edit') }}" wire:navigate title="{{ __('Mot de passe') }}" aria-label="{{ __('Mot de passe') }}" class="rounded-lg p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                {!! $icon('lock') !!}
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="{{ __('Déconnexion') }}" aria-label="{{ __('Déconnexion') }}" class="rounded-lg p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    {!! $icon('logout') !!}
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex h-16 items-center justify-between gap-3 border-b border-stone-200 bg-white px-4 sm:px-6">
                    <button type="button" class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-700 lg:hidden" aria-label="{{ __('Ouvrir le menu') }}" @click="sidebarOpen = true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                    <div class="ms-auto flex items-center gap-3">
                        <div class="flex items-center gap-1 text-xs font-medium text-stone-500">
                            @foreach (config('app.supported_locales') as $localeOption)
                                <a
                                    href="{{ route('locale.switch', $localeOption) }}"
                                    class="rounded px-1.5 py-1 uppercase {{ app()->getLocale() === $localeOption ? 'bg-stone-100 text-stone-900' : 'hover:text-stone-700' }}"
                                >{{ $localeOption }}</a>
                            @endforeach
                        </div>
                        @livewire('establishments.switcher')
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8">
                    <div class="mx-auto max-w-6xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
