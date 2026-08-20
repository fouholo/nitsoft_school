<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-50 antialiased">
        <nav class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
                <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm font-semibold text-stone-900">
                    {{ config('app.name') }}<span class="hidden sm:inline"> — Espace parents</span>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ route('guardian-portal.link-child') }}" wire:navigate class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                        Lier un enfant
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-stone-500 hover:text-stone-900">
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-3xl px-4 py-8">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
