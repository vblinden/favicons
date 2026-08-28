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

            <form
                id="favicon-playground"
                class="animate-rise-delay-2 mt-12 space-y-4"
                action="{{ route('favicons.show', ['domain' => 'example.com']) }}"
                method="get"
            >
                <label for="playground-domain" class="block text-sm font-medium text-ink-soft">
                    Try a domain
                </label>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        id="playground-domain"
                        name="domain"
                        type="text"
                        value="github.com"
                        autocomplete="off"
                        spellcheck="false"
                        class="w-full rounded-lg border border-ink/10 bg-paper-deep/60 px-4 py-3 font-mono text-sm text-ink outline-none ring-accent focus:ring-2 sm:max-w-xs"
                    >
                    <button
                        type="submit"
                        class="rounded-lg bg-accent px-4 py-3 text-sm font-semibold text-white transition hover:bg-accent-bright"
                    >
                        Preview
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <img
                        id="playground-preview"
                        src="{{ route('favicons.show', ['domain' => 'github.com', 'sz' => 64]) }}"
                        alt="Preview favicon"
                        width="48"
                        height="48"
                        class="size-12 rounded-lg bg-paper-deep/80 p-1"
                    >
                    <code id="playground-snippet" class="block max-w-full overflow-x-auto rounded-lg bg-paper-deep/70 px-3 py-2 font-mono text-xs text-ink sm:text-sm">&lt;img src="{{ url('/i/github.com') }}" alt="" width="32" height="32"&gt;</code>
                    <button
                        type="button"
                        id="playground-copy"
                        class="rounded-lg border border-ink/15 px-3 py-2 text-xs font-medium text-ink-soft transition hover:border-accent hover:text-accent"
                    >
                        Copy
                    </button>
                </div>
            </form>

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
                <p class="text-ink-soft/80">
                    Optional theme:
                    <code class="text-ink">?theme=dark</code>
                    <span class="text-ink-soft/70">or</span>
                    <code class="text-ink">?theme=light</code>
                </p>
                <p class="text-ink-soft/80">
                    Optional format:
                    <code class="text-ink">?format=webp</code>
                </p>
                <p class="text-ink-soft">
                    <span class="text-accent">GET</span>
                    <code class="ml-2 break-all text-ink">{{ url('/m/github.com') }}</code>
                    <span class="text-ink-soft/70">metadata</span>
                </p>
                <p class="text-ink-soft">
                    <span class="text-accent">GET</span>
                    <code class="ml-2 break-all text-ink">{{ url('/b?domains=github.com,laravel.com') }}</code>
                    <span class="text-ink-soft/70">batch</span>
                </p>
                <p class="text-ink-soft">
                    <span class="text-accent">GET</span>
                    <code class="ml-2 break-all text-ink">{{ url('/s2/favicons?domain=github.com&sz=64') }}</code>
                    <span class="text-ink-soft/70">Google-compatible</span>
                </p>
                <p class="text-ink-soft">
                    <span class="text-danger">DELETE</span>
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
