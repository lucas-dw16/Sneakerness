<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col bg-zinc-950 text-zinc-100 antialiased">
    <header class="border-b border-zinc-800/80 backdrop-blur bg-zinc-900/70 sticky top-0 z-30">
        <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center h-14 gap-8">
            <a href="{{ url('/') }}" class="font-semibold tracking-wide text-lg">{{ config('app.name','Sneakerness') }}</a>
            <div class="flex-1 flex items-center gap-6 text-sm">
                <a href="{{ route('events.index') }}" class="hover:text-indigo-400 transition">Events</a>
                <a href="{{ route('contact.show') }}" class="hover:text-indigo-400 transition">Contact</a>
            </div>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <a href="/admin" class="inline-flex items-center gap-1 rounded-md border border-zinc-700 px-3 py-1.5 hover:border-indigo-500 hover:text-indigo-300 transition">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                        @csrf
                        <button class="inline-flex items-center rounded-md bg-zinc-700/70 hover:bg-indigo-600 px-3 py-1.5 text-sm font-medium transition">Logout</button>
                    </form>
                @else
                    @php
                        $loginUrl = (\Illuminate\Support\Facades\Route::has('filament.admin.auth.login'))
                            ? route('filament.admin.auth.login')
                            : (\Illuminate\Support\Facades\Route::has('login')
                                ? route('login')
                                : '/dashboard/login');
                    @endphp
                    <a href="{{ $loginUrl }}" class="inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 text-sm font-medium transition">Login</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="flex-1">
        @if(session('success'))
            <div class="bg-emerald-600/20 text-emerald-300 py-3 text-sm text-center">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-rose-700/20 text-rose-300 py-3 text-sm text-center">
                {{ __('Er zijn formulier fouten. Controleer aub.') }}
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="mt-16 border-t border-zinc-800/70 bg-zinc-900/60 py-8 text-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between gap-4">
            <p class="text-zinc-400">&copy; {{ now()->year }} {{ config('app.name') }}. Alle rechten voorbehouden.</p>
            <div class="flex gap-6">
                <a href="{{ route('contact.show') }}" class="hover:text-indigo-400">Contact</a>
                <a href="{{ route('events.index') }}" class="hover:text-indigo-400">Events</a>
            </div>
        </div>
    </footer>
</body>
</html>