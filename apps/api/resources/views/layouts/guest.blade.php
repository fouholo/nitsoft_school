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
    <body class="min-h-screen bg-stone-100 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4">
            <div class="fixed top-4 end-4 flex items-center gap-1 text-xs font-medium text-stone-500">
                @foreach (config('app.supported_locales') as $localeOption)
                    <a
                        href="{{ route('locale.switch', $localeOption) }}"
                        class="rounded px-1.5 py-1 uppercase {{ app()->getLocale() === $localeOption ? 'bg-white text-stone-900 shadow-sm' : 'hover:text-stone-700' }}"
                    >{{ $localeOption }}</a>
                @endforeach
            </div>

            <div class="mb-8 flex flex-col items-center gap-2">
                <img src="{{ asset('branding/nitsoft-school-logo.png') }}" alt="{{ config('app.name') }}" class="h-16 w-16 object-contain">
                <div class="text-[2.5rem] font-semibold leading-none text-blue-700">{{ config('app.name') }}</div>
            </div>

            <div class="w-full max-w-sm rounded-xl bg-white p-8 shadow">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
