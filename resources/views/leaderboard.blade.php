<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Most requested favicons served by this API.">

        <title>Leaderboard — Favicons</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-mesh min-h-screen antialiased">
        <main class="mx-auto w-full max-w-3xl px-6 py-16 sm:px-8">
            <p class="animate-rise">
                <a
                    href="{{ route('home') }}"
                    class="text-sm text-ink-soft underline decoration-accent/40 underline-offset-4 transition hover:text-ink hover:decoration-accent"
                >Back home</a>
            </p>

            @if ($entries->isEmpty())
                <p class="animate-rise-delay-1 mt-16 text-ink-soft">
                    No requests yet. Hit
                    <a
                        href="{{ route('favicons.show', ['domain' => 'github.com']) }}"
                        class="text-ink underline decoration-accent/40 underline-offset-4 transition hover:decoration-accent"
                    >{{ url('/i/github.com') }}</a>
                    to get started.
                </p>
            @else
                <ol class="animate-rise-delay-1 mt-12 space-y-3">
                    @foreach ($entries as $entry)
                        <li class="flex items-center gap-4">
                            <span class="w-8 shrink-0 font-mono text-sm tabular-nums text-ink-soft">
                                {{ $entry->rank }}
                            </span>

                            <span class="shrink-0 rounded-lg bg-paper-deep/80 p-1.5">
                                <img
                                    src="{{ $entry->preview }}"
                                    alt="{{ $entry->domain }} favicon"
                                    width="32"
                                    height="32"
                                    class="size-8"
                                    loading="lazy"
                                >
                            </span>

                            <a
                                href="{{ route('favicons.show', ['domain' => $entry->domain]) }}"
                                class="min-w-0 flex-1 truncate font-mono text-sm text-ink underline decoration-accent/30 underline-offset-4 transition hover:decoration-accent sm:text-base"
                            >{{ $entry->domain }}</a>

                            <span class="shrink-0 font-mono text-sm tabular-nums text-ink-soft">
                                {{ number_format($entry->request_count) }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </main>
    </body>
</html>
