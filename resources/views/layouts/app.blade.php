<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BarnoTrading') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-slate-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-slate-900 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <x-market-status />
            <x-alert-banner />

            <main>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
                    @if (session('status'))
                        <div class="rounded-md bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-2 text-sm">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="rounded-md bg-red-900/40 border border-red-700 text-red-200 px-4 py-2 text-sm">{{ session('error') }}</div>
                    @endif
                    @foreach ((array) session('warnings', []) as $warning)
                        <div class="rounded-md bg-amber-900/40 border border-amber-700 text-amber-200 px-4 py-2 text-sm">⚠ {{ $warning }}</div>
                    @endforeach
                    @if ($errors->any() && ! request()->routeIs('profile.*', 'password.*'))
                        <div class="rounded-md bg-red-900/40 border border-red-700 text-red-200 px-4 py-2 text-sm">{{ $errors->first() }}</div>
                    @endif
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
