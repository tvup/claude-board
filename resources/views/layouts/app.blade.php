<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Claude Board</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen antialiased">
    <header class="border-b border-panel-border bg-panel backdrop-blur-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <span class="text-cyber-green font-bold text-xl tracking-tight">&#x27E6;*&#x27E7;</span>
                <h1 class="text-lg font-semibold text-white">Claude Board</h1>
            </a>
            <div class="flex items-center gap-4 text-sm text-gray-400">
                <span id="last-update">-</span>
                <span class="inline-flex items-center gap-1.5">
                    <span id="status-dot" class="w-2 h-2 rounded-full bg-cyber-green animate-pulse"></span>
                    <span id="status-text">{{ __('dashboard.live') }}</span>
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-panel-border mt-12 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-500">
            <span>&copy; {{ date('Y') }} <a href="mailto:contact@torbenit.dk" class="text-gray-400 hover:text-cyber-blue transition">Torben IT ApS</a> &middot; CVR 39630605</span>
            <span>Claude Board <span class="text-gray-600">{{ app(\App\Services\VersionService::class)->resolve() }}</span> &middot; <a href="https://github.com/tvup/claude-board" class="text-gray-400 hover:text-cyber-blue transition">GitHub</a> &middot; MIT License</span>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
