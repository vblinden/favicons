<footer {{ $attributes->merge(['class' => 'flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink-soft']) }}>
    <a
        href="{{ route('legal.terms') }}"
        class="underline decoration-accent/30 underline-offset-4 transition hover:text-ink hover:decoration-accent"
    >Terms</a>
    <a
        href="{{ route('legal.acceptable-use') }}"
        class="underline decoration-accent/30 underline-offset-4 transition hover:text-ink hover:decoration-accent"
    >Acceptable use</a>
    <a
        href="{{ route('legal.privacy') }}"
        class="underline decoration-accent/30 underline-offset-4 transition hover:text-ink hover:decoration-accent"
    >Privacy</a>
    <a
        href="{{ url('/llms.txt') }}"
        class="underline decoration-accent/30 underline-offset-4 transition hover:text-ink hover:decoration-accent"
    >llms.txt</a>
</footer>
