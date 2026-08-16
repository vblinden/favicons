@props([
    'title',
    'description',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description }}">

        <title>{{ $title }} — Favicons</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-mesh min-h-screen antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-6 py-16 sm:px-8">
            <p class="animate-rise">
                <a
                    href="{{ route('home') }}"
                    class="text-sm text-ink-soft underline decoration-accent/40 underline-offset-4 transition hover:text-ink hover:decoration-accent"
                >Back home</a>
            </p>

            <div class="animate-rise-delay-1 mt-12 flex-1">
                {{ $slot }}
            </div>

            <x-site-footer class="animate-rise-delay-2 mt-16" />
        </main>
    </body>
</html>
