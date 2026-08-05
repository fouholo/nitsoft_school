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
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-900">
                        {{ config('app.name') }}
                    </a>

                    <div class="hidden items-center gap-4 text-sm text-slate-600 sm:flex">
                        <a href="{{ route('academics.school-years.index') }}" class="hover:text-slate-900">Années scolaires</a>
                        <a href="{{ route('academics.classrooms.index') }}" class="hover:text-slate-900">Classes</a>
                        <a href="{{ route('academics.subjects.index') }}" class="hover:text-slate-900">Matières</a>
                        <a href="{{ route('students.index') }}" class="hover:text-slate-900">Élèves</a>
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
