@props([
    'heading',
    'updated',
])

<article class="space-y-8 text-ink-soft">
    <header class="space-y-3">
        <h1 class="font-display text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
            {{ $heading }}
        </h1>
        <p class="text-sm text-ink-soft/80">
            Last updated {{ $updated }}
        </p>
    </header>

    <div class="space-y-6 text-base leading-relaxed sm:text-lg [&_a]:text-ink [&_a]:underline [&_a]:decoration-accent/40 [&_a]:underline-offset-4 [&_a]:transition hover:[&_a]:decoration-accent [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-ink [&_li]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:max-w-2xl [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5">
        {{ $slot }}
    </div>
</article>
