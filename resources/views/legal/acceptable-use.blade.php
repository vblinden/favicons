<x-document
    title="Acceptable Use"
    description="Acceptable Use Policy for the Favicons drop-in favicon API."
>
    <x-legal-article heading="Acceptable Use Policy" updated="16 August 2026">
        <p>
            This Acceptable Use Policy (“Policy”) explains what is and is not allowed when using
            Favicons (the “Service”). It forms part of the
            <a href="{{ route('legal.terms') }}">Terms of Service</a>.
        </p>

        <h2>Intended use</h2>
        <p>You may use the Service to:</p>
        <ul>
            <li>Request favicon images for legitimate domains via the documented endpoints</li>
            <li>Hotlink or embed those image URLs in applications, sites, emails, and similar products</li>
            <li>Force-refresh a cached favicon within the published rate limits when you need a newer copy</li>
            <li>View public pages such as the home page and leaderboard</li>
        </ul>

        <h2>Prohibited use</h2>
        <p>You may not use the Service to:</p>
        <ul>
            <li>Attack, scan, or overload this Service or any third-party system</li>
            <li>Attempt to bypass rate limits, authentication, or other protective controls</li>
            <li>Use the refresh endpoint as a general-purpose proxy, scraper, or denial-of-service tool</li>
            <li>Request domains or resources in a way intended to cause harm, harassment, or illegal activity</li>
            <li>Interfere with caching, storage, or other users’ access to the Service</li>
            <li>Misrepresent the Service as belonging to another party, or remove notices that identify Favicons where attribution is reasonably expected</li>
            <li>Violate applicable law, including intellectual property, privacy, and computer misuse laws</li>
        </ul>

        <h2>Automated access</h2>
        <p>
            Reasonable automated use of the image endpoints is allowed and expected. You must still
            honor rate limits, back off on errors, and avoid traffic patterns that degrade the Service
            for others. High-volume or abusive automation may be throttled or blocked without notice.
        </p>

        <h2>Third-party websites</h2>
        <p>
            When the Service fetches a favicon, it contacts third-party sites on your behalf. Do not
            use the Service to target sites you are not permitted to access, or to evade those sites’
            own access controls.
        </p>

        <h2>Enforcement</h2>
        <p>
            We may investigate suspected violations and respond with rate limiting, temporary blocks,
            permanent blocks, deletion of cached data, or other measures we consider appropriate.
        </p>

        <h2>Reporting</h2>
        <p>
            Report abuse or security concerns via
            <a href="https://github.com/vblinden/favicons/issues">GitHub Issues</a>.
            Do not disclose security vulnerabilities publicly before we have had a reasonable chance
            to respond.
        </p>
    </x-legal-article>
</x-document>
