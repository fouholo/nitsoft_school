<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
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
                    default => '',
                };
            };

            $navItems = [
                ['type' => 'link', 'label' => 'Tableau de bord', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home'],
                ['type' => 'group', 'label' => 'Académique', 'icon' => 'book', 'active' => 'academics.*', 'children' => [
                    ['label' => 'Années scolaires', 'route' => 'academics.school-years.index'],
                    ['label' => 'Périodes', 'route' => 'academics.terms.index'],
                    ['label' => 'Classes', 'route' => 'academics.classrooms.index'],
                    ['label' => 'Matières', 'route' => 'academics.subjects.index'],
                    ['label' => 'Affectations', 'route' => 'academics.teacher-assignments.index'],
                ]],
                ['type' => 'link', 'label' => 'Élèves', 'route' => 'students.index', 'active' => 'students.*', 'icon' => 'users'],
                ['type' => 'link', 'label' => 'Tuteurs', 'route' => 'guardians.index', 'active' => 'guardians.*', 'icon' => 'identification'],
                ['type' => 'group', 'label' => 'Notes', 'icon' => 'document-text', 'active' => 'grading.*', 'children' => [
                    ['label' => 'Évaluations', 'route' => 'grading.grade-sheets.index'],
                    ['label' => 'Bulletins', 'route' => 'grading.report-cards.index'],
                ]],
                ['type' => 'link', 'label' => 'Présences', 'route' => 'attendance.sessions.index', 'active' => 'attendance.*', 'icon' => 'calendar-check'],
                ['type' => 'group', 'label' => 'Facturation', 'icon' => 'banknote', 'active' => 'billing.*', 'children' => [
                    ['label' => 'Grilles tarifaires', 'route' => 'billing.fee-schedules.index'],
                    ['label' => 'Factures', 'route' => 'billing.invoices.index'],
                ]],
                ['type' => 'group', 'label' => 'SMS', 'icon' => 'chat', 'active' => 'notifications.*', 'children' => [
                    ['label' => 'Modèles', 'route' => 'notifications.sms-templates.index'],
                    ['label' => 'Journal', 'route' => 'notifications.sms-messages.index'],
                ]],
            ];

            $initials = collect(explode(' ', auth()->user()->name))
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                ->take(2)
                ->implode('');
        @endphp

        <div class="flex min-h-screen">
            <aside class="flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white">
                <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">N</span>
                    <span class="text-sm font-semibold text-slate-900">{{ config('app.name') }}</span>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @foreach ($navItems as $item)
                        @if ($item['type'] === 'link')
                            @php $isActive = request()->routeIs($item['active']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $isActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0">
                                    {!! $icon($item['icon']) !!}
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        @else
                            <div class="pt-4 first:pt-0">
                                <div class="flex items-center gap-2 px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0">
                                        {!! $icon($item['icon']) !!}
                                    </svg>
                                    {{ $item['label'] }}
                                </div>
                                <div class="space-y-1">
                                    @foreach ($item['children'] as $child)
                                        @php $childActive = request()->routeIs($child['route']); @endphp
                                        <a
                                            href="{{ route($child['route']) }}"
                                            wire:navigate
                                            class="block rounded-lg py-1.5 pl-10 pr-3 text-sm {{ $childActive ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                                        >
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="border-t border-slate-200 p-3">
                    <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                            {{ $initials }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ ucfirst(auth()->user()->currentRole() ?? '') }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Déconnexion" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    {!! $icon('logout') !!}
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex h-16 items-center justify-end border-b border-slate-200 bg-white px-6">
                    @livewire('establishments.switcher')
                </header>

                <main class="flex-1 px-6 py-8">
                    <div class="mx-auto max-w-6xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
