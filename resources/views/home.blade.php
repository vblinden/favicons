<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Drop-in favicon URLs for any domain. Cached, easily refreshed, and great for hotlinking.">

        <title>Favicons — drop-in favicon API</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-mesh min-h-screen antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center px-6 py-16 sm:px-8">
            <p class="font-display animate-rise text-5xl font-extrabold tracking-tight text-ink sm:text-7xl">
                Favicons
            </p>

            <p class="animate-rise-delay-1 mt-4 max-w-xl text-lg text-ink-soft sm:text-xl">
                Drop-in favicon URLs for any domain. Cached, easily refreshed, and great for hotlinking.
            </p>

            <div class="animate-rise-delay-2 mt-10 flex flex-wrap items-center gap-3">
                @foreach (['github.com', 'laravel.com', 'nvidia.com', 'x.ai', 'apple.com'] as $domain)
                    <a
                        href="{{ route('favicons.show', ['domain' => $domain]) }}"
                        class="animate-float rounded-lg bg-paper-deep/80 p-1.5 transition hover:bg-paper-deep"
                        style="animation-delay: {{ $loop->index * 0.15 }}s"
                        title="{{ $domain }}"
                    >
                        <img
                            src="{{ route('favicons.show', ['domain' => $domain, 'sz' => 64]) }}"
                            alt="{{ $domain }} favicon"
                            width="40"
                            height="40"
                            class="size-10"
                            loading="eager"
                        >
                    </a>
                @endforeach
            </div>

            <div class="animate-rise-delay-3 mt-12 space-y-4 font-mono text-sm sm:text-base">
                <p class="text-ink-soft">
                    <span class="text-accent">GET</span>
                    <a
                        href="{{ route('favicons.show', ['domain' => 'github.com']) }}"
                        class="ml-2 break-all text-ink underline decoration-accent/40 underline-offset-4 transition hover:decoration-accent"
                    >{{ url('/i/github.com') }}</a>
                </p>
                <p class="text-ink-soft/80">
                    Optional size:
                    <code class="text-ink">?sz=64</code>
                    <span class="text-ink-soft/70">(default 32)</span>
                </p>
                <p class="text-ink-soft">
                    <span class="text-[#9b1c1c]">DELETE</span>
                    <code class="ml-2 break-all text-ink">{{ url('/r/github.com') }}</code>
                </p>
                <p class="text-ink-soft">
                    <span class="text-accent">GET</span>
                    <a
                        href="{{ route('leaderboard') }}"
                        class="ml-2 break-all text-ink underline decoration-accent/40 underline-offset-4 transition hover:decoration-accent"
                    >{{ url('/leaderboard') }}</a>
                </p>
            </div>

            <x-site-footer class="animate-rise-delay-3 mt-16" />
        </main>
    </body>
</html>
