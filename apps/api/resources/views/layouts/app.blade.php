<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 antialiased">
        <nav class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-900">
                        {{ config('app.name') }}
                    </a>

                    <div class="hidden items-center gap-1 text-sm text-slate-600 sm:flex">
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">
                                Académique
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-10 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('academics.school-years.index') }}" class="block px-4 py-2 hover:bg-slate-50">Années scolaires</a>
                                <a href="{{ route('academics.terms.index') }}" class="block px-4 py-2 hover:bg-slate-50">Périodes</a>
                                <a href="{{ route('academics.classrooms.index') }}" class="block px-4 py-2 hover:bg-slate-50">Classes</a>
                                <a href="{{ route('academics.subjects.index') }}" class="block px-4 py-2 hover:bg-slate-50">Matières</a>
                                <a href="{{ route('academics.teacher-assignments.index') }}" class="block px-4 py-2 hover:bg-slate-50">Affectations</a>
                            </div>
                        </div>

                        <a href="{{ route('students.index') }}" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">Élèves</a>
                        <a href="{{ route('guardians.index') }}" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">Tuteurs</a>

                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">
                                Notes
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-10 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('grading.grade-sheets.index') }}" class="block px-4 py-2 hover:bg-slate-50">Évaluations</a>
                                <a href="{{ route('grading.report-cards.index') }}" class="block px-4 py-2 hover:bg-slate-50">Bulletins</a>
                            </div>
                        </div>

                        <a href="{{ route('attendance.sessions.index') }}" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">Présences</a>

                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">
                                Facturation
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-10 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('billing.fee-schedules.index') }}" class="block px-4 py-2 hover:bg-slate-50">Grilles tarifaires</a>
                                <a href="{{ route('billing.invoices.index') }}" class="block px-4 py-2 hover:bg-slate-50">Factures</a>
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="rounded-md px-2 py-1 hover:bg-slate-100 hover:text-slate-900">
                                SMS
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-10 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('notifications.sms-templates.index') }}" class="block px-4 py-2 hover:bg-slate-50">Modèles</a>
                                <a href="{{ route('notifications.sms-messages.index') }}" class="block px-4 py-2 hover:bg-slate-50">Journal</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    @livewire('establishments.switcher')

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 hover:text-slate-900">
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
