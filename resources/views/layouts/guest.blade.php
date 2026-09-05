<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Study Tracker') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="min-h-screen bg-slate-50 transition-colors duration-200 dark:bg-slate-950">
            <div class="fixed right-4 top-4 z-20">
                <x-theme-toggle />
            </div>

            <div class="flex min-h-screen items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex flex-col items-center gap-4 text-center">
                        <a href="/" class="inline-flex rounded-lg shadow-sm shadow-slate-300/70">
                            <x-application-logo class="h-16 w-16" />
                        </a>

                        <div>
                            <p class="text-sm font-semibold text-teal-700">Study Tracker</p>
                            <h1 class="mt-2 text-2xl font-semibold text-slate-950">Acesse seu painel</h1>
                            <p class="mt-2 text-sm text-slate-500">Organize suas materias, metas e estudos em um so lugar.</p>
                        </div>
                    </div>

                    <div class="w-full overflow-hidden rounded-lg border border-slate-200 bg-white px-6 py-6 shadow-sm shadow-slate-200/80 sm:px-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
